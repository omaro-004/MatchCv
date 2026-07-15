<?php

namespace App\Controller;

use App\Entity\Offre;
use App\Repository\OffreRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * AdminOffreController
 *
 * Superpouvoir Admin — Modération des offres (F-A02/F-A03) :
 *   - Vue globale de TOUTES les offres, toutes entreprises confondues
 *   - Suppression forcée avec motif obligatoire (fraude, offre bidon,
 *     violation salariale...) — notifie l'entreprise concernée
 *   - Suppression en cascade des candidatures liées (cohérence RM-O05,
 *     assurée nativement par le cascade Doctrine sur Offre::candidatures)
 */
#[IsGranted('ROLE_ADMIN')]
class AdminOffreController extends AbstractController
{
    #[Route('/admin/offres', name: 'app_admin_offres_liste', methods: ['GET'])]
    public function liste(Request $request, OffreRepository $offreRepository): Response
    {
        $filters = [
            'statut' => (string) $request->query->get('statut', ''),
            'q' => trim((string) $request->query->get('q', '')),
        ];

        return $this->render('admin/offres/liste.html.twig', [
            'offres' => $offreRepository->findAllForAdmin($filters),
            'filters' => $filters,
        ]);
    }

    #[Route('/admin/offres/{id}/supprimer', name: 'app_admin_offre_supprimer', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function supprimer(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        NotificationService $notificationService
    ): Response {
        $offre = $em->getRepository(Offre::class)->find($id);
        if (!$offre) {
            throw $this->createNotFoundException('Offre introuvable.');
        }

        if (!$this->isCsrfTokenValid('admin_offre_supprimer', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Session invalide, veuillez réessayer.');
            return $this->redirectToRoute('app_admin_offres_liste');
        }

        $motif = trim((string) $request->request->get('motif', ''));
        if ($motif === '') {
            $this->addFlash('error', 'Un motif de suppression est obligatoire (fraude, violation, offre abusive...).');
            return $this->redirectToRoute('app_admin_offres_liste');
        }

        $titre = $offre->getTitre();
        $destinataire = $offre->getEntreprise()?->getUser();

        // RM-O05 : suppression en cascade des candidatures (géré par Doctrine
        // via cascade:['remove'], orphanRemoval:true sur Offre::candidatures)
        $em->remove($offre);
        $em->flush();

        if ($destinataire !== null) {
            $notificationService->notifierOffreSupprimeeParAdmin($destinataire, $titre, $motif);
        }

        $this->addFlash('success', 'L\'offre « ' . $titre . ' » a été supprimée avec succès.');
        return $this->redirectToRoute('app_admin_offres_liste');
    }
}