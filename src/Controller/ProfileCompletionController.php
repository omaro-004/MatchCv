<?php

namespace App\Controller;

use App\Entity\ProfilCandidat;
use App\Entity\User;
use App\Form\CompleteProfileType;
use App\Service\CvAiProfileAnalyzer;
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
 * NOUVEAU (parsing IA du profil) : comme pour le flux d'inscription
 * classique, le CV uploadé ici déclenche également
 * App\Service\CvAiProfileAnalyzer pour enrichir automatiquement le
 * profil (années d'expérience, langues, compétences, formations,
 * expériences pro, résumé).
 *
 * Tant que ce n'est pas fait, l'utilisateur n'est PAS authentifié
 * auprès de Symfony Security (même logique que l'étape Face ID de
 * InscriptionController : ID en session, pas de token).
 */
class ProfileCompletionController extends AbstractController
{
    #[Route('/inscription/completer-profil', name: 'app_complete_profile', methods: ['GET', 'POST'])]
    public function completeProfile(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        Security $security,
        CvAiProfileAnalyzer $cvAiProfileAnalyzer
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
            $user->setProfilCandidat($profil);
        }

        $form = $this->createForm(CompleteProfileType::class, $profil);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $cvAbsolutePath = null;

            $cvFile = $form->get('cv')->getData();
            if ($cvFile instanceof UploadedFile) {
                $cvRelativePath = $this->storeUploadedFile($cvFile, 'uploads/candidats/cv', $slugger);
                $profil->setCv($cvRelativePath);
                $cvAbsolutePath = $this->getParameter('kernel.project_dir') . '/public/' . $cvRelativePath;
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

            // ── NOUVEAU : analyse IA du profil (parsing CV + données form) ──
            if ($cvAbsolutePath !== null) {
                $aiData = $cvAiProfileAnalyzer->analyze($cvAbsolutePath, [
                    'nom_complet'  => $profil->getNomComplet(),
                    'bio'          => $profil->getBio(),
                    'localisation' => $profil->getLocalisation(),
                    'type_contrat' => $profil->getTypeContrat(),
                ]);

                $profil
                    ->setAnneesExperience($aiData['annees_experience'])
                    ->setLanguesParleesArray($aiData['langues_parlees'])
                    ->setCompetencesTechniquesArray($aiData['competences_techniques'])
                    ->setFormationsArray($aiData['formations'])
                    ->setExperiencesProfessionnellesArray($aiData['experiences_professionnelles'])
                    ->setProjetsAcademiquesArray($aiData['projets_academiques'])
                    ->setSoftSkillsArray($aiData['soft_skills'])
                    ->setResumeIa($aiData['resume_ia'])
                    ->setCvAiParsedAt(new \DateTimeImmutable());

                $entityManager->flush();
            }

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