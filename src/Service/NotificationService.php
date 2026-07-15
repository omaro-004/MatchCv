<?php

namespace App\Service;

use App\Entity\AvisEntreprise;
use App\Entity\Candidature;
use App\Entity\Notification;
use App\Entity\Offre;
use App\Entity\User;
use App\Repository\ProfilCandidatRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * NotificationService
 *
 * Point d'entrée unique pour la création de notifications. Centralise la
 * logique de construction des messages et des liens, afin que les
 * Controllers déclencheurs restent minces (ils appellent une seule méthode
 * après leur flush() principal).
 *
 * NOUVEAU : en plus de la notification in-app, un email est envoyé au
 * candidat lorsque le statut de sa candidature passe à "Accepté" ou
 * "Refusé" (notifierChangementStatut). C'est la SEULE notification qui
 * déclenche un envoi d'email — toutes les autres (nouvelle candidature,
 * nouvel avis, nouvelle offre) restent uniquement in-app, sans email.
 *
 * Principe de robustesse : chaque méthode vérifie la présence des relations
 * nécessaires (destinataire, offre...) et ne fait rien si elles manquent —
 * jamais d'exception qui casserait le flux métier principal (candidature,
 * changement de statut, etc.). Un échec d'envoi d'email (SMTP indisponible,
 * DSN mal configuré...) est loggé mais NE DOIT JAMAIS empêcher la mise à
 * jour du statut de la candidature ni la création de la notification in-app.
 */
class NotificationService
{
    private const MAIL_FROM = 'no-reply@matchcv.com';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProfilCandidatRepository $profilCandidatRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
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
     *
     * NOUVEAU : en plus de la notification in-app (comportement existant,
     * inchangé), un email esthétique est envoyé au candidat avec la
     * décision de l'entreprise (accepté ou refusé).
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

        $accepte = $candidature->getStatut() === Candidature::STATUT_ACCEPTE;

        $message = $accepte
            ? sprintf('Bonne nouvelle ! Votre candidature pour « %s » a été acceptée.', $offre->getTitre())
            : sprintf('Votre candidature pour « %s » a été refusée.', $offre->getTitre());

        // ── 1. Notification in-app (comportement existant, inchangé) ──────
        $this->creer(
            $destinataire,
            Notification::TYPE_STATUT_CANDIDATURE,
            'Statut de candidature mis à jour — ' . $candidature->getStatutLabel(),
            $message,
            $lien
        );

        // ── 2. NOUVEAU — Email de la décision ──────────────────────────────
        $this->envoyerEmailChangementStatut($candidature, $destinataire, $offre, $accepte);
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

    /**
     * Envoie l'email de décision (accepté/refusé) au candidat. Ne bloque
     * jamais le flux principal en cas d'échec (SMTP down, DSN invalide...).
     */
    private function envoyerEmailChangementStatut(Candidature $candidature, User $destinataire, Offre $offre, bool $accepte): void
    {
        $candidat = $candidature->getCandidat();

        try {
            $email = (new TemplatedEmail())
                ->from(self::MAIL_FROM)
                ->to($destinataire->getEmail())
                ->subject($accepte
                    ? '✓ Votre candidature a été acceptée — ' . $offre->getTitre()
                    : 'Mise à jour de votre candidature — ' . $offre->getTitre())
                ->htmlTemplate('emails/candidature_statut.html.twig')
                ->context([
                    'accepte' => $accepte,
                    'candidat_nom' => $candidat?->getNomComplet() ?? $destinataire->getEmail(),
                    'candidat_prenom' => $candidat ? explode(' ', trim($candidat->getNomComplet()))[0] : '',
                    'offre_titre' => $offre->getTitre(),
                    'entreprise_nom' => $offre->getEntreprise()?->getRaisonSociale() ?? 'L\'entreprise',
                    'lien_candidatures' => $this->urlGenerator->generate(
                        'app_candidat_candidatures_liste',
                        [],
                        UrlGeneratorInterface::ABSOLUTE_URL
                    ),
                ]);

            $this->mailer->send($email);
        } catch (\Throwable $e) {
            // On ne casse jamais le changement de statut si l'email échoue.
            $this->logger->error('NotificationService: échec envoi email statut candidature — ' . $e->getMessage());
        }
    }
}