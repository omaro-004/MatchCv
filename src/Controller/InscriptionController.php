<?php

namespace App\Controller;

use App\Entity\ProfilCandidat;
use App\Entity\ProfilEntreprise;
use App\Entity\User;
use App\Form\InscriptionCandidatType;
use App\Form\InscriptionEntrepriseType;
use App\Service\CvAiProfileAnalyzer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class InscriptionController extends AbstractController
{
    /**
     * Étape 0 : sélection du rôle (candidat ou entreprise).
     * Règle RM-U05 : OAuth disponible pour candidats uniquement.
     */
    #[Route('/inscription', name: 'app_inscription_choice', methods: ['GET'])]
    public function choice(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard_redirect');
        }

        return $this->render('inscription/choice.html.twig');
    }

    /**
     * Étape 1 (candidat) : formulaire principal.
     *
     * Changement par rapport à l'original : on ne redirige plus vers app_login
     * après la sauvegarde. On redirige vers l'étape Face ID (/inscription/candidat/face-id)
     * en passant l'ID du User fraîchement créé dans la session (clé sécurisée).
     *
     * NOUVEAU (parsing IA du profil) : dès que le CV est uploadé, on déclenche
     * App\Service\CvAiProfileAnalyzer qui extrait le texte du PDF et interroge
     * l'IA pour pré-remplir automatiquement : années d'expérience, langues
     * parlées, compétences techniques, formations, expériences pro et un
     * résumé. Ceci remplace le simple "GET" des champs du formulaire — les
     * données affichées sur le dashboard candidat sont désormais enrichies
     * par une analyse réelle du contenu du CV. En cas d'échec (PDF image,
     * API indisponible...), les champs IA restent simplement vides : cela
     * ne bloque JAMAIS l'inscription (règles RM-U01, RM-U02, RM-U06 inchangées).
     *
     * Règles : RM-U01 (unicité email), RM-U02 (hash bcrypt), RM-U06 (CV obligatoire).
     */
    #[Route('/inscription/candidat', name: 'app_inscription_candidat', methods: ['GET', 'POST'])]
    public function candidat(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        SluggerInterface $slugger,
        CvAiProfileAnalyzer $cvAiProfileAnalyzer
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard_redirect');
        }

        $profilCandidat = new ProfilCandidat();
        $form = $this->createForm(InscriptionCandidatType::class, $profilCandidat);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // ── Création de l'entité User ─────────────────────────────────
            $user = new User();
            $user->setEmail((string) $form->get('email')->getData());
            $user->setRole('candidat');
            $user->setPassword(
                $passwordHasher->hashPassword($user, (string) $form->get('plainPassword')->getData())
                // bcrypt coût 12 configuré dans security.yaml — RM-U02
            );
            $user->setLienLinkedin($this->normalizeNullableString($form->get('lienLinkedin')->getData()));
            $user->setAutresLiens($this->normalizeNullableString($form->get('autresLiens')->getData()));

            // Marquer l'inscription comme "en attente de l'étape Face ID"
            $user->setInscriptionStatus('pending_face_id');

            // ── Liaison 1:1 User ↔ ProfilCandidat ────────────────────────
            $user->setProfilCandidat($profilCandidat);

            // Upload photo de profil (optionnel)
            $photoFile = $form->get('photo')->getData();
            if ($photoFile instanceof UploadedFile) {
                $profilCandidat->setPhoto(
                    $this->storeUploadedFile($photoFile, 'uploads/candidats/photos', $slugger)
                );
            }

            // Upload CV PDF (obligatoire pour postuler — RM-U06)
            $cvAbsolutePath = null;
            $cvFile = $form->get('cv')->getData();
            if ($cvFile instanceof UploadedFile) {
                $cvRelativePath = $this->storeUploadedFile($cvFile, 'uploads/candidats/cv', $slugger);
                $profilCandidat->setCv($cvRelativePath);
                $cvAbsolutePath = $this->getParameter('kernel.project_dir') . '/public/' . $cvRelativePath;
            }

            $entityManager->persist($user);
            $entityManager->persist($profilCandidat);
            $entityManager->flush();

            // ── NOUVEAU : analyse IA du profil (parsing CV + données form) ──
            if ($cvAbsolutePath !== null) {
                $aiData = $cvAiProfileAnalyzer->analyze($cvAbsolutePath, [
                    'nom_complet'   => $profilCandidat->getNomComplet(),
                    'bio'           => $profilCandidat->getBio(),
                    'localisation'  => $profilCandidat->getLocalisation(),
                    'type_contrat'  => $profilCandidat->getTypeContrat(),
                ]);

                $profilCandidat
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

            // ── Passage de l'ID en session pour l'étape 2 ────────────────
            $request->getSession()->set('face_id_registration_user_id', $user->getId());

            return $this->redirectToRoute('app_inscription_candidat_face_id');
        }

        return $this->render('inscription/candidat.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * Étape 2 (candidat) : page de configuration Face ID.
     *
     * Accessible UNIQUEMENT si la session contient 'face_id_registration_user_id'
     * et que l'User correspondant est au statut 'pending_face_id'.
     * Protège contre l'accès direct à l'URL sans avoir rempli l'étape 1.
     *
     * Affiche la vue avec la webcam. Le JS capture le visage et soumet
     * un POST AJAX vers FaceIdController::register().
     */
    #[Route('/inscription/candidat/face-id', name: 'app_inscription_candidat_face_id', methods: ['GET'])]
    public function faceIdStep(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard_redirect');
        }

        // Vérifier que la session contient un User en attente de l'étape Face ID
        $userId = $request->getSession()->get('face_id_registration_user_id');

        if (!$userId) {
            // Accès direct sans avoir passé l'étape 1 → retour au début
            $this->addFlash('error', 'Veuillez d\'abord remplir le formulaire d\'inscription.');
            return $this->redirectToRoute('app_inscription_candidat');
        }

        $user = $entityManager->find(User::class, $userId);

        if (!$user || $user->getRole() !== 'candidat' || $user->isInscriptionComplete()) {
            // User introuvable ou déjà inscrit complet → nettoyer et rediriger
            $request->getSession()->remove('face_id_registration_user_id');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('face_id.html.twig', [
            // On passe le prénom pour personnaliser l'interface
            'nom' => $user->getProfilCandidat()?->getNomComplet() ?? 'Candidat',
        ]);
    }

    /**
     * Étape 2b (candidat) : inscription entreprise (inchangé).
     * Règle RM-U05 : pas d'OAuth pour le rôle Entreprise.
     */
    #[Route('/inscription/entreprise', name: 'app_inscription_entreprise', methods: ['GET', 'POST'])]
    public function entreprise(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        SluggerInterface $slugger
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard_redirect');
        }

        $profilEntreprise = new ProfilEntreprise();
        $form = $this->createForm(InscriptionEntrepriseType::class, $profilEntreprise);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = new User();
            $user->setEmail((string) $form->get('email')->getData());
            $user->setRole('entreprise');
            $user->setPassword(
                $passwordHasher->hashPassword($user, (string) $form->get('plainPassword')->getData())
            );
            $user->setLienLinkedin($this->normalizeNullableString($form->get('lienLinkedin')->getData()));
            // Les entreprises ont toujours le statut 'complete' dès la fin du formulaire
            $user->setInscriptionStatus('complete');

            $user->setProfilEntreprise($profilEntreprise);

            $logoFile = $form->get('logo')->getData();
            if ($logoFile instanceof UploadedFile) {
                $profilEntreprise->setLogo(
                    $this->storeUploadedFile($logoFile, 'uploads/entreprises/logos', $slugger)
                );
            }

            $entityManager->persist($user);
            $entityManager->persist($profilEntreprise);
            $entityManager->flush();

            $this->addFlash('success', 'Votre compte recruteur a été créé avec succès. Connectez-vous pour publier vos premières offres !');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('inscription/entreprise.html.twig', [
            'form' => $form,
        ]);
    }

    // ── Helpers privés ──────────────────────────────────────────────

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

    private function normalizeNullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}