<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * UserChecker
 *
 * Vérifié automatiquement par Symfony Security à CHAQUE authentification
 * classique (email/mot de passe), avant ET après la vérification du mot
 * de passe. Bloque toute connexion pour un compte suspendu par un Admin.
 *
 * IMPORTANT : ce checker n'est PAS invoqué automatiquement lors d'un appel
 * manuel à Security::login() (flux OAuth). OAuthController et
 * ProfileCompletionController effectuent donc une vérification explicite
 * de User::isSuspendu() avant d'appeler $security->login().
 *
 * Déclaré dans security.yaml sous : firewalls > main > user_checker
 */
class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if ($user->isSuspendu()) {
            $motif = $user->getMotifSuspension();
            $message = $motif
                ? 'Votre compte a été suspendu : ' . $motif
                : 'Votre compte a été suspendu par un administrateur.';

            throw new CustomUserMessageAccountStatusException($message);
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // Double vérification post-authentification (défense en profondeur) :
        // si le compte a été suspendu entre le checkPreAuth et la validation
        // du mot de passe (cas rare mais possible en cas de forte concurrence).
        $this->checkPreAuth($user);
    }
}