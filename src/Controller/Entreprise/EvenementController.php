<?php

namespace App\Controller\Entreprise;

use App\Entity\Evenement;
use App\Entity\User;
use App\Form\EvenementType;
use App\Repository\EvenementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * EvenementController (espace entreprise)
 *
 * Gère la création, modification, annulation et archivage des événements
 * publiés par une entreprise.
 *
 * Routes protégées par #[IsGranted('ROLE_ENTREPRISE')] : un candidat ou un
 * visiteur anonyme reçoit une 403/redirection login automatiquement, sans
 * avoir besoin de vérifier le rôle manuellement dans chaque méthode.
 *
 * Chaque action qui modifie ou supprime un état (annuler / archiver) est
 * protégée par un jeton CSRF, comme le reste des actions POST de
 * l'application (voir OffreController::archiver()).
 */
#[Route('/entreprise/events')]
#[IsGranted('ROLE_ENTREPRISE')]
class EvenementController extends AbstractController
{
    #[Route('', name: 'app_entreprise_events', methods: ['GET'])]
    public function index(EvenementRepository $repo): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $active = $repo->findActiveByEntreprise($user);
        $archived = $repo->findArchivedByEntreprise($user);
        $cancelled = $repo->findCancelledByEntreprise($user);

        return $this->render('entreprise/events/index.html.twig', [
            'activeEvents' => $active,
            'archivedEvents' => $archived,
            'cancelledEvents' => $cancelled,
            'now' => new \DateTimeImmutable(),
        ]);
    }

    #[Route('/new', name: 'app_entreprise_events_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $evenement = new Evenement();
        $form = $this->createForm(EvenementType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $photoFile */
            $photoFile = $form->get('photo')->getData();

            if ($photoFile instanceof UploadedFile) {
                $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $photoFile->guessExtension();
                try {
                    $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/evenements';
                    $photoFile->move($uploadDir, $newFilename);
                    $evenement->setPhoto($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Impossible d\'uploader la photo.');
                }
            }

            $evenement->setEntreprise($user);
            $em->persist($evenement);
            $em->flush();

            $this->addFlash('success', 'Événement « ' . $evenement->getTitre() . ' » créé avec succès.');

            return $this->redirectToRoute('app_entreprise_events');
        }

        return $this->render('entreprise/events/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_entreprise_events_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Evenement $evenement, Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $this->denyAccessUnlessOwner($evenement);

        $form = $this->createForm(EvenementType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $photoFile */
            $photoFile = $form->get('photo')->getData();
            if ($photoFile instanceof UploadedFile) {
                $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $photoFile->guessExtension();
                try {
                    $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/evenements';
                    $photoFile->move($uploadDir, $newFilename);
                    $evenement->setPhoto($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Impossible d\'uploader la photo.');
                }
            }

            $em->flush();

            $this->addFlash('success', 'Événement mis à jour avec succès.');

            return $this->redirectToRoute('app_entreprise_events');
        }

        return $this->render('entreprise/events/edit.html.twig', [
            'form' => $form,
            'evenement' => $evenement,
        ]);
    }

    #[Route('/{id}/cancel', name: 'app_entreprise_events_cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function cancel(Evenement $evenement, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessOwner($evenement);

        if (!$this->isCsrfTokenValid('evenement_cancel_' . $evenement->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Session invalide, veuillez réessayer.');

            return $this->redirectToRoute('app_entreprise_events');
        }

        $note = trim((string) $request->request->get('note', ''));

        $evenement->setIsAnnule(true);
        $evenement->setNoteAnnulation($note !== '' ? $note : null);
        $em->flush();

        $this->addFlash('success', 'Événement annulé.');

        return $this->redirectToRoute('app_entreprise_events');
    }

    #[Route('/{id}/archive', name: 'app_entreprise_events_archive', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function archive(Evenement $evenement, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessOwner($evenement);

        if (!$this->isCsrfTokenValid('evenement_archive_' . $evenement->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Session invalide, veuillez réessayer.');

            return $this->redirectToRoute('app_entreprise_events');
        }

        $evenement->setIsArchive(!$evenement->isArchive());
        $em->flush();

        $this->addFlash('success', $evenement->isArchive() ? 'Événement archivé.' : 'Événement désarchivé.');

        return $this->redirectToRoute('app_entreprise_events');
    }

    #[Route('/{id}', name: 'app_entreprise_events_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Evenement $evenement, \App\Repository\EvenementApplicationRepository $appRepo): Response
    {
        $this->denyAccessUnlessOwner($evenement);

        $participants = $appRepo->findBy(['evenement' => $evenement], ['createdAt' => 'ASC']);

        return $this->render('entreprise/events/show.html.twig', [
            'evenement' => $evenement,
            'participants' => $participants,
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function denyAccessUnlessOwner(Evenement $evenement): void
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($evenement->getEntreprise()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException("Vous n'êtes pas autorisé à accéder à cet événement.");
        }
    }
}