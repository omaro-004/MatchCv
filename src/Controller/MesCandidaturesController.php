<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\CandidatureRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * MesCandidaturesController
 *
 * Onglet "Mes candidatures" du dashboard candidat : suivi des candidatures
 * déposées, avec statut en temps réel (RM-C05, RM-C06).
 */
#[IsGranted('ROLE_CANDIDAT')]
class MesCandidaturesController extends AbstractController
{
    #[Route('/candidat/candidatures', name: 'app_candidat_candidatures_liste', methods: ['GET'])]
    public function liste(CandidatureRepository $candidatureRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $profil = $user->getProfilCandidat();

        if (!$profil) {
            throw $this->createNotFoundException('Profil candidat introuvable.');
        }

        $candidatures = $candidatureRepository->findByCandidat($profil);

        return $this->render('candidat/candidatures/liste.html.twig', [
            'candidatures' => $candidatures,
        ]);
    }
}