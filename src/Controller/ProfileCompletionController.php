<?php

namespace App\Controller;

use App\Entity\ProfilCandidat;
use App\Entity\User;
use App\Form\CompleteProfileType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * ProfileCompletionController
 *
 * Étape obligatoire après une PREMIÈRE connexion GitHub/LinkedIn qui a
 * créé un nouveau compte (inscription_status != 'complete') : le
 * candidat doit renseigner téléphone, localisation, type de contrat
 * et surtout uploader son CV (règle RM-U06) avant d'accéder à son
 * dashboard.
 *
 * Tant que ce n'est pas fait, l'utilisateur n'est PAS authentifié
 * auprès de Symfony Security (même logique que l'étape Face ID de
 * InscriptionController : ID en session, pas de token).
 *
 * Un compte déjà complet (ancien compte classique lié à OAuth, ou
 * compte OAuth déjà finalisé auparavant) ne passe JAMAIS par ce
 * contrôleur : OAuthController::finalizeOAuthLogin() l'authentifie
 * directement et redirige vers le dashboard.
 */
class ProfileCompletionController extends AbstractController
{
    #[Route('/inscription/completer-profil', name: 'app_complete_profile', methods: ['GET', 'POST'])]
    public function completeProfile(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        Security $security
    ): Response {
        // Si déjà pleinement connecté, cette étape n'a plus lieu d'être.
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard_redirect');
        }

        $userId = $request->getSession()->get('oauth_registration_user_id');

        if (!$userId) {
            $this->addFlash('error', 'Session expirée. Veuillez vous reconnecter avec GitHub ou LinkedIn.');
            return $this->redirectToRoute('app_login');
        }

        /** @var User|null $user */
        $user = $entityManager->find(User::class, $userId);

        if (!$user || !$user->isCandidat()) {
            $request->getSession()->remove('oauth_registration_user_id');
            return $this->redirectToRoute('app_login');
        }

        // Garde-fou strict : si, pour une raison quelconque, ce compte
        // est déjà marqué 'complete' (double soumission, retour arrière,
        // session périmée pointant vers un ancien compte...), on ne lui
        // fait JAMAIS revoir ce formulaire : on l'authentifie et on
        // l'envoie directement au dashboard.
        if ($user->isInscriptionComplete()) {
            $request->getSession()->remove('oauth_registration_user_id');
            $security->login($user, 'form_login', 'main');
            return $this->redirectToRoute('app_dashboard_redirect');
        }

        $profil = $user->getProfilCandidat();
        if (!$profil) {
            // Garde-fou : ne devrait jamais arriver, le ProfilCandidat
            // est créé en même temps que le User dans OAuthController.
            $profil = new ProfilCandidat();
            $profil->setUser($user);
        }

        $form = $this->createForm(CompleteProfileType::class, $profil);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $cvFile = $form->get('cv')->getData();
            if ($cvFile instanceof UploadedFile) {
                $profil->setCv($this->storeUploadedFile($cvFile, 'uploads/candidats/cv', $slugger));
            }

            $photoFile = $form->get('photo')->getData();
            if ($photoFile instanceof UploadedFile) {
                $profil->setPhoto($this->storeUploadedFile($photoFile, 'uploads/candidats/photos', $slugger));
            }

            // C'est ICI, et seulement ici, que le statut passe à 'complete'
            // pour un compte créé via OAuth.
            $user->setInscriptionStatus('complete');

            $entityManager->persist($profil);
            $entityManager->persist($user);
            $entityManager->flush();

            $request->getSession()->remove('oauth_registration_user_id');

            // Authentification Symfony définitive maintenant que le
            // profil (et notamment le CV) est complet.
            $security->login($user, 'form_login', 'main');

            $this->addFlash('success', 'Votre profil est complet. Bienvenue sur MatchCV !');

            return $this->redirectToRoute('app_dashboard_redirect');
        }

        return $this->render('inscription/complete_profile.html.twig', [
            'form' => $form,
            'provider' => $user->getOauthProvider(),
        ]);
    }

    private function storeUploadedFile(UploadedFile $file, string $relativeDirectory, SluggerInterface $slugger): string
    {
        $projectDir = $this->getParameter('kernel.project_dir');
        $targetDirectory = $projectDir . '/public/' . $relativeDirectory;

        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0777, true);
        }

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename)->lower();
        $newFilename = $safeFilename . '-' . uniqid('', true) . '.' . $file->guessExtension();

        $file->move($targetDirectory, $newFilename);

        return $relativeDirectory . '/' . $newFilename;
    }
}