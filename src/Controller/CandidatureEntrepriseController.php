<?php

namespace App\Controller;

use App\Entity\Candidature;
use App\Entity\Offre;
use App\Entity\ProfilEntreprise;
use App\Entity\User;
use App\Repository\CandidatureRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * CandidatureEntrepriseController
 *
 * Onglet "Candidatures" du dashboard entreprise : consultation des
 * candidatures reçues (triées par score IA — RM-C-relatif à US-E04) et
 * changement de statut (RM-C06 : seul le recruteur propriétaire de l'offre
 * peut modifier le statut d'une candidature).
 *
 * CORRECTIF : le changement de statut (Accepté/Refusé) déclenche désormais
 * NotificationService::notifierChangementStatut(), qui était défini mais
 * jamais appelé — c'est pour cela qu'aucune notification n'était générée
 * pour le candidat (règle RM-C07).
 */
#[IsGranted('ROLE_ENTREPRISE')]
class CandidatureEntrepriseController extends AbstractController
{
    #[Route('/entreprise/candidatures', name: 'app_entreprise_candidatures_liste', methods: ['GET'])]
    public function liste(Request $request, EntityManagerInterface $em, CandidatureRepository $candidatureRepository): Response
    {
        $profil = $this->getProfilEntrepriseOrThrow();

        $offreFiltre = null;
        $offreId = $request->query->get('offre');
        if ($offreId) {
            $offreFiltre = $em->getRepository(Offre::class)->find((int) $offreId);
            if ($offreFiltre && $offreFiltre->getEntreprise()?->getId() !== $profil->getId()) {
                throw $this->createAccessDeniedException("Vous n'êtes pas autorisé à voir cette offre.");
            }
        }

        $candidatures = $candidatureRepository->findByEntreprise($profil, $offreFiltre);

        return $this->render('entreprise/candidatures/liste.html.twig', [
            'candidatures' => $candidatures,
            'offre_filtre' => $offreFiltre,
            'offres' => $profil->getOffres(),
        ]);
    }

    #[Route('/entreprise/candidatures/{id}/statut', name: 'app_entreprise_candidature_statut', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function changerStatut(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        NotificationService $notificationService
    ): Response {
        $profil = $this->getProfilEntrepriseOrThrow();

        $candidature = $em->getRepository(Candidature::class)->find($id);
        if (!$candidature || $candidature->getOffre()?->getEntreprise()?->getId() !== $profil->getId()) {
            throw $this->createNotFoundException('Candidature introuvable.');
        }

        $csrfToken = $request->headers->get('X-CSRF-Token', '');
        if (!$this->isCsrfTokenValid('candidature_statut', $csrfToken)) {
            return $this->json(['error' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        $payload = json_decode($request->getContent(), true);
        $statut = is_array($payload) ? ($payload['statut'] ?? null) : null;

        if (!in_array($statut, [Candidature::STATUT_ACCEPTE, Candidature::STATUT_REFUSE], true)) {
            return $this->json(['error' => 'Statut invalide.'], Response::HTTP_BAD_REQUEST);
        }

        // On ne notifie que si le statut change réellement (évite les doublons
        // si jamais l'action est déclenchée deux fois sur un statut identique).
        $statutAChange = $candidature->getStatut() !== $statut;

        $candidature->setStatut($statut);
        $em->flush();

        // ── CORRECTIF : notification du candidat (règle RM-C07) ──────────
        if ($statutAChange) {
            $notificationService->notifierChangementStatut($candidature);
        }

        return $this->json([
            'success' => true,
            'statut' => $candidature->getStatut(),
            'statut_label' => $candidature->getStatutLabel(),
        ]);
    }

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
}