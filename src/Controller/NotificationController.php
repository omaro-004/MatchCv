<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * NotificationController
 *
 * Commun aux candidats et aux entreprises (pas de préfixe /candidat ou
 * /entreprise) : chaque utilisateur ne voit et ne peut agir que sur ses
 * propres notifications (vérification explicite du destinataire dans
 * lire()). Routes protégées via security.yaml (^/notifications ->
 * IS_AUTHENTICATED_FULLY).
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class NotificationController extends AbstractController
{
    /**
     * Page complète listant toutes les notifications récentes de l'utilisateur.
     */
    #[Route('/notifications', name: 'app_notifications_liste', methods: ['GET'])]
    public function liste(NotificationRepository $notificationRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('notifications/liste.html.twig', [
            'notifications' => $notificationRepository->findAllForUser($user, 50),
        ]);
    }

    /**
     * Endpoint AJAX consommé par le dropdown de la topbar : renvoie les
     * dernières notifications + le compteur de non-lues.
     */
    #[Route('/notifications/panel', name: 'app_notifications_panel', methods: ['GET'])]
    public function panel(NotificationRepository $notificationRepository): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $notifications = $notificationRepository->findRecentForUser($user, 8);

        return $this->json([
            'unread_count' => $notificationRepository->countUnreadForUser($user),
            'notifications' => array_map([$this, 'serialize'], $notifications),
        ]);
    }

    /**
     * Marque une notification comme lue puis renvoie son lien (le JS du
     * client effectue ensuite la redirection).
     */
    #[Route('/notifications/{id}/lire', name: 'app_notifications_lire', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function lire(int $id, Request $request, EntityManagerInterface $em, NotificationRepository $notificationRepository): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('notification_mark_read', $request->headers->get('X-CSRF-Token', ''))) {
            return $this->json(['error' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        $notification = $em->getRepository(Notification::class)->find($id);

        if (!$notification || $notification->getDestinataire()?->getId() !== $user->getId()) {
            return $this->json(['error' => 'Notification introuvable.'], Response::HTTP_NOT_FOUND);
        }

        if (!$notification->isLu()) {
            $notification->setLu(true);
            $em->flush();
        }

        return $this->json([
            'success' => true,
            'lien' => $notification->getLien(),
            'unread_count' => $notificationRepository->countUnreadForUser($user),
        ]);
    }

    /**
     * Marque toutes les notifications de l'utilisateur courant comme lues.
     */
    #[Route('/notifications/lire-tout', name: 'app_notifications_lire_tout', methods: ['POST'])]
    public function lireTout(Request $request, NotificationRepository $notificationRepository): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('notification_mark_all_read', $request->headers->get('X-CSRF-Token', ''))) {
            return $this->json(['error' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        $notificationRepository->markAllAsReadForUser($user);

        return $this->json(['success' => true, 'unread_count' => 0]);
    }

    private function serialize(Notification $notification): array
    {
        return [
            'id' => $notification->getId(),
            'type' => $notification->getType(),
            'icon' => $notification->getTypeIcon(),
            'color' => $notification->getTypeColorClass(),
            'titre' => $notification->getTitre(),
            'message' => $notification->getMessage(),
            'lien' => $notification->getLien(),
            'lu' => $notification->isLu(),
            'date' => $notification->getDateEnvoi()?->format('d/m/Y à H:i'),
        ];
    }
}