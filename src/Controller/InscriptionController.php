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
    #[Route('/inscription', name: 'app_inscription_choice', methods: ['GET'])]
    public function choice(): Response
    {
        return $this->render('inscription/choice.html.twig');
    }

    #[Route('/inscription/candidat', name: 'app_inscription_candidat', methods: ['GET', 'POST'])]
    public function candidat(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        SluggerInterface $slugger
    ): Response
    {
        $profilCandidat = new ProfilCandidat();
        $form = $this->createForm(InscriptionCandidatType::class, $profilCandidat);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = new User();
            $user->setEmail((string) $form->get('email')->getData());
            $user->setRole('candidat');
            $user->setPassword($passwordHasher->hashPassword($user, (string) $form->get('plainPassword')->getData()));
            $user->setLienLinkedin($this->normalizeNullableString($form->get('lienLinkedin')->getData()));
            $user->setAutresLiens($this->normalizeNullableString($form->get('autresLiens')->getData()));

            $profilCandidat->setUser($user);

            $photoFile = $form->get('photo')->getData();
            if ($photoFile instanceof UploadedFile) {
                $profilCandidat->setPhoto($this->storeUploadedFile($photoFile, 'uploads/candidats/photos', $slugger));
            }

            $cvFile = $form->get('cv')->getData();
            if ($cvFile instanceof UploadedFile) {
                $profilCandidat->setCv($this->storeUploadedFile($cvFile, 'uploads/candidats/cv', $slugger));
            }

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Votre compte candidat a été créé avec succès.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('inscription/candidat.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/inscription/entreprise', name: 'app_inscription_entreprise', methods: ['GET', 'POST'])]
    public function entreprise(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        SluggerInterface $slugger
    ): Response
    {
        $profilEntreprise = new ProfilEntreprise();
        $form = $this->createForm(InscriptionEntrepriseType::class, $profilEntreprise);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = new User();
            $user->setEmail((string) $form->get('email')->getData());
            $user->setRole('entreprise');
            $user->setPassword($passwordHasher->hashPassword($user, (string) $form->get('plainPassword')->getData()));
            $user->setLienLinkedin($this->normalizeNullableString($form->get('lienLinkedin')->getData()));

            $profilEntreprise->setUser($user);

            $logoFile = $form->get('logo')->getData();
            if ($logoFile instanceof UploadedFile) {
                $profilEntreprise->setLogo($this->storeUploadedFile($logoFile, 'uploads/entreprises/logos', $slugger));
            }

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Votre compte entreprise a été créé avec succès.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('inscription/entreprise.html.twig', [
            'form' => $form,
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

    private function normalizeNullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
