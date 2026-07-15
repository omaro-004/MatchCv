<?php

namespace App\Controller;

use App\Entity\ProfilCandidat;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\Provider\GithubClient;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GithubResourceOwner;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * OAuthController
 *
 * Gère la connexion / inscription automatique des CANDIDATS via
 * GitHub et LinkedIn (règle RM-U05 : OAuth réservé aux candidats).
 *
 * ────────────────────────────────────────────────────────────────
 * IMPORTANT — À propos du "toujours revoir l'écran d'autorisation"
 * ────────────────────────────────────────────────────────────────
 * GitHub et LinkedIn ne fournissent PAS de paramètre `prompt=login`
 * fiable comme le fait Google. Une fois que l'utilisateur a autorisé
 * votre application une première fois sur le fournisseur, celui-ci
 * redirige automatiquement sans réafficher l'écran de consentement
 * lors des connexions suivantes. Ce comportement est géré côté
 * fournisseur (session/cookie GitHub ou LinkedIn) et NE PEUT PAS
 * être forcé de façon fiable depuis Symfony. Nous ajoutons tout de
 * même les paramètres "best effort" ci-dessous (sans garantie sur
 * LinkedIn/GitHub), mais la vraie garantie métier que vous demandez
 * (ne jamais sauter l'étape de complétion pour un NOUVEAU compte)
 * est assurée par la logique applicative dans finalizeOAuthLogin().
 *
 * Flux :
 *   1. GET /connexion/{provider}        -> redirige vers le fournisseur
 *   2. GET /connexion/{provider}/check  -> callback :
 *        - retrouve/crée le User + ProfilCandidat
 *        - si le compte est VRAIMENT nouveau (inscription_status !=
 *          'complete') : stocke l'ID en session et redirige vers la
 *          page "compléter mon profil" SANS authentifier Symfony
 *        - si le compte est déjà complet (nouveau OU ancien) :
 *          authentifie directement et redirige vers le dashboard
 *          (ou Face ID si activé).
 *
 * Ces routes sont déjà PUBLIC_ACCESS et déjà exemptées du contrôle
 * Face ID grâce au préfixe existant "^/connexion" — aucune
 * modification de security.yaml ni de FaceIdListener nécessaire.
 */
class OAuthController extends AbstractController
{
    // ================================================================
    //  GITHUB
    // ================================================================

    #[Route('/connexion/github', name: 'app_oauth_github_start', methods: ['GET'])]
    public function connectGithub(ClientRegistry $clientRegistry): RedirectResponse
    {
        return $clientRegistry
            ->getClient('github_candidat')
            ->redirect(
                ['read:user', 'user:email'],
                // "Best effort" : GitHub ignore ce paramètre pour forcer une
                // réauthentification, mais `prompt=select_account` force bien
                // l'écran de choix de compte quand GitHub le prend en charge.
                ['allow_signup' => 'true', 'prompt' => 'select_account']
            );
    }

    #[Route('/connexion/github/check', name: 'app_oauth_github_check', methods: ['GET'])]
    public function checkGithub(
        Request $request,
        ClientRegistry $clientRegistry,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        HttpClientInterface $httpClient,
        Security $security
    ): RedirectResponse {
        /** @var GithubClient $client */
        $client = $clientRegistry->getClient('github_candidat');

        try {
            $accessToken = $client->getAccessToken();
            /** @var GithubResourceOwner $githubUser */
            $githubUser = $client->fetchUserFromToken($accessToken);
        } catch (IdentityProviderException|\Throwable $e) {
            $this->addFlash('error', 'La connexion avec GitHub a échoué. Veuillez réessayer.');
            return $this->redirectToRoute('app_login');
        }

        $email = $githubUser->getEmail();
        if (!$email) {
            $email = $this->fetchGithubPrimaryEmail($httpClient, $accessToken->getToken());
        }

        if (!$email) {
            $this->addFlash('error', "Impossible de récupérer votre adresse email depuis GitHub. Rendez votre email public sur GitHub ou utilisez l'inscription classique.");
            return $this->redirectToRoute('app_login');
        }

        $fullName = $githubUser->getName() ?: ($githubUser->getNickname() ?: 'Candidat GitHub');

        [$user, $isNewAccount] = $this->findOrCreateOAuthUser(
            $em,
            $passwordHasher,
            provider: 'github',
            oauthId: (string) $githubUser->getId(),
            email: $email,
            fullName: $fullName
        );

        return $this->finalizeOAuthLogin($request, $security, $user, $isNewAccount);
    }

    private function fetchGithubPrimaryEmail(HttpClientInterface $httpClient, string $accessToken): ?string
    {
        try {
            $response = $httpClient->request('GET', 'https://api.github.com/user/emails', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/vnd.github+json',
                    'User-Agent' => 'MatchCV-App',
                ],
            ]);
            $emails = $response->toArray(false);
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($emails)) {
            return null;
        }

        foreach ($emails as $entry) {
            if (!empty($entry['primary']) && !empty($entry['verified'])) {
                return $entry['email'];
            }
        }

        return $emails[0]['email'] ?? null;
    }

    // ================================================================
    //  LINKEDIN (Sign In with LinkedIn using OpenID Connect)
    // ================================================================

    #[Route('/connexion/linkedin', name: 'app_oauth_linkedin_start', methods: ['GET'])]
    public function connectLinkedin(ClientRegistry $clientRegistry): RedirectResponse
    {
        return $clientRegistry
            ->getClient('linkedin_candidat')
            ->redirect(
                ['openid', 'profile', 'email'],
                // LinkedIn peut réutiliser une session/grant existant et sauter
                // l'écran de consentement. `prompt=login` force, au minimum, une
                // nouvelle authentification au niveau du provider.
                ['prompt' => 'login']
            );
    }

    #[Route('/connexion/linkedin/check', name: 'app_oauth_linkedin_check', methods: ['GET'])]
    public function checkLinkedin(
        Request $request,
        ClientRegistry $clientRegistry,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        Security $security
    ): RedirectResponse {
        $client = $clientRegistry->getClient('linkedin_candidat');

        try {
            $accessToken = $client->getAccessToken();
            $linkedinUser = $client->fetchUserFromToken($accessToken);
            $data = $linkedinUser->toArray();
        } catch (IdentityProviderException|\Throwable $e) {
            $this->addFlash('error', 'La connexion avec LinkedIn a échoué. Veuillez réessayer.');
            return $this->redirectToRoute('app_login');
        }

        $email = $data['email'] ?? null;
        $linkedinId = $data['sub'] ?? null;

        if (!$email || !$linkedinId) {
            $this->addFlash('error', "Impossible de récupérer vos informations depuis LinkedIn. Vérifiez que l'accès à l'email est autorisé.");
            return $this->redirectToRoute('app_login');
        }

        $fullName = trim(($data['given_name'] ?? '') . ' ' . ($data['family_name'] ?? ''));
        if ($fullName === '') {
            $fullName = $data['name'] ?? 'Candidat LinkedIn';
        }

        [$user, $isNewAccount] = $this->findOrCreateOAuthUser(
            $em,
            $passwordHasher,
            provider: 'linkedin',
            oauthId: (string) $linkedinId,
            email: $email,
            fullName: $fullName
        );

        return $this->finalizeOAuthLogin($request, $security, $user, $isNewAccount);
    }

    // ================================================================
    //  LOGIQUE COMMUNE
    // ================================================================

    /**
     * Décide, une fois le User trouvé/créé, si on peut authentifier
     * directement (profil complet — que ce soit un compte ancien ou
     * un compte OAuth déjà complété auparavant) ou s'il faut d'abord
     * passer par l'étape "compléter mon profil".
     *
     * Règle stricte demandée : SEUL un compte VRAIMENT nouveau
     * (inscription_status !== 'complete', donc jamais complété) doit
     * voir la page de complétion. Un compte déjà existant/complété —
     * même s'il vient d'être lié à GitHub/LinkedIn pour la première
     * fois via findOrCreateOAuthUser (cas "existingByEmail" complet)
     * — passe directement au dashboard.
     */
    private function finalizeOAuthLogin(Request $request, Security $security, ?User $user, bool $isNewAccount): RedirectResponse
    {
        if ($user === null) {
            $this->addFlash('error', 'Cette adresse email est déjà associée à un compte non-candidat. Connectez-vous avec votre email et mot de passe.');
            return $this->redirectToRoute('app_login');
        }
        if ($user->isSuspendu()) {
            $motif = $user->getMotifSuspension();
            $this->addFlash('error', $motif
                ? 'Votre compte a été suspendu : ' . $motif
                : 'Votre compte a été suspendu par un administrateur.');
            return $this->redirectToRoute('app_login');
        }

        // ── Nettoyage systématique de toute session résiduelle ──────
        // Évite qu'une ancienne clé de session (d'un flow interrompu
        // précédemment) ne vienne fausser la logique.
        $request->getSession()->remove('oauth_registration_user_id');

        if (!$user->isInscriptionComplete()) {
            // Compte incomplet : soit tout juste créé (nouveau), soit un
            // compte OAuth précédemment créé mais jamais finalisé (l'utilisateur
            // avait quitté avant de soumettre le formulaire de complétion).
            // Dans les deux cas, on NE connecte PAS encore Symfony.
            $request->getSession()->set('oauth_registration_user_id', $user->getId());
            return $this->redirectToRoute('app_complete_profile');
        }

        // Profil complet (compte ancien OU nouveau déjà complété) →
        // authentification réelle immédiate.
        $security->login($user, 'form_login', 'main');

        if ($user->isFaceIdEnabled() && $user->hasFaceDescriptor()) {
            $request->getSession()->remove('face_id_verified');
            $request->getSession()->remove('face_id_verified_at');
            return $this->redirectToRoute('app_face_id_verify');
        }

        return $this->redirectToRoute('app_dashboard_redirect');
    }

    /**
     * Retourne [User, bool $isNewAccount].
     *
     * $isNewAccount = true UNIQUEMENT si le User vient d'être créé lors
     * de cet appel (aucune trace préalable, ni par provider+oauthId, ni
     * par email). C'est cette information qui garantit — en plus du
     * statut 'inscription_status' — que la page de complétion n'est
     * proposée qu'aux comptes réellement neufs.
     *
     * Retourne [null, false] si l'email est déjà utilisé par un compte
     * non-candidat (entreprise/admin).
     */
    private function findOrCreateOAuthUser(
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        string $provider,
        string $oauthId,
        string $email,
        string $fullName
    ): array {
        // 1. Compte déjà lié à ce provider + cet ID (retour, éventuellement
        //    encore en attente de complétion de profil s'il n'avait jamais
        //    terminé son inscription).
        $existing = $em->getRepository(User::class)->findOneBy([
            'oauthProvider' => $provider,
            'oauthId' => $oauthId,
        ]);
        if ($existing) {
            return [$existing, false];
        }

        // 2. Compte existant avec cet email (inscription classique ou
        //    autre provider) → on lie ce provider si c'est un candidat.
        //    Ce n'est PAS un nouveau compte : on ne touche jamais à
        //    inscription_status ici.
        $existingByEmail = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingByEmail) {
            if (!$existingByEmail->isCandidat()) {
                return [null, false];
            }
            $existingByEmail->setOauthProvider($provider);
            $existingByEmail->setOauthId($oauthId);
            $em->flush();
            return [$existingByEmail, false];
        }

        // 3. Aucun compte trouvé → création (User + ProfilCandidat),
        //    en attente de complétion de profil (CV notamment).
        //    C'est le SEUL cas où $isNewAccount = true.
        $user = new User();
        $user->setEmail($email);
        $user->setRole('candidat');
        $user->setPassword($passwordHasher->hashPassword($user, bin2hex(random_bytes(32))));
        $user->setOauthProvider($provider);
        $user->setOauthId($oauthId);
        $user->setInscriptionStatus('pending_profile_completion');

        $profil = new ProfilCandidat();
        $profil->setNomComplet($fullName !== '' ? $fullName : 'Candidat');
        $profil->setUser($user);

        $em->persist($user);
        $em->persist($profil);
        $em->flush();

        return [$user, true];
    }
}