<?php

namespace App\Controller\Entreprise;

use App\Entity\Evenement;
use App\Form\EvenementType;
use App\Repository\EvenementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/entreprise/events')]
class EvenementController extends AbstractController
{
    #[Route('/', name: 'app_entreprise_events', methods: ['GET'])]
    public function index(EvenementRepository $repo, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user || $user->getRole() !== 'entreprise') {
            return $this->redirectToRoute('app_login');
        }

        $events = $repo->findByEntrepriseOrdered($user);

        // No permanent 'termine' flag in DB; compute for display and ensure no inconsistent state
        $now = new \DateTimeImmutable();

        return $this->render('entreprise/events/index.html.twig', [
            'events' => $events,
            'now' => $now,
        ]);
    }

    #[Route('/new', name: 'app_entreprise_events_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user || $user->getRole() !== 'entreprise') {
            return $this->redirectToRoute('app_login');
        }

        $evenement = new Evenement();
        $form = $this->createForm(EvenementType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $evenement->setEntreprise($user);
            $em->persist($evenement);
            $em->flush();

            $this->addFlash('success', 'Événement créé avec succès.');
            return $this->redirectToRoute('app_entreprise_events');
        }

        return $this->render('entreprise/events/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_entreprise_events_edit', methods: ['GET', 'POST'])]
    public function edit(Evenement $evenement, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user || $user->getRole() !== 'entreprise' || $evenement->getEntreprise()?->getId() !== $user->getId()) {
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(EvenementType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Événement mis à jour.');
            return $this->redirectToRoute('app_entreprise_events');
        }

        return $this->render('entreprise/events/edit.html.twig', [
            'form' => $form->createView(),
            'evenement' => $evenement,
        ]);
    }

    #[Route('/{id}/cancel', name: 'app_entreprise_events_cancel', methods: ['POST'])]
    public function cancel(Evenement $evenement, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user || $user->getRole() !== 'entreprise' || $evenement->getEntreprise()?->getId() !== $user->getId()) {
            return $this->redirectToRoute('app_login');
        }

        $note = $request->request->get('note');
        $evenement->setIsAnnule(true);
        $evenement->setNoteAnnulation($note ?: null);
        $em->flush();

        $this->addFlash('success', 'Événement annulé.');
        return $this->redirectToRoute('app_entreprise_events');
    }

    #[Route('/{id}/archive', name: 'app_entreprise_events_archive', methods: ['POST'])]
    public function archive(Evenement $evenement, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user || $user->getRole() !== 'entreprise' || $evenement->getEntreprise()?->getId() !== $user->getId()) {
            return $this->redirectToRoute('app_login');
        }

        $evenement->setIsArchive(!$evenement->isArchive());
        $em->flush();

        $this->addFlash('success', $evenement->isArchive() ? 'Événement archivé.' : 'Événement désarchivé.');
        return $this->redirectToRoute('app_entreprise_events');
    }

    #[Route('/{id}', name: 'app_entreprise_events_show', methods: ['GET'])]
    public function show(Evenement $evenement): Response
    {
        $user = $this->getUser();
        if (!$user || $user->getRole() !== 'entreprise' || $evenement->getEntreprise()?->getId() !== $user->getId()) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('entreprise/events/show.html.twig', [
            'evenement' => $evenement,
        ]);
    }
}
