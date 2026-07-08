<?php

namespace App\Controller;

use App\Entity\Offre;
use App\Entity\ProfilEntreprise;
use App\Entity\User;
use App\Form\OffreType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * OffreController
 *
 * Gestion des offres côté Entreprise :
 *   - Liste des offres actives (cards)
 *   - Création
 *   - Modification
 *   - Archivage (avec motif obligatoire)
 *   - Liste des offres archivées (avec motif)
 *
 * Toutes les routes sont sous /entreprise/offres, déjà couvertes par
 * security.yaml (^/entreprise -> ROLE_ENTREPRISE). Aucune modification
 * de security.yaml n'est nécessaire.
 *
 * Règle RM-O01 : seule l'entreprise auteure d'une offre peut la modifier
 * ou l'archiver — vérifié explicitement dans findOwnedOffreOrThrow().
 */
#[IsGranted('ROLE_ENTREPRISE')]
class OffreController extends AbstractController
{
    #[Route('/entreprise/offres', name: 'app_entreprise_offres_liste', methods: ['GET'])]
    public function liste(EntityManagerInterface $em): Response
    {
        $profil = $this->getProfilEntrepriseOrThrow();

        $offres = $em->getRepository(Offre::class)->findActiveByEntreprise($profil);

        return $this->render('entreprise/offres/liste.html.twig', [
            'offres' => $offres,
        ]);
    }

    #[Route('/entreprise/offres/archivees', name: 'app_entreprise_offres_archivees', methods: ['GET'])]
    public function archivees(EntityManagerInterface $em): Response
    {
        $profil = $this->getProfilEntrepriseOrThrow();

        $offres = $em->getRepository(Offre::class)->findArchivedByEntreprise($profil);

        return $this->render('entreprise/offres/archivees.html.twig', [
            'offres' => $offres,
        ]);
    }

    #[Route('/entreprise/offres/creer', name: 'app_entreprise_offre_creer', methods: ['GET', 'POST'])]
    public function creer(Request $request, EntityManagerInterface $em): Response
    {
        $profil = $this->getProfilEntrepriseOrThrow();

        $offre = new Offre();
        $offre->setEntreprise($profil);

        $form = $this->createForm(OffreType::class, $offre);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($offre);
            $em->flush();

            $this->addFlash('success', 'L\'offre « ' . $offre->getTitre() . ' » a été publiée avec succès.');

            return $this->redirectToRoute('app_entreprise_offres_liste');
        }

        return $this->render('entreprise/offres/formulaire.html.twig', [
            'form' => $form,
            'mode' => 'creation',
        ]);
    }

    #[Route('/entreprise/offres/{id}/modifier', name: 'app_entreprise_offre_modifier', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function modifier(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $profil = $this->getProfilEntrepriseOrThrow();
        $offre = $this->findOwnedOffreOrThrow($em, $id, $profil);

        if ($offre->isArchivee()) {
            $this->addFlash('error', 'Une offre archivée ne peut plus être modifiée.');
            return $this->redirectToRoute('app_entreprise_offres_archivees');
        }

        $form = $this->createForm(OffreType::class, $offre);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'L\'offre a été mise à jour avec succès.');

            return $this->redirectToRoute('app_entreprise_offres_liste');
        }

        return $this->render('entreprise/offres/formulaire.html.twig', [
            'form' => $form,
            'mode' => 'edition',
            'offre' => $offre,
        ]);
    }

    #[Route('/entreprise/offres/{id}/archiver', name: 'app_entreprise_offre_archiver', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function archiver(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $profil = $this->getProfilEntrepriseOrThrow();
        $offre = $this->findOwnedOffreOrThrow($em, $id, $profil);

        $csrfToken = $request->headers->get('X-CSRF-Token', '');
        if (!$this->isCsrfTokenValid('offre_archiver', $csrfToken)) {
            return $this->json(['error' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        if ($offre->isArchivee()) {
            return $this->json(['error' => 'Cette offre est déjà archivée.'], Response::HTTP_CONFLICT);
        }

        $payload = json_decode($request->getContent(), true);
        $motif = is_array($payload) ? ($payload['motif'] ?? null) : null;
        $details = is_array($payload) && isset($payload['details']) ? trim((string) $payload['details']) : null;

        if (!in_array($motif, Offre::MOTIFS_ARCHIVAGE, true)) {
            return $this->json(['error' => 'Motif d\'archivage invalide.'], Response::HTTP_BAD_REQUEST);
        }

        if ($motif === 'autre' && ($details === null || $details === '')) {
            return $this->json(['error' => 'Merci de préciser le motif.'], Response::HTTP_BAD_REQUEST);
        }

        $offre->archiver($motif, $details !== '' ? $details : null);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'L\'offre a été archivée avec succès.',
            'redirect' => $this->generateUrl('app_entreprise_offres_liste'),
        ]);
    }

    // ── Helpers privés ──────────────────────────────────────────────

    private function getProfilEntrepriseOrThrow(): ProfilEntreprise
    {
        /** @var User $user */
        $user = $this->getUser();
        $profil = $user->getProfilEntreprise();

        if (!$profil) {
            throw $this->createNotFoundException('Profil entreprise introuvable.');
        }

        return $profil;
    }

    private function findOwnedOffreOrThrow(EntityManagerInterface $em, int $id, ProfilEntreprise $profil): Offre
    {
        $offre = $em->getRepository(Offre::class)->find($id);

        if (!$offre) {
            throw $this->createNotFoundException('Offre introuvable.');
        }

        // Règle RM-O01 : seule l'entreprise auteure peut agir sur son offre.
        if ($offre->getEntreprise()?->getId() !== $profil->getId()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à accéder à cette offre.');
        }

        return $offre;
    }
}