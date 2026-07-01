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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * OAuthController
 *
 * Gère la connexion / inscription automatique des CANDIDATS via
 * GitHub et LinkedIn (règle RM-U05 : OAuth réservé aux candidats).
 *
 * Flux :
 *   1. GET /connexion/{provider}        -> redirige vers le fournisseur
 *   2. GET /connexion/{provider}/check  -> callback : récupère le profil,
 *      crée ou retrouve le User + ProfilCandidat, connecte l'utilisateur,
 *      puis redirige vers /dashboard/redirect.
 *
 * Ces routes sont déjà PUBLIC_ACCESS et déjà exemptées du contrôle
 * Face ID grâce au préfixe existant "^/connexion" (security.yaml et
 * FaceIdListener::EXEMPT_PREFIXES) — aucune modification requise ailleurs.
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
            ->redirect(['read:user', 'user:email']);
    }

    #[Route('/connexion/github/check', name: 'app_oauth_github_check', methods: ['GET'])]
    public function checkGithub(
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

        // GitHub ne renvoie pas toujours l'email si le profil est privé :
        // on interroge alors explicitement l'API des emails du compte.
        if (!$email) {
            $email = $this->fetchGithubPrimaryEmail($httpClient, $accessToken->getToken());
        }

        if (!$email) {
            $this->addFlash('error', "Impossible de récupérer votre adresse email depuis GitHub. Rendez votre email public sur GitHub ou utilisez l'inscription classique.");
            return $this->redirectToRoute('app_login');
        }

        $fullName = $githubUser->getName() ?: ($githubUser->getNickname() ?: 'Candidat GitHub');

        $user = $this->findOrCreateOAuthUser(
            $em,
            $passwordHasher,
            provider: 'github',
            oauthId: (string) $githubUser->getId(),
            email: $email,
            fullName: $fullName
        );

        if ($user === null) {
            $this->addFlash('error', 'Cette adresse email est déjà associée à un compte non-candidat. Connectez-vous avec votre email et mot de passe.');
            return $this->redirectToRoute('app_login');
        }

        $security->login($user, 'form_login', 'main');

        return $this->redirectToRoute('app_dashboard_redirect');
    }

    /**
     * Appelle l'API GitHub /user/emails pour trouver l'email principal
     * vérifié, quand le profil public ne l'expose pas.
     */
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
            ->redirect(['openid', 'profile', 'email']);
    }

    #[Route('/connexion/linkedin/check', name: 'app_oauth_linkedin_check', methods: ['GET'])]
    public function checkLinkedin(
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

        $user = $this->findOrCreateOAuthUser(
            $em,
            $passwordHasher,
            provider: 'linkedin',
            oauthId: (string) $linkedinId,
            email: $email,
            fullName: $fullName
        );

        if ($user === null) {
            $this->addFlash('error', 'Cette adresse email est déjà associée à un compte non-candidat. Connectez-vous avec votre email et mot de passe.');
            return $this->redirectToRoute('app_login');
        }

        $security->login($user, 'form_login', 'main');

        return $this->redirectToRoute('app_dashboard_redirect');
    }

    // ================================================================
    //  LOGIQUE COMMUNE : recherche ou création du compte candidat
    // ================================================================

    /**
     * Retourne le User correspondant à ce provider/oauthId, en le créant
     * (User + ProfilCandidat) si nécessaire. Retourne null si l'email
     * est déjà utilisé par un compte non-candidat (entreprise/admin).
     */
    private function findOrCreateOAuthUser(
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        string $provider,
        string $oauthId,
        string $email,
        string $fullName
    ): ?User {
        // 1. Un compte est déjà lié à ce provider + cet ID → connexion normale.
        $existing = $em->getRepository(User::class)->findOneBy([
            'oauthProvider' => $provider,
            'oauthId' => $oauthId,
        ]);
        if ($existing) {
            return $existing;
        }

        // 2. Un compte existe déjà avec cet email (inscription classique
        //    ou autre provider) → on lie ce provider si c'est un candidat.
        $existingByEmail = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingByEmail) {
            if (!$existingByEmail->isCandidat()) {
                return null;
            }
            $existingByEmail->setOauthProvider($provider);
            $existingByEmail->setOauthId($oauthId);
            $em->flush();
            return $existingByEmail;
        }

        // 3. Aucun compte trouvé → création complète (User + ProfilCandidat).
        $user = new User();
        $user->setEmail($email);
        $user->setRole('candidat');
        // Mot de passe aléatoire haché : ce compte ne se connecte QUE via OAuth.
        $user->setPassword($passwordHasher->hashPassword($user, bin2hex(random_bytes(32))));
        $user->setOauthProvider($provider);
        $user->setOauthId($oauthId);
        // Le flux OAuth court-circuite l'étape CV / Face ID de l'inscription
        // classique (le candidat pourra compléter son profil plus tard).
        $user->setInscriptionStatus('complete');

        $profil = new ProfilCandidat();
        $profil->setNomComplet($fullName !== '' ? $fullName : 'Candidat');
        $profil->setUser($user);

        $em->persist($user);
        $em->persist($profil);
        $em->flush();

        return $user;
    }
}