<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * FaceIdListener (Event Subscriber)
 *
 * Vérifie, à chaque requête HTTP, que les utilisateurs candidats
 * ayant activé le Face ID ont bien passé la vérification faciale
 * avant d'accéder à leurs routes protégées.
 *
 * Logique :
 *   - Si l'utilisateur est un candidat avec Face ID activé
 *   - ET que la session ne contient pas 'face_id_verified' = true
 *   - ET que la route courante n'est PAS une route exemptée
 *   → Redirige vers /face-id/verify
 *
 * Routes exemptées (whitelist) :
 *   - /face-id/*         (la page de vérification elle-même)
 *   - /connexion         (login)
 *   - /deconnexion       (logout)
 *   - /inscription/*     (inscription)
 *   - /_profiler/*       (Symfony profiler dev)
 *   - /_wdt/*            (Symfony web debug toolbar)
 *
 * Déclaration dans services.yaml :
 *   App\Security\FaceIdListener:
 *     tags:
 *       - { name: kernel.event_subscriber }
 */
class FaceIdListener implements EventSubscriberInterface
{
    /**
     * Routes dont le PRÉFIXE est exempté du contrôle Face ID.
     * Utiliser des préfixes (startsWith) plutôt que des noms de routes
     * pour rester robuste aux ajouts de routes futures.
     */
    private const EXEMPT_PREFIXES = [
        '/face-id',
        '/connexion',
        '/deconnexion',
        '/inscription',
        '/_profiler',
        '/_wdt',
        '/favicon.ico',
    ];

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly RouterInterface $router
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            // Priorité 10 pour s'exécuter après le pare-feu Symfony (priorité 8)
            // mais avant les contrôleurs (priorité 0)
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // Ignorer les sous-requêtes (forward interne Symfony)
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path    = $request->getPathInfo();

        // ── 1. Vérifier si la route est exemptée ─────────────────────────────
        foreach (self::EXEMPT_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return;
            }
        }

        // ── 2. Récupérer le token de sécurité courant ─────────────────────────
        $token = $this->tokenStorage->getToken();

        if ($token === null) {
            return; // Pas connecté → Symfony gère la redirection login normalement
        }

        $user = $token->getUser();

        if (!$user instanceof User) {
            return;
        }

        // ── 3. Contrôle Face ID uniquement pour les candidats concernés ────────
        if (!$user->isCandidat()
            || !$user->isFaceIdEnabled()
            || !$user->hasFaceDescriptor()
        ) {
            return; // Entreprise, Admin, ou candidat sans Face ID → pas de contrôle
        }

        // ── 4. Vérifier si la vérification faciale a été effectuée ────────────
        $session   = $request->getSession();
        $verified  = (bool) $session->get('face_id_verified', false);

        if ($verified) {
            // Optionnel : vérifier que la validation n'est pas trop ancienne (1h max)
            $verifiedAt = (int) $session->get('face_id_verified_at', 0);
            if (time() - $verifiedAt > 3600) {
                // Session Face ID expirée → forcer une nouvelle vérification
                $session->remove('face_id_verified');
                $session->remove('face_id_verified_at');
                $verified = false;
            }
        }

        if (!$verified) {
            // Bloquer l'accès et rediriger vers la page de vérification faciale
            $event->setResponse(
                new RedirectResponse($this->router->generate('app_face_id_verify'))
            );
        }
    }
}
