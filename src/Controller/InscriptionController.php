<?php

namespace App\Controller;

use App\Entity\ProfilCandidat;
use App\Entity\ProfilEntreprise;
use App\Entity\User;
use App\Form\InscriptionCandidatType;
use App\Form\InscriptionEntrepriseType;
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
     * Règles : RM-U01 (unicité email), RM-U02 (hash bcrypt), RM-U06 (CV obligatoire).
     */
    #[Route('/inscription/candidat', name: 'app_inscription_candidat', methods: ['GET', 'POST'])]
    public function candidat(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        SluggerInterface $slugger
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
            $profilCandidat->setUser($user);

            // Upload photo de profil (optionnel)
            $photoFile = $form->get('photo')->getData();
            if ($photoFile instanceof UploadedFile) {
                $profilCandidat->setPhoto(
                    $this->storeUploadedFile($photoFile, 'uploads/candidats/photos', $slugger)
                );
            }

            // Upload CV PDF (obligatoire pour postuler — RM-U06)
            $cvFile = $form->get('cv')->getData();
            if ($cvFile instanceof UploadedFile) {
                $profilCandidat->setCv(
                    $this->storeUploadedFile($cvFile, 'uploads/candidats/cv', $slugger)
                );
            }

            $entityManager->persist($user);
            $entityManager->flush();

            // ── Passage de l'ID en session pour l'étape 2 ────────────────
            // On stocke l'ID (entier) du User nouvellement créé dans la session.
            // Cette valeur servira à la route face-id pour charger le bon User
            // sans que le candidat soit encore connecté (authentification Symfony
            // n'a pas encore eu lieu — c'est voulu, on est en plein flow d'inscription).
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

            $profilEntreprise->setUser($user);

            $logoFile = $form->get('logo')->getData();
            if ($logoFile instanceof UploadedFile) {
                $profilEntreprise->setLogo(
                    $this->storeUploadedFile($logoFile, 'uploads/entreprises/logos', $slugger)
                );
            }

            $entityManager->persist($user);
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
