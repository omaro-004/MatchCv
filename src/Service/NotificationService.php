<?php

namespace App\Service;

use App\Entity\AvisEntreprise;
use App\Entity\Candidature;
use App\Entity\Notification;
use App\Entity\Offre;
use App\Entity\User;
use App\Repository\ProfilCandidatRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * NotificationService
 *
 * Point d'entrée unique pour la création de notifications. Centralise la
 * logique de construction des messages et des liens, afin que les
 * Controllers déclencheurs restent minces (ils appellent une seule méthode
 * après leur flush() principal).
 *
 * Principe de robustesse : chaque méthode vérifie la présence des relations
 * nécessaires (destinataire, offre...) et ne fait rien si elles manquent —
 * jamais d'exception qui casserait le flux métier principal (candidature,
 * changement de statut, etc.).
 */
class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProfilCandidatRepository $profilCandidatRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Entreprise notifiée : nouvelle candidature reçue sur une de ses offres.
     */
    public function notifierNouvelleCandidature(Candidature $candidature): void
    {
        $offre = $candidature->getOffre();
        $entreprise = $offre?->getEntreprise();
        $destinataire = $entreprise?->getUser();
        $candidat = $candidature->getCandidat();

        if (!$destinataire || !$candidat || !$offre) {
            return;
        }

        $lien = $this->urlGenerator->generate('app_entreprise_candidatures_liste', ['offre' => $offre->getId()]);

        $this->creer(
            $destinataire,
            Notification::TYPE_CANDIDATURE_RECUE,
            'Nouvelle candidature reçue',
            sprintf('%s a postulé à votre offre « %s ».', $candidat->getNomComplet(), $offre->getTitre()),
            $lien
        );
    }

    /**
     * Candidat notifié : le statut d'une de ses candidatures a changé.
     */
    public function notifierChangementStatut(Candidature $candidature): void
    {
        $candidat = $candidature->getCandidat();
        $destinataire = $candidat?->getUser();
        $offre = $candidature->getOffre();

        if (!$destinataire || !$offre) {
            return;
        }

        $lien = $this->urlGenerator->generate('app_candidat_candidatures_liste');

        $message = $candidature->getStatut() === Candidature::STATUT_ACCEPTE
            ? sprintf('Bonne nouvelle ! Votre candidature pour « %s » a été acceptée.', $offre->getTitre())
            : sprintf('Votre candidature pour « %s » a été refusée.', $offre->getTitre());

        $this->creer(
            $destinataire,
            Notification::TYPE_STATUT_CANDIDATURE,
            'Statut de candidature mis à jour — ' . $candidature->getStatutLabel(),
            $message,
            $lien
        );
    }

    /**
     * Entreprise notifiée : un candidat a déposé (ou modifié) un avis.
     */
    public function notifierNouvelAvis(AvisEntreprise $avis, bool $isNouveau): void
    {
        $entreprise = $avis->getEntreprise();
        $destinataire = $entreprise?->getUser();
        $candidat = $avis->getCandidat();

        if (!$destinataire || !$candidat) {
            return;
        }

        $lien = $this->urlGenerator->generate('app_profil_entreprise');

        $commentaire = $avis->getCommentaire();
        $extrait = $commentaire
            ? sprintf(' : « %s »', mb_substr($commentaire, 0, 80) . (mb_strlen($commentaire) > 80 ? '…' : ''))
            : '.';

        $this->creer(
            $destinataire,
            Notification::TYPE_NOUVEL_AVIS,
            $isNouveau ? 'Nouvel avis reçu' : 'Avis mis à jour',
            sprintf('%s vous a attribué la note de %d/5%s', $candidat->getNomComplet(), $avis->getNote(), $extrait),
            $lien
        );
    }

    /**
     * Candidats notifiés en masse : une nouvelle offre correspondant à leur
     * type de contrat recherché vient d'être publiée.
     */
    public function notifierNouvelleOffre(Offre $offre): void
    {
        $profils = $this->profilCandidatRepository->findMatchingTypeContrat($offre->getTypeContrat());

        if (count($profils) === 0) {
            return;
        }

        $lien = $this->urlGenerator->generate('app_candidat_offre_detail', ['id' => $offre->getId()]);
        $entrepriseNom = $offre->getEntreprise()?->getRaisonSociale() ?? 'Une entreprise';

        foreach ($profils as $profil) {
            $destinataire = $profil->getUser();
            if (!$destinataire) {
                continue;
            }

            $notification = new Notification();
            $notification->setDestinataire($destinataire);
            $notification->setType(Notification::TYPE_NOUVELLE_OFFRE);
            $notification->setTitre('Nouvelle offre pour vous');
            $notification->setMessage(sprintf('%s a publié une nouvelle offre : « %s ».', $entrepriseNom, $offre->getTitre()));
            $notification->setLien($lien);

            $this->entityManager->persist($notification);
        }

        $this->entityManager->flush();
    }

    private function creer(User $destinataire, string $type, string $titre, string $message, ?string $lien): Notification
    {
        $notification = new Notification();
        $notification->setDestinataire($destinataire);
        $notification->setType($type);
        $notification->setTitre($titre);
        $notification->setMessage($message);
        $notification->setLien($lien);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return $notification;
    }
}