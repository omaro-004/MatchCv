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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
 
class InscriptionController extends AbstractController
{
    /**
     * Étape 1 : sélection du rôle (candidat ou entreprise).
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
     * Étape 2a : formulaire candidat.
     * Crée User (role=candidat) + ProfilCandidat lié en 1:1.
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
            // Création de l'entité User (authentification uniquement)
            $user = new User();
            $user->setEmail((string) $form->get('email')->getData());
            $user->setRole('candidat');
            $user->setPassword(
                $passwordHasher->hashPassword($user, (string) $form->get('plainPassword')->getData())
                // bcrypt coût 12 configuré dans security.yaml — RM-U02
            );
            $user->setLienLinkedin($this->normalizeNullableString($form->get('lienLinkedin')->getData()));
            $user->setAutresLiens($this->normalizeNullableString($form->get('autresLiens')->getData()));
 
            // Liaison 1:1 User ↔ ProfilCandidat
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
 
            $this->addFlash('success', 'Votre compte candidat a été créé avec succès. Connectez-vous pour accéder à vos offres matchées !');
 
            return $this->redirectToRoute('app_login');
        }
 
        return $this->render('inscription/candidat.html.twig', [
            'form' => $form,
        ]);
    }
 
    /**
     * Étape 2b : formulaire entreprise.
     * Crée User (role=entreprise) + ProfilEntreprise lié en 1:1.
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