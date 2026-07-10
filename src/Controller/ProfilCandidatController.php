<?php

namespace App\Controller;

use App\Entity\ProfilCandidat;
use App\Entity\User;
use App\Form\ProfilCandidatEditType;
use App\Repository\MatchingPreviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * ProfilCandidatController
 *
 * Page "Mon profil" du dashboard candidat.
 * Affiche les données saisies à l'inscription ET les données enrichies
 * automatiquement par l'IA à partir du CV (App\Service\CvAiProfileAnalyzer) :
 * années d'expérience, langues parlées, compétences techniques, formations,
 * expériences professionnelles, résumé IA.
 *
 * Une page d'édition (app_profil_candidat_edit) permet au candidat de
 * corriger manuellement TOUS ces champs après leur remplissage automatique
 * par l'IA.
 *
 * NOUVEAU : toute modification manuelle du profil invalide le cache des
 * scores IA "recommandés" (MatchingPreview) déjà calculés pour ce candidat,
 * afin que le dashboard recalcule des scores à jour reflétant les
 * changements — jamais un score basé sur d'anciennes compétences.
 *
 * Routes déjà couvertes par security.yaml (préfixe ^/candidat → ROLE_CANDIDAT),
 * aucune modification de security.yaml n'est nécessaire.
 */
#[IsGranted('ROLE_CANDIDAT')]
class ProfilCandidatController extends AbstractController
{
    #[Route('/candidat/profil', name: 'app_profil_candidat', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $profil = $this->getOrCreateProfil($entityManager);

        return $this->render('candidat/profil.html.twig', [
            'profil' => $profil,
            'user' => $this->getUser(),
        ]);
    }

    #[Route('/candidat/profil/modifier', name: 'app_profil_candidat_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        MatchingPreviewRepository $matchingPreviewRepository
    ): Response {
        $profil = $this->getOrCreateProfil($entityManager);

        $form = $this->createForm(ProfilCandidatEditType::class, $profil);

        // Pré-remplissage des champs texte "libres" à partir des tableaux
        // actuellement stockés (issus soit de l'IA, soit d'une édition
        // précédente) — uniquement lors de l'affichage initial du formulaire.
        if (!$request->isMethod('POST')) {
            $form->get('languesParleesText')->setData(implode("\n", $profil->getLanguesParleesArray()));
            $form->get('competencesTechniquesText')->setData(implode("\n", $profil->getCompetencesTechniquesArray()));
            $form->get('formationsText')->setData(implode("\n", $profil->getFormationsArray()));
            $form->get('experiencesProfessionnellesText')->setData(implode("\n", $profil->getExperiencesProfessionnellesArray()));
            $form->get('projetsAcademiquesText')->setData(implode("\n", $profil->getProjetsAcademiquesArray()));
            $form->get('softSkillsText')->setData(implode("\n", $profil->getSoftSkillsArray()));
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $photoFile = $form->get('photo')->getData();
            if ($photoFile instanceof UploadedFile) {
                $profil->setPhoto(
                    $this->storeUploadedFile($photoFile, 'uploads/candidats/photos', $slugger)
                );
            }

            // Conversion texte (un élément par ligne) -> tableau JSON,
            // via les mêmes méthodes *Array() utilisées par CvAiProfileAnalyzer.
            $profil->setLanguesParleesArray($this->splitLines($form->get('languesParleesText')->getData()));
            $profil->setCompetencesTechniquesArray($this->splitLines($form->get('competencesTechniquesText')->getData()));
            $profil->setFormationsArray($this->splitLines($form->get('formationsText')->getData()));
            $profil->setExperiencesProfessionnellesArray($this->splitLines($form->get('experiencesProfessionnellesText')->getData()));
            $profil->setProjetsAcademiquesArray($this->splitLines($form->get('projetsAcademiquesText')->getData()));
            $profil->setSoftSkillsArray($this->splitLines($form->get('softSkillsText')->getData()));

            $entityManager->flush();

            // NOUVEAU : invalide le cache de scores IA "recommandés" — le
            // profil ayant changé manuellement, les anciens scores ne sont
            // plus fiables. Le prochain chargement du dashboard recalculera.
            $matchingPreviewRepository->deleteAllForCandidat($profil);

            $this->addFlash('success', 'Votre profil a été mis à jour avec succès.');

            return $this->redirectToRoute('app_profil_candidat');
        }

        return $this->render('candidat/profil_edit.html.twig', [
            'form' => $form,
            'profil' => $profil,
        ]);
    }

    // ── Helpers privés ──────────────────────────────────────────────

    private function getOrCreateProfil(EntityManagerInterface $entityManager): ProfilCandidat
    {
        /** @var User $user */
        $user = $this->getUser();
        $profil = $user->getProfilCandidat();

        if (!$profil) {
            $profil = new ProfilCandidat();
            $user->setProfilCandidat($profil);

            $entityManager->persist($profil);
            $entityManager->flush();
        }

        return $profil;
    }

    /**
     * Convertit un textarea "un élément par ligne" en tableau de chaînes
     * nettoyées (trim, lignes vides ignorées).
     *
     * @return string[]
     */
    private function splitLines(?string $text): array
    {
        if ($text === null || trim($text) === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $text);

        return array_values(array_filter(
            array_map('trim', $lines),
            static fn (string $line): bool => $line !== ''
        ));
    }

    private function storeUploadedFile(UploadedFile $file, string $relativeDirectory, SluggerInterface $slugger): string
    {
        $projectDir = $this->getParameter('kernel.project_dir');
        $targetDirectory = $projectDir . '/public/' . $relativeDirectory;

        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0777, true);
        }

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename)->lower();
        $newFilename = $safeFilename . '-' . uniqid('', true) . '.' . $file->guessExtension();

        $file->move($targetDirectory, $newFilename);

        return $relativeDirectory . '/' . $newFilename;
    }
}