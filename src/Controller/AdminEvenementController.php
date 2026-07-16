<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Entity\User;
use App\Form\AdminEvenementType;
use App\Repository\EvenementApplicationRepository;
use App\Repository\EvenementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[IsGranted('ROLE_ADMIN')]
class AdminEvenementController extends AbstractController
{
    #[Route('/admin/evenements', name: 'app_admin_evenements_liste', methods: ['GET'])]
    public function liste(Request $request, EvenementRepository $evenementRepository): Response
    {
        $filters = [
            'q' => trim((string) $request->query->get('q', '')),
            'statut' => (string) $request->query->get('statut', ''),
        ];

        return $this->render('admin/evenements/liste.html.twig', [
            'evenements' => $evenementRepository->findAllForAdmin($filters),
            'filters' => $filters,
        ]);
    }

    #[Route('/admin/evenements/new', name: 'app_admin_evenements_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $evenement = new Evenement();
        /** @var User $admin */
        $admin = $this->getUser();
        $evenement->setEntreprise($admin);
        $form = $this->createForm(AdminEvenementType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $photoFile */
            $photoFile = $form->get('photo')->getData();

            if ($photoFile instanceof UploadedFile) {
                $photo = $this->storeUploadedPhoto($photoFile, $slugger);
                if ($photo !== null) {
                    $evenement->setPhoto($photo);
                }
            }

            $em->persist($evenement);
            $em->flush();

            $this->addFlash('success', 'Événement créé avec succès.');

            return $this->redirectToRoute('app_admin_evenements_show', ['id' => $evenement->getId()]);
        }

        return $this->render('admin/evenements/form.html.twig', [
            'form' => $form,
            'evenement' => $evenement,
            'page_title' => 'Créer un événement',
            'page_subtitle' => 'Publier un événement MatchCV',
            'submit_label' => 'Créer l\'événement',
        ]);
    }

    #[Route('/admin/evenements/{id}/edit', name: 'app_admin_evenements_edit', methods: ['GET', 'POST'], requirements: ['id' => '\\d+'])]
    public function edit(Evenement $evenement, Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(AdminEvenementType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $photoFile */
            $photoFile = $form->get('photo')->getData();

            if ($photoFile instanceof UploadedFile) {
                $photo = $this->storeUploadedPhoto($photoFile, $slugger);
                if ($photo !== null) {
                    $evenement->setPhoto($photo);
                }
            }

            $em->flush();

            $this->addFlash('success', 'Événement mis à jour avec succès.');

            return $this->redirectToRoute('app_admin_evenements_show', ['id' => $evenement->getId()]);
        }

        return $this->render('admin/evenements/form.html.twig', [
            'form' => $form,
            'evenement' => $evenement,
            'page_title' => 'Modifier un événement',
            'page_subtitle' => $evenement->getTitre(),
            'submit_label' => 'Enregistrer les modifications',
        ]);
    }

    #[Route('/admin/evenements/{id}', name: 'app_admin_evenements_show', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function show(Evenement $evenement, EvenementApplicationRepository $appRepo): Response
    {
        $participants = $appRepo->findBy(['evenement' => $evenement], ['createdAt' => 'ASC']);

        return $this->render('admin/evenements/show.html.twig', [
            'evenement' => $evenement,
            'participants' => $participants,
        ]);
    }

    #[Route('/admin/evenements/{id}/archive', name: 'app_admin_evenements_archive', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function archive(Evenement $evenement, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('admin_evenement_archive_' . $evenement->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Session invalide, veuillez réessayer.');

            return $this->redirectToRoute('app_admin_evenements_liste');
        }

        $evenement->setIsArchive(!$evenement->isArchive());
        $em->flush();

        $this->addFlash('success', $evenement->isArchive() ? 'Événement archivé.' : 'Événement désarchivé.');

        return $this->redirectToRoute('app_admin_evenements_show', ['id' => $evenement->getId()]);
    }

    #[Route('/admin/evenements/{id}/cancel', name: 'app_admin_evenements_cancel', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function cancel(Evenement $evenement, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('admin_evenement_cancel_' . $evenement->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Session invalide, veuillez réessayer.');

            return $this->redirectToRoute('app_admin_evenements_liste');
        }

        $note = trim((string) $request->request->get('note', ''));

        $evenement->setIsAnnule(true);
        $evenement->setNoteAnnulation($note !== '' ? $note : null);
        $em->flush();

        $this->addFlash('success', 'Événement annulé.');

        return $this->redirectToRoute('app_admin_evenements_show', ['id' => $evenement->getId()]);
    }

    #[Route('/admin/evenements/{id}/supprimer', name: 'app_admin_evenements_supprimer', methods: ['POST'], requirements: ['id' => '\\d+'])]
    private function storeUploadedPhoto(UploadedFile $photoFile, SluggerInterface $slugger): ?string
    {
        $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $photoFile->guessExtension();

        try {
            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/evenements';
            $photoFile->move($uploadDir, $newFilename);
        } catch (FileException) {
            $this->addFlash('error', 'Impossible d\'uploader la photo.');

            return null;
        }

        return $newFilename;
    }
}