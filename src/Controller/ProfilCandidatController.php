<?php

namespace App\Controller;

use App\Entity\ProfilCandidat;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * ProfilCandidatController
 *
 * Page "Mon profil" du dashboard candidat.
 * Affiche les données saisies à l'inscription ET les données enrichies
 * automatiquement par l'IA à partir du CV (App\Service\CvAiProfileAnalyzer) :
 * années d'expérience, langues parlées, compétences techniques, formations,
 * expériences professionnelles, résumé IA.
 *
 * Route déjà couverte par security.yaml (préfixe ^/candidat → ROLE_CANDIDAT),
 * aucune modification de security.yaml n'est nécessaire.
 */
class ProfilCandidatController extends AbstractController
{
    #[Route('/candidat/profil', name: 'app_profil_candidat', methods: ['GET'])]
    #[IsGranted('ROLE_CANDIDAT')]
    public function index(EntityManagerInterface $entityManager): Response
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

        return $this->render('candidat/profil.html.twig', [
            'profil' => $profil,
            'user' => $user,
        ]);
    }
}
