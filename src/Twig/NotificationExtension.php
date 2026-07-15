<?php

namespace App\Twig;

use App\Entity\User;
use App\Repository\NotificationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * NotificationExtension
 *
 * Fournit `notification_unread_count()` aux templates, utilisé pour le
 * badge de la cloche dans la topbar et le badge des liens "Notifications"
 * dans les deux sidebars (même pattern que CandidatSidebarExtension).
 */
class NotificationExtension extends AbstractExtension
{
    public function __construct(
        private readonly Security $security,
        private readonly NotificationRepository $notificationRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('notification_unread_count', [$this, 'countUnread']),
        ];
    }

    public function countUnread(): int
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return 0;
        }

        return $this->notificationRepository->countUnreadForUser($user);
    }
}