<?php

namespace App\Controller;

use App\Entity\AvisEntreprise;
use App\Entity\Candidature;
use App\Entity\Offre;
use App\Entity\ProfilCandidat;
use App\Entity\ProfilEntreprise;
use App\Entity\User;
use App\Form\CandidatureType;
use App\Repository\AvisEntrepriseRepository;
use App\Repository\CandidatureRepository;
use App\Repository\OffreRepository;
use App\Service\MatchingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * OffreCandidatController
 *
 * Navigation des offres côté candidat (onglet "Offres") : liste avec filtres,
 * page détail (offre + entreprise + avis + carte), dépôt de candidature et
 * dépôt d'un avis sur l'entreprise.
 *
 * Toutes les routes sont sous /candidat, déjà couvertes par security.yaml.
 */
#[IsGranted('ROLE_CANDIDAT')]
class OffreCandidatController extends AbstractController
{
    #[Route('/candidat/offres', name: 'app_candidat_offres_liste', methods: ['GET'])]
    public function liste(Request $request, OffreRepository $offreRepository, CandidatureRepository $candidatureRepository): Response
    {
        $profil = $this->getProfilCandidatOrThrow();

        $filters = [
            'q' => trim((string) $request->query->get('q', '')),
            'typeContrat' => (string) $request->query->get('type', ''),
            'modeTravail' => (string) $request->query->get('mode', ''),
            'localisation' => trim((string) $request->query->get('lieu', '')),
        ];

        $offres = $offreRepository->findAllActive($filters);
        $offresCandidateesIds = $candidatureRepository->findOffreIdsCandidatees($profil);

        return $this->render('candidat/offres/liste.html.twig', [
            'offres' => $offres,
            'filters' => $filters,
            'offres_candidatees_ids' => $offresCandidateesIds,
        ]);
    }

    #[Route('/candidat/offres/{id}', name: 'app_candidat_offre_detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function detail(
        int $id,
        EntityManagerInterface $em,
        AvisEntrepriseRepository $avisRepository,
        CandidatureRepository $candidatureRepository
    ): Response {
        $profil = $this->getProfilCandidatOrThrow();

        $offre = $em->getRepository(Offre::class)->find($id);
        if (!$offre || !$offre->isActive()) {
            throw $this->createNotFoundException("Cette offre n'est plus disponible.");
        }

        $entreprise = $offre->getEntreprise();

        return $this->render('candidat/offres/detail.html.twig', [
            'offre' => $offre,
            'entreprise' => $entreprise,
            'avis' => $avisRepository->findByEntreprise($entreprise),
            'note_moyenne' => $avisRepository->averageNoteForEntreprise($entreprise),
            'nombre_avis' => $avisRepository->countForEntreprise($entreprise),
            'mon_avis' => $avisRepository->findOneByEntrepriseAndCandidat($entreprise, $profil),
            'deja_candidate' => $candidatureRepository->findOneByOffreAndCandidat($offre, $profil) !== null,
            'profil' => $profil,
        ]);
    }

    #[Route('/candidat/offres/{id}/postuler', name: 'app_candidat_offre_postuler', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function postuler(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        CandidatureRepository $candidatureRepository,
        MatchingService $matchingService,
        SluggerInterface $slugger
    ): Response {
        $profil = $this->getProfilCandidatOrThrow();

        $offre = $em->getRepository(Offre::class)->find($id);
        if (!$offre) {
            throw $this->createNotFoundException('Offre introuvable.');
        }

        // RM-C02 : seule une offre active accepte des candidatures
        if (!$offre->isActive()) {
            $this->addFlash('error', "Cette offre n'accepte plus de candidatures.");
            return $this->redirectToRoute('app_candidat_offres_liste');
        }

        // RM-U06 : CV obligatoire sur le profil pour pouvoir postuler
        if (!$profil->hasCv()) {
            $this->addFlash('error', 'Vous devez ajouter un CV à votre profil avant de postuler.');
            return $this->redirectToRoute('app_profil_candidat');
        }

        // RM-C01 : unicité de la candidature
        if ($candidatureRepository->findOneByOffreAndCandidat($offre, $profil) !== null) {
            $this->addFlash('info', 'Vous avez déjà postulé à cette offre.');
            return $this->redirectToRoute('app_candidat_candidatures_liste');
        }

        $candidature = new Candidature();
        $candidature->setOffre($offre);
        $candidature->setCandidat($profil);
        $candidature->setStatut(Candidature::STATUT_EN_ATTENTE);
        // Par défaut on réutilise le CV du profil (RM-M04 autorise un CV propre à la candidature)
        $candidature->setCv($profil->getCv());

        $form = $this->createForm(CandidatureType::class, $candidature);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $cvFile = $form->get('cv')->getData();
            if ($cvFile instanceof UploadedFile) {
                $cvRelativePath = $this->storeUploadedFile($cvFile, 'uploads/candidatures/cv', $slugger);
                $candidature->setCv($cvRelativePath);
            }

            $em->persist($candidature);
            $em->flush();

            // RM-C03 : calcul du score IA de façon synchrone au dépôt de la candidature
            $result = $matchingService->computeScore($profil, $offre);
            $candidature->setScoreMatching($result['score'] !== null ? (float) $result['score'] : null);
            $candidature->setCompetencesMatcheesArray($result['competences_matchees']);
            $candidature->setCompetencesManquantesArray($result['competences_manquantes']);
            $em->flush();

            $this->addFlash('success', 'Votre candidature a été envoyée avec succès !');
            return $this->redirectToRoute('app_candidat_candidatures_liste');
        }

        return $this->render('candidat/offres/postuler.html.twig', [
            'form' => $form,
            'offre' => $offre,
        ]);
    }

    #[Route('/candidat/entreprises/{id}/avis', name: 'app_candidat_entreprise_avis', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function laisserAvis(int $id, Request $request, EntityManagerInterface $em, AvisEntrepriseRepository $avisRepository): Response
    {
        $profil = $this->getProfilCandidatOrThrow();

        $entreprise = $em->getRepository(ProfilEntreprise::class)->find($id);
        if (!$entreprise) {
            throw $this->createNotFoundException('Entreprise introuvable.');
        }

        if (!$this->isCsrfTokenValid('laisser_avis', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Session invalide, veuillez réessayer.');
            return $this->redirectBack($request);
        }

        $note = (int) $request->request->get('note', 0);
        $commentaire = trim((string) $request->request->get('commentaire', ''));

        if ($note < 1 || $note > 5) {
            $this->addFlash('error', 'Veuillez donner une note entre 1 et 5.');
            return $this->redirectBack($request);
        }

        $avis = $avisRepository->findOneByEntrepriseAndCandidat($entreprise, $profil);
        if (!$avis) {
            $avis = new AvisEntreprise();
            $avis->setEntreprise($entreprise);
            $avis->setCandidat($profil);
        }

        $avis->setNote($note);
        $avis->setCommentaire($commentaire !== '' ? $commentaire : null);

        $em->persist($avis);
        $em->flush();

        $this->addFlash('success', 'Merci pour votre avis !');
        return $this->redirectBack($request);
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function getProfilCandidatOrThrow(): ProfilCandidat
    {
        /** @var User $user */
        $user = $this->getUser();
        $profil = $user->getProfilCandidat();

        if (!$profil) {
            throw $this->createNotFoundException('Profil candidat introuvable.');
        }

        return $profil;
    }

    private function redirectBack(Request $request): Response
    {
        $referer = $request->headers->get('referer');
        return $referer ? $this->redirect($referer) : $this->redirectToRoute('app_candidat_offres_liste');
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