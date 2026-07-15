<?php

namespace App\Controller;

use App\Entity\Candidature;
use App\Repository\CandidatureRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * AdminCandidatureController
 *
 * Superpouvoir Admin — Supervision des candidatures (F-A04/F-A05) :
 *   - Vue globale de toutes les candidatures, toutes offres confondues
 *   - Suppression administrative avec motif (règle RM-C06 : l'admin peut
 *     supprimer mais ne modifie JAMAIS le statut d'une candidature)
 */
#[IsGranted('ROLE_ADMIN')]
class AdminCandidatureController extends AbstractController
{
    #[Route('/admin/candidatures', name: 'app_admin_candidatures_liste', methods: ['GET'])]
    public function liste(Request $request, CandidatureRepository $candidatureRepository): Response
    {
        $filters = [
            'statut' => (string) $request->query->get('statut', ''),
            'q' => trim((string) $request->query->get('q', '')),
        ];

        return $this->render('admin/candidatures/liste.html.twig', [
            'candidatures' => $candidatureRepository->findAllForAdmin($filters),
            'filters' => $filters,
        ]);
    }

    #[Route('/admin/candidatures/{id}/supprimer', name: 'app_admin_candidature_supprimer', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function supprimer(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        NotificationService $notificationService
    ): Response {
        $candidature = $em->getRepository(Candidature::class)->find($id);
        if (!$candidature) {
            throw $this->createNotFoundException('Candidature introuvable.');
        }

        if (!$this->isCsrfTokenValid('admin_candidature_supprimer', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Session invalide, veuillez réessayer.');
            return $this->redirectToRoute('app_admin_candidatures_liste');
        }

        $motif = trim((string) $request->request->get('motif', ''));
        if ($motif === '') {
            $this->addFlash('error', 'Un motif de suppression est obligatoire.');
            return $this->redirectToRoute('app_admin_candidatures_liste');
        }

        $titreOffre = $candidature->getOffre()?->getTitre() ?? 'une offre';
        $destinataire = $candidature->getCandidat()?->getUser();

        $em->remove($candidature);
        $em->flush();

        if ($destinataire !== null) {
            $notificationService->notifierCandidatureSupprimeeParAdmin($destinataire, $titreOffre, $motif);
        }

        $this->addFlash('success', 'La candidature a été supprimée avec succès.');
        return $this->redirectToRoute('app_admin_candidatures_liste');
    }
}