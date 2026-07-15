<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Notification
 *
 * Notification adressée à un User (candidat ou entreprise), déclenchée par
 * App\Service\NotificationService lors d'événements métier :
 *   - Entreprise : nouvelle candidature reçue, nouvel avis déposé.
 *   - Candidat   : changement de statut de candidature, nouvelle offre
 *                  correspondant à son type de contrat.
 *
 * Choix d'architecture : pas de relation FK directe vers Offre/Candidature/
 * AvisEntreprise. Le champ `lien` stocke l'URL déjà résolue au moment de la
 * création (via UrlGeneratorInterface dans NotificationService). Ainsi, la
 * notification reste lisible et exploitable même si la ressource d'origine
 * est supprimée par la suite (règle RM-N04 : conservation des notifications).
 */
#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notification')]
#[ORM\HasLifecycleCallbacks]
class Notification
{
    public const TYPE_CANDIDATURE_RECUE = 'candidature_recue';
    public const TYPE_STATUT_CANDIDATURE = 'statut_candidature';
    public const TYPE_NOUVEL_AVIS = 'nouvel_avis';
    public const TYPE_NOUVELLE_OFFRE = 'nouvelle_offre';
    public const TYPE_SYSTEME = 'systeme';

    public const TYPES = [
        self::TYPE_CANDIDATURE_RECUE,
        self::TYPE_STATUT_CANDIDATURE,
        self::TYPE_NOUVEL_AVIS,
        self::TYPE_NOUVELLE_OFFRE,
        self::TYPE_SYSTEME,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_notification', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_destinataire', referencedColumnName: 'id_user', nullable: false, onDelete: 'CASCADE')]
    private ?User $destinataire = null;

    #[ORM\Column(
        name: 'type',
        type: 'string',
        length: 30,
        columnDefinition: "ENUM('candidature_recue','statut_candidature','nouvel_avis','nouvelle_offre','systeme') NOT NULL"
    )]
    private string $type = self::TYPE_SYSTEME;

    #[ORM\Column(name: 'titre', type: 'string', length: 150)]
    private string $titre = '';

    #[ORM\Column(name: 'message', type: 'text')]
    private string $message = '';

    /**
     * URL relative précalculée vers la ressource concernée (ex: liste des
     * candidatures, profil entreprise, détail d'une offre).
     */
    #[ORM\Column(name: 'lien', type: 'string', length: 500, nullable: true)]
    private ?string $lien = null;

    #[ORM\Column(name: 'lu', type: 'boolean', options: ['default' => false])]
    private bool $lu = false;

    #[ORM\Column(name: 'date_envoi', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $dateEnvoi = null;

    #[ORM\PrePersist]
    public function setDateEnvoiOnCreate(): void
    {
        if ($this->dateEnvoi === null) {
            $this->dateEnvoi = new \DateTimeImmutable();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDestinataire(): ?User
    {
        return $this->destinataire;
    }

    public function setDestinataire(?User $destinataire): static
    {
        $this->destinataire = $destinataire;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        if (!in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException("Type de notification invalide : $type.");
        }
        $this->type = $type;
        return $this;
    }

    public function getTitre(): string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;
        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function getLien(): ?string
    {
        return $this->lien;
    }

    public function setLien(?string $lien): static
    {
        $this->lien = $lien;
        return $this;
    }

    public function isLu(): bool
    {
        return $this->lu;
    }

    public function setLu(bool $lu): static
    {
        $this->lu = $lu;
        return $this;
    }

    public function getDateEnvoi(): ?\DateTimeImmutable
    {
        return $this->dateEnvoi;
    }

    public function setDateEnvoi(\DateTimeImmutable $dateEnvoi): static
    {
        $this->dateEnvoi = $dateEnvoi;
        return $this;
    }

    /**
     * Icône associée au type — utilisée par le contrôleur (JSON) et les templates.
     */
    public function getTypeIcon(): string
    {
        return match ($this->type) {
            self::TYPE_CANDIDATURE_RECUE => '📩',
            self::TYPE_STATUT_CANDIDATURE => '📄',
            self::TYPE_NOUVEL_AVIS => '⭐',
            self::TYPE_NOUVELLE_OFFRE => '💼',
            default => '🔔',
        };
    }

    /**
     * Classe de couleur (réutilise les classes .accent/.success/.warning/.info
     * déjà définies dans base.html.twig pour les stat-card-icon).
     */
    public function getTypeColorClass(): string
    {
        return match ($this->type) {
            self::TYPE_CANDIDATURE_RECUE => 'accent',
            self::TYPE_STATUT_CANDIDATURE => 'success',
            self::TYPE_NOUVEL_AVIS => 'warning',
            self::TYPE_NOUVELLE_OFFRE => 'info',
            default => 'accent',
        };
    }
}