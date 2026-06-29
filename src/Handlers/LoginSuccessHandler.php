<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * LoginSuccessHandler
 *
 * Intercepte chaque authentification réussie (email/password) et décide
 * où rediriger l'utilisateur :
 *
 *  1. Si le candidat a Face ID activé → /face-id/verify
 *     (la session 'face_id_verified' n'est PAS encore mise à true)
 *
 *  2. Sinon → /dashboard/redirect (le UserController dispatche ensuite
 *     vers le bon dashboard selon le rôle)
 *
 * Ce handler est déclaré dans security.yaml sous :
 *   firewalls > main > form_login > success_handler
 *
 * Il remplace le comportement par défaut de Symfony qui redirige
 * vers la page de succès ou la page précédente.
 */
class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private readonly RouterInterface $router
    ) {}

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): RedirectResponse
    {
        /** @var User|null $user */
        $user = $token->getUser();

        if ($user instanceof User
            && $user->isCandidat()
            && $user->isFaceIdEnabled()
            && $user->hasFaceDescriptor()
        ) {
            // Réinitialiser la marque de vérification faciale à chaque login
            // pour forcer une nouvelle vérification (même si une ancienne session
            // avait été validée, elle ne doit pas persister entre deux logins).
            $request->getSession()->remove('face_id_verified');
            $request->getSession()->remove('face_id_verified_at');

            return new RedirectResponse($this->router->generate('app_face_id_verify'));
        }

        // Flux normal → le UserController dispatche vers le bon dashboard
        return new RedirectResponse($this->router->generate('app_dashboard_redirect'));
    }
}
