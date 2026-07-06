<?php

namespace App\Controller;

use App\Entity\PasswordResetToken;
use App\Entity\User;
use App\Repository\PasswordResetTokenRepository;
use App\Repository\UserRepository;
use App\Security\TotpService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PasswordController extends AbstractController
{
    // ================================================================
    //  ÉTAPE 1 — Identification du compte
    // ================================================================

    #[Route('/mot-de-passe-oublie', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(Request $request, UserRepository $userRepository): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('forgot_password', (string) $request->request->get('_csrf_token'))) {
                $this->addFlash('error', 'Session invalide, veuillez réessayer.');
                return $this->redirectToRoute('app_forgot_password');
            }

            $identifiant = trim((string) $request->request->get('identifiant'));

            if ($identifiant === '') {
                $this->addFlash('error', 'Veuillez saisir votre email, nom ou numéro de téléphone.');
                return $this->redirectToRoute('app_forgot_password');
            }

            $user = $userRepository->findOneByIdentifier($identifiant);

            if (!$user) {
                $this->addFlash('error', 'Aucun compte ne correspond à ces informations.');
                return $this->redirectToRoute('app_forgot_password');
            }

            // On stocke uniquement l'ID en session, le temps du parcours de récupération.
            $request->getSession()->set('password_reset_candidate_user_id', $user->getId());

            return $this->redirectToRoute('app_password_reset_method');
        }

        return $this->render('forgot_password.html.twig');
    }

    // ================================================================
    //  ÉTAPE 2 — Choix de la méthode
    // ================================================================

    #[Route('/mot-de-passe-oublie/methode', name: 'app_password_reset_method', methods: ['GET'])]
    public function chooseMethod(Request $request, EntityManagerInterface $em): Response
    {
        $userId = $request->getSession()->get('password_reset_candidate_user_id');
        $user = $userId ? $em->find(User::class, $userId) : null;

        if (!$user) {
            $this->addFlash('error', 'Veuillez d\'abord identifier votre compte.');
            return $this->redirectToRoute('app_forgot_password');
        }

        return $this->render('password_reset_method.html.twig', [
            'totp_available' => $user->isTotpEnabled(),
            'masked_email'   => $this->maskEmail($user->getEmail()),
        ]);
    }

    // ================================================================
    //  MÉTHODE A — Envoi du lien par email
    // ================================================================

    #[Route('/mot-de-passe-oublie/envoyer-email', name: 'app_password_reset_send_email', methods: ['POST'])]
    public function sendResetEmail(
        Request $request,
        EntityManagerInterface $em,
        MailerInterface $mailer,
        PasswordResetTokenRepository $tokenRepository
    ): Response {
        if (!$this->isCsrfTokenValid('password_reset_send_email', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Session invalide, veuillez réessayer.');
            return $this->redirectToRoute('app_forgot_password');
        }

        $userId = $request->getSession()->get('password_reset_candidate_user_id');
        $user = $userId ? $em->find(User::class, $userId) : null;

        if (!$user) {
            $this->addFlash('error', 'Session expirée, veuillez recommencer.');
            return $this->redirectToRoute('app_forgot_password');
        }

        // Invalide les anciens liens non utilisés avant d'en créer un nouveau.
        $tokenRepository->invalidateAllForUser($user);

        $rawToken = bin2hex(random_bytes(32));

        $resetToken = new PasswordResetToken();
        $resetToken->setUser($user);
        $resetToken->setTokenHash(hash('sha256', $rawToken));
        $resetToken->setRequestedAt(new \DateTimeImmutable());
        $resetToken->setExpiresAt((new \DateTimeImmutable())->modify('+24 hours'));
        $resetToken->setUsed(false);

        $em->persist($resetToken);
        $em->flush();

        $resetUrl = $this->generateUrl(
            'app_reset_password',
            ['token' => $rawToken],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $email = (new TemplatedEmail())
            ->from('no-reply@matchcv.com')
            ->to($user->getEmail())
            ->subject('Réinitialisation de votre mot de passe MatchCV')
            ->htmlTemplate('emails/reset_password.html.twig')
            ->context([
                'resetUrl' => $resetUrl,
                'user'     => $user,
            ]);

        $mailer->send($email);

        $request->getSession()->remove('password_reset_candidate_user_id');

        $this->addFlash('success', 'Un email de réinitialisation a été envoyé. Vérifiez votre boîte de réception (valable 24h).');
        return $this->redirectToRoute('app_login');
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(
        Request $request,
        string $token,
        EntityManagerInterface $em,
        PasswordResetTokenRepository $tokenRepository,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $resetToken = $tokenRepository->findValidByHash(hash('sha256', $token));

        if (!$resetToken) {
            $this->addFlash('error', 'Ce lien de réinitialisation est invalide ou a expiré. Refaites une demande.');
            return $this->redirectToRoute('app_forgot_password');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('reset_password', (string) $request->request->get('_csrf_token'))) {
                $this->addFlash('error', 'Session invalide, veuillez réessayer.');
                return $this->redirectToRoute('app_reset_password', ['token' => $token]);
            }

            $passwords = $request->request->all('password');
            $first  = (string) ($passwords['first'] ?? '');
            $second = (string) ($passwords['second'] ?? '');

            if (strlen($first) < 8) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 8 caractères.');
                return $this->redirectToRoute('app_reset_password', ['token' => $token]);
            }

            if ($first !== $second) {
                $this->addFlash('error', 'Les deux mots de passe ne correspondent pas.');
                return $this->redirectToRoute('app_reset_password', ['token' => $token]);
            }

            $user = $resetToken->getUser();
            $user->setPassword($passwordHasher->hashPassword($user, $first));
            $resetToken->setUsed(true);
            $em->flush();

            $this->addFlash('success', 'Votre mot de passe a été réinitialisé avec succès. Vous pouvez vous connecter.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('reset_password.html.twig', [
            'token' => $token,
        ]);
    }

    // ================================================================
    //  MÉTHODE B — Récupération via application d'authentification (TOTP)
    // ================================================================

    #[Route('/mot-de-passe-oublie/authenticator', name: 'app_password_reset_totp_verify', methods: ['GET', 'POST'])]
    public function verifyTotp(Request $request, EntityManagerInterface $em, TotpService $totpService): Response
    {
        $userId = $request->getSession()->get('password_reset_candidate_user_id');
        $user = $userId ? $em->find(User::class, $userId) : null;

        if (!$user || !$user->isTotpEnabled() || !$user->getTotpSecret()) {
            $this->addFlash('error', 'Cette méthode n\'est pas disponible pour ce compte.');
            return $this->redirectToRoute('app_forgot_password');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('password_reset_totp', (string) $request->request->get('_csrf_token'))) {
                $this->addFlash('error', 'Session invalide, veuillez réessayer.');
                return $this->redirectToRoute('app_password_reset_totp_verify');
            }

            $code = (string) $request->request->get('code');

            if (!$totpService->verify($user->getTotpSecret(), $code)) {
                $this->addFlash('error', 'Code incorrect. Vérifiez votre application d\'authentification et réessayez.');
                return $this->redirectToRoute('app_password_reset_totp_verify');
            }

            // Le code est valide : on marque le compte comme vérifié pour
            // l'étape suivante UNIQUEMENT, puis on nettoie l'ancienne clé.
            $request->getSession()->set('password_reset_totp_verified_user_id', $user->getId());
            $request->getSession()->remove('password_reset_candidate_user_id');

            return $this->redirectToRoute('app_password_reset_totp_new_password');
        }

        return $this->render('password_reset_totp_verify.html.twig');
    }

    #[Route('/mot-de-passe-oublie/authenticator/nouveau-mot-de-passe', name: 'app_password_reset_totp_new_password', methods: ['GET', 'POST'])]
    public function newPasswordViaTotp(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $userId = $request->getSession()->get('password_reset_totp_verified_user_id');
        $user = $userId ? $em->find(User::class, $userId) : null;

        if (!$user) {
            $this->addFlash('error', 'Session expirée, veuillez recommencer.');
            return $this->redirectToRoute('app_forgot_password');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('password_reset_totp_new_password', (string) $request->request->get('_csrf_token'))) {
                $this->addFlash('error', 'Session invalide, veuillez réessayer.');
                return $this->redirectToRoute('app_password_reset_totp_new_password');
            }

            $passwords = $request->request->all('password');
            $first  = (string) ($passwords['first'] ?? '');
            $second = (string) ($passwords['second'] ?? '');

            if (strlen($first) < 8) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 8 caractères.');
                return $this->redirectToRoute('app_password_reset_totp_new_password');
            }

            if ($first !== $second) {
                $this->addFlash('error', 'Les deux mots de passe ne correspondent pas.');
                return $this->redirectToRoute('app_password_reset_totp_new_password');
            }

            $user->setPassword($passwordHasher->hashPassword($user, $first));
            $em->flush();

            $request->getSession()->remove('password_reset_totp_verified_user_id');

            $this->addFlash('success', 'Mot de passe modifié avec succès. Vous pouvez vous connecter.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('password_reset_totp_new_password.html.twig');
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email;
        }

        [$local, $domain] = $parts;
        $visible = mb_substr($local, 0, 2);

        return $visible . str_repeat('*', max(1, mb_strlen($local) - 2)) . '@' . $domain;
    }
}