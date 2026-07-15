<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * AdminUserController
 *
 * Superpouvoir Admin — Gestion globale des utilisateurs (F-A01) :
 *   - Liste complète des candidats et entreprises (avec filtres)
 *   - Suspension d'un compte (violation, fraude...) avec motif obligatoire
 *   - Réactivation d'un compte suspendu
 *
 * Règle : un admin ne peut JAMAIS suspendre un autre admin (protection
 * contre l'auto-verrouillage et les abus de pouvoir).
 */
#[IsGranted('ROLE_ADMIN')]
class AdminUserController extends AbstractController
{
    #[Route('/admin/utilisateurs', name: 'app_admin_utilisateurs_liste', methods: ['GET'])]
    public function liste(Request $request, UserRepository $userRepository): Response
    {
        $filters = [
            'role' => (string) $request->query->get('role', ''),
            'statut' => (string) $request->query->get('statut', ''),
            'q' => trim((string) $request->query->get('q', '')),
        ];

        return $this->render('admin/utilisateurs/liste.html.twig', [
            'utilisateurs' => $userRepository->findAllFiltered($filters),
            'filters' => $filters,
        ]);
    }

    #[Route('/admin/utilisateurs/{id}/suspendre', name: 'app_admin_utilisateur_suspendre', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function suspendre(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        NotificationService $notificationService
    ): Response {
        /** @var User $admin */
        $admin = $this->getUser();

        $user = $em->getRepository(User::class)->find($id);
        if (!$user) {
            throw $this->createNotFoundException('Utilisateur introuvable.');
        }

        if (!$this->isCsrfTokenValid('admin_user_suspendre', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Session invalide, veuillez réessayer.');
            return $this->redirectToRoute('app_admin_utilisateurs_liste');
        }

        if ($user->isAdmin()) {
            $this->addFlash('error', 'Impossible de suspendre un compte administrateur.');
            return $this->redirectToRoute('app_admin_utilisateurs_liste');
        }

        if ($user->getId() === $admin->getId()) {
            $this->addFlash('error', 'Vous ne pouvez pas suspendre votre propre compte.');
            return $this->redirectToRoute('app_admin_utilisateurs_liste');
        }

        $motif = trim((string) $request->request->get('motif', ''));
        if ($motif === '') {
            $this->addFlash('error', 'Un motif de suspension est obligatoire.');
            return $this->redirectToRoute('app_admin_utilisateurs_liste');
        }

        $user->suspendre($motif, $admin->getEmail());
        $em->flush();

        $notificationService->notifierSuspensionCompte($user, $motif);

        $this->addFlash('success', 'Le compte a été suspendu avec succès.');
        return $this->redirectToRoute('app_admin_utilisateurs_liste');
    }

    #[Route('/admin/utilisateurs/{id}/reactiver', name: 'app_admin_utilisateur_reactiver', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function reactiver(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $user = $em->getRepository(User::class)->find($id);
        if (!$user) {
            throw $this->createNotFoundException('Utilisateur introuvable.');
        }

        if (!$this->isCsrfTokenValid('admin_user_reactiver', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Session invalide, veuillez réessayer.');
            return $this->redirectToRoute('app_admin_utilisateurs_liste');
        }

        $user->reactiver();
        $em->flush();

        $this->addFlash('success', 'Le compte a été réactivé avec succès.');
        return $this->redirectToRoute('app_admin_utilisateurs_liste');
    }
}