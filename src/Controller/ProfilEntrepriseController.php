<?php

namespace App\Controller;

use App\Entity\ProfilEntreprise;
use App\Entity\User;
use App\Form\ProfilEntrepriseEditType;
use App\Repository\OffreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * ProfilEntrepriseController
 *
 * Page "Mon profil" côté entreprise, présentée sous une forme
 * "réseau social professionnel" (photo de couverture + logo + infos
 * clés saisies à l'inscription, mises en forme de façon plus visuelle).
 *
 * Routes déjà couvertes par security.yaml (préfixe ^/entreprise →
 * ROLE_ENTREPRISE), aucune modification de security.yaml nécessaire.
 */
#[IsGranted('ROLE_ENTREPRISE')]
class ProfilEntrepriseController extends AbstractController
{
    #[Route('/entreprise/profil', name: 'app_profil_entreprise', methods: ['GET'])]
    public function index(OffreRepository $offreRepository): Response
    {
        $profil = $this->getProfilEntrepriseOrThrow();

        return $this->render('entreprise/profil.html.twig', [
            'profil' => $profil,
            'user' => $this->getUser(),
            'offresActives' => $offreRepository->countActiveByEntreprise($profil),
            'offresArchivees' => $offreRepository->countArchivedByEntreprise($profil),
        ]);
    }

    #[Route('/entreprise/profil/modifier', name: 'app_profil_entreprise_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $profil = $this->getProfilEntrepriseOrThrow();

        $form = $this->createForm(ProfilEntrepriseEditType::class, $profil);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $logoFile = $form->get('logo')->getData();
            if ($logoFile instanceof UploadedFile) {
                $profil->setLogo(
                    $this->storeUploadedFile($logoFile, 'uploads/entreprises/logos', $slugger)
                );
            }

            $coverFile = $form->get('photoCouverture')->getData();
            if ($coverFile instanceof UploadedFile) {
                $profil->setPhotoCouverture(
                    $this->storeUploadedFile($coverFile, 'uploads/entreprises/couvertures', $slugger)
                );
            }

            $entityManager->flush();

            $this->addFlash('success', 'Votre profil entreprise a été mis à jour avec succès.');

            return $this->redirectToRoute('app_profil_entreprise');
        }

        return $this->render('entreprise/profil_edit.html.twig', [
            'form' => $form,
            'profil' => $profil,
        ]);
    }

    // ── Helpers privés ──────────────────────────────────────────────

    private function getProfilEntrepriseOrThrow(): ProfilEntreprise
    {
        /** @var User $user */
        $user = $this->getUser();
        $profil = $user->getProfilEntreprise();

        if (!$profil) {
            throw $this->createNotFoundException('Profil entreprise introuvable.');
        }

        return $profil;
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