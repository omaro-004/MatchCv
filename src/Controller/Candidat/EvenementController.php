<?php

namespace App\Controller\Candidat;

use App\Entity\Evenement;
use App\Entity\EvenementApplication;
use App\Form\EvenementApplicationType;
use App\Repository\EvenementRepository;
use App\Repository\EvenementApplicationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mail\Email;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/candidat/events')]
class EvenementController extends AbstractController
{
    #[Route('', name: 'app_candidat_events', methods: ['GET'])]
    public function index(EvenementRepository $repo, \App\Repository\EvenementApplicationRepository $appRepo): Response
    {
        $events = $repo->findAllActive();

        $applied = [];
        if ($this->getUser()) {
            $apps = $appRepo->findByCandidat($this->getUser());
            foreach ($apps as $a) {
                $applied[] = $a->getEvenement();
            }
        }

        return $this->render('candidat/events/index.html.twig', [
            'events' => $events,
            'appliedEvents' => $applied,
        ]);
    }

    #[Route('/{id}', name: 'app_candidat_events_show', methods: ['GET','POST'], requirements: ['id' => '\\d+'])]
    public function show(Evenement $evenement, Request $request, EntityManagerInterface $em, MailerInterface $mailer, EvenementApplicationRepository $appRepo): Response
    {
        $existing = null;
        if ($this->getUser()) {
            $existing = $appRepo->findOneBy(['evenement' => $evenement, 'candidat' => $this->getUser()]);
        }

        $form = $this->createForm(EvenementApplicationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->denyAccessUnlessGranted('ROLE_CANDIDAT');

            // Prevent duplicate applications
            if ($existing) {
                $this->addFlash('info', 'Vous êtes déjà inscrit à cet événement.');
                return $this->redirectToRoute('app_candidat_events');
            }

            $app = new EvenementApplication();
            $app->setEvenement($evenement);
            $app->setCandidat($this->getUser());

            $em->persist($app);
            $em->flush();

            // send confirmation email to candidate
            try {
                $email = (new Email())
                    ->from('no-reply@matchcv.local')
                    ->to($this->getUser()->getEmail())
                    ->subject('Confirmation de candidature à l\'événement ' . $evenement->getTitre())
                    ->text('Vous vous êtes inscrit à l\'événement "' . $evenement->getTitre() . '".');

                $mailer->send($email);
            } catch (\Throwable $e) {
                // ignore email errors but log if needed
            }

            // redirect to show with confirmation flag so the modal is displayed
            return $this->redirectToRoute('app_candidat_events_show', ['id' => $evenement->getId(), 'confirmed' => 1]);
        }

        return $this->render('candidat/events/show.html.twig', [
            'evenement' => $evenement,
            'form' => $form->createView(),
            'existingApplication' => $existing,
        ]);
    }

    #[Route('/{id}/unregister', name: 'app_candidat_events_unregister', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function unregister(Evenement $evenement, Request $request, EntityManagerInterface $em, EvenementApplicationRepository $appRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_CANDIDAT');

        if (!$this->isCsrfTokenValid('evenement_unregister_' . $evenement->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Session invalide, veuillez réessayer.');
            return $this->redirectToRoute('app_candidat_events_show', ['id' => $evenement->getId()]);
        }

        $existing = $appRepo->findOneBy(['evenement' => $evenement, 'candidat' => $this->getUser()]);
        if ($existing) {
            $em->remove($existing);
            $em->flush();
            $this->addFlash('success', 'Vous avez été désinscrit de cet événement.');
        } else {
            $this->addFlash('info', 'Vous n\'étiez pas inscrit à cet événement.');
        }

        return $this->redirectToRoute('app_candidat_events');
    }
}
