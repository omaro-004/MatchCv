<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\ORM\Mapping as ORM;

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
    // NOUVEAU — notifications déclenchées par les actions de modération Admin
    public const TYPE_COMPTE_SUSPENDU = 'compte_suspendu';
    public const TYPE_OFFRE_SUPPRIMEE_ADMIN = 'offre_supprimee_admin';
    public const TYPE_CANDIDATURE_SUPPRIMEE_ADMIN = 'candidature_supprimee_admin';

    public const TYPES = [
        self::TYPE_CANDIDATURE_RECUE,
        self::TYPE_STATUT_CANDIDATURE,
        self::TYPE_NOUVEL_AVIS,
        self::TYPE_NOUVELLE_OFFRE,
        self::TYPE_SYSTEME,
        self::TYPE_COMPTE_SUSPENDU,
        self::TYPE_OFFRE_SUPPRIMEE_ADMIN,
        self::TYPE_CANDIDATURE_SUPPRIMEE_ADMIN,
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
        columnDefinition: "ENUM('candidature_recue','statut_candidature','nouvel_avis','nouvelle_offre','systeme','compte_suspendu','offre_supprimee_admin','candidature_supprimee_admin') NOT NULL"
    )]
    private string $type = self::TYPE_SYSTEME;

    #[ORM\Column(name: 'titre', type: 'string', length: 150)]
    private string $titre = '';

    #[ORM\Column(name: 'message', type: 'text')]
    private string $message = '';

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

    public function getTypeIcon(): string
    {
        return match ($this->type) {
            self::TYPE_CANDIDATURE_RECUE => '📩',
            self::TYPE_STATUT_CANDIDATURE => '📄',
            self::TYPE_NOUVEL_AVIS => '⭐',
            self::TYPE_NOUVELLE_OFFRE => '💼',
            self::TYPE_COMPTE_SUSPENDU => '🚫',
            self::TYPE_OFFRE_SUPPRIMEE_ADMIN => '🛑',
            self::TYPE_CANDIDATURE_SUPPRIMEE_ADMIN => '🗑️',
            default => '🔔',
        };
    }

    public function getTypeColorClass(): string
    {
        return match ($this->type) {
            self::TYPE_CANDIDATURE_RECUE => 'accent',
            self::TYPE_STATUT_CANDIDATURE => 'success',
            self::TYPE_NOUVEL_AVIS => 'warning',
            self::TYPE_NOUVELLE_OFFRE => 'info',
            self::TYPE_COMPTE_SUSPENDU, self::TYPE_OFFRE_SUPPRIMEE_ADMIN, self::TYPE_CANDIDATURE_SUPPRIMEE_ADMIN => 'warning',
            default => 'accent',
        };
    }
}