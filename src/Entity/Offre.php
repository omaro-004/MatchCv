<?php

namespace App\Entity;

use App\Repository\OffreRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: OffreRepository::class)]
#[ORM\Table(name: 'offre')]
#[ORM\HasLifecycleCallbacks]
class Offre
{
    public const STATUT_ACTIVE = 'active';
    public const STATUT_ARCHIVEE = 'archivee';

    public const TYPES_CONTRAT = ['stage', 'emploi'];
    public const MODES_TRAVAIL = ['sur_site', 'teletravail', 'hybride'];
    public const MOTIFS_ARCHIVAGE = ['duree_terminee', 'poste_pourvu', 'autre'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_offre', type: 'integer')]
    private ?int $id = null;

    /**
     * Clé étrangère vers ProfilEntreprise — l'entreprise auteure de l'offre (RM-O01).
     */
    #[ORM\ManyToOne(targetEntity: ProfilEntreprise::class, inversedBy: 'offres')]
    #[ORM\JoinColumn(name: 'id_entreprise', referencedColumnName: 'id_profil', nullable: false, onDelete: 'CASCADE')]
    private ?ProfilEntreprise $entreprise = null;

    #[ORM\Column(name: 'titre', type: 'string', length: 255)]
    #[Assert\NotBlank(message: "Le titre de l'offre est obligatoire.")]
    #[Assert\Length(
        min: 5,
        max: 255,
        minMessage: 'Le titre doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.'
    )]
    private string $titre = '';

    #[ORM\Column(name: 'description', type: 'text')]
    #[Assert\NotBlank(message: 'La description est obligatoire.')]
    #[Assert\Length(min: 20, minMessage: 'La description doit contenir au moins {{ limit }} caractères.')]
    private string $description = '';

    #[ORM\Column(name: 'competences_requises', type: 'text', nullable: true)]
    private ?string $competencesRequises = null;

    #[ORM\Column(
        name: 'type_contrat',
        type: 'string',
        length: 20,
        columnDefinition: "ENUM('stage', 'emploi') NOT NULL DEFAULT 'stage'"
    )]
    #[Assert\Choice(choices: self::TYPES_CONTRAT, message: 'Type de contrat invalide.')]
    private string $typeContrat = 'stage';

    #[ORM\Column(name: 'duree_contrat', type: 'string', length: 100, nullable: true)]
    #[Assert\Length(max: 100, maxMessage: 'La durée ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $dureeContrat = null;

    #[ORM\Column(name: 'localisation', type: 'string', length: 255)]
    #[Assert\NotBlank(message: 'La localisation est obligatoire.')]
    private string $localisation = '';

    #[ORM\Column(
        name: 'mode_travail',
        type: 'string',
        length: 20,
        columnDefinition: "ENUM('sur_site', 'teletravail', 'hybride') NOT NULL DEFAULT 'sur_site'"
    )]
    #[Assert\Choice(choices: self::MODES_TRAVAIL, message: 'Mode de travail invalide.')]
    private string $modeTravail = 'sur_site';

    #[ORM\Column(name: 'salaire', type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero(message: 'Le salaire doit être un nombre positif.')]
    private ?string $salaire = null;

    #[ORM\Column(name: 'date_publication', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $datePublication = null;

    #[ORM\Column(name: 'date_debut', type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(
        name: 'statut',
        type: 'string',
        length: 20,
        columnDefinition: "ENUM('active', 'archivee') NOT NULL DEFAULT 'active'"
    )]
    private string $statut = self::STATUT_ACTIVE;

    #[ORM\Column(
        name: 'motif_archivage',
        type: 'string',
        length: 30,
        nullable: true,
        columnDefinition: "ENUM('duree_terminee', 'poste_pourvu', 'autre') DEFAULT NULL"
    )]
    private ?string $motifArchivage = null;

    #[ORM\Column(name: 'motif_archivage_details', type: 'text', nullable: true)]
    private ?string $motifArchivageDetails = null;

    #[ORM\Column(name: 'date_archivage', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateArchivage = null;

    /**
     * NOUVEAU — Horodatage de la dernière modification de l'offre. Utilisé
     * par MatchingPreviewService pour invalider le cache de score IA d'une
     * offre modifiée (titre, description, compétences requises...).
     * Mis à jour automatiquement par Doctrine à chaque UPDATE.
     */
    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * Candidatures reçues pour cette offre (nouveau — fondation du module stats).
     */
    #[ORM\OneToMany(mappedBy: 'offre', targetEntity: Candidature::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $candidatures;

    public function __construct()
    {
        $this->candidatures = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function setDatePublicationOnCreate(): void
    {
        if ($this->datePublication === null) {
            $this->datePublication = new \DateTimeImmutable();
        }
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtOnUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // ---------------------------------------------------------------
    // Getters & Setters
    // ---------------------------------------------------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntreprise(): ?ProfilEntreprise
    {
        return $this->entreprise;
    }

    public function setEntreprise(?ProfilEntreprise $entreprise): static
    {
        $this->entreprise = $entreprise;
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

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getCompetencesRequises(): ?string
    {
        return $this->competencesRequises;
    }

    public function setCompetencesRequises(?string $competencesRequises): static
    {
        $this->competencesRequises = $competencesRequises;
        return $this;
    }

    public function getCompetencesRequisesArray(): array
    {
        if ($this->competencesRequises === null || trim($this->competencesRequises) === '') {
            return [];
        }

        $skills = array_map('trim', explode(',', $this->competencesRequises));

        return array_values(array_filter($skills, fn (string $s) => $s !== ''));
    }

    public function getTypeContrat(): string
    {
        return $this->typeContrat;
    }

    public function setTypeContrat(string $typeContrat): static
    {
        if (!in_array($typeContrat, self::TYPES_CONTRAT, true)) {
            throw new \InvalidArgumentException("Type de contrat invalide : $typeContrat.");
        }
        $this->typeContrat = $typeContrat;
        return $this;
    }

    public function getTypeContratLabel(): string
    {
        return match ($this->typeContrat) {
            'stage' => 'Stage',
            'emploi' => 'Emploi',
            default => $this->typeContrat,
        };
    }

    public function getDureeContrat(): ?string
    {
        return $this->dureeContrat;
    }

    public function setDureeContrat(?string $dureeContrat): static
    {
        $this->dureeContrat = $dureeContrat;
        return $this;
    }

    public function getLocalisation(): string
    {
        return $this->localisation;
    }

    public function setLocalisation(string $localisation): static
    {
        $this->localisation = $localisation;
        return $this;
    }

    public function getModeTravail(): string
    {
        return $this->modeTravail;
    }

    public function setModeTravail(string $modeTravail): static
    {
        if (!in_array($modeTravail, self::MODES_TRAVAIL, true)) {
            throw new \InvalidArgumentException("Mode de travail invalide : $modeTravail.");
        }
        $this->modeTravail = $modeTravail;
        return $this;
    }

    public function getModeTravailLabel(): string
    {
        return match ($this->modeTravail) {
            'sur_site' => 'Sur site',
            'teletravail' => 'Télétravail',
            'hybride' => 'Hybride',
            default => $this->modeTravail,
        };
    }

    public function getSalaire(): ?string
    {
        return $this->salaire;
    }

    public function setSalaire(?string $salaire): static
    {
        $this->salaire = $salaire;
        return $this;
    }

    public function getDatePublication(): ?\DateTimeImmutable
    {
        return $this->datePublication;
    }

    public function setDatePublication(\DateTimeImmutable $datePublication): static
    {
        $this->datePublication = $datePublication;
        return $this;
    }

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTimeInterface $dateDebut): static
    {
        $this->dateDebut = $dateDebut;
        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function isActive(): bool
    {
        return $this->statut === self::STATUT_ACTIVE;
    }

    public function isArchivee(): bool
    {
        return $this->statut === self::STATUT_ARCHIVEE;
    }

    public function getMotifArchivage(): ?string
    {
        return $this->motifArchivage;
    }

    public function getMotifArchivageLabel(): string
    {
        return match ($this->motifArchivage) {
            'duree_terminee' => 'Durée du contrat terminée',
            'poste_pourvu' => 'Poste pourvu',
            'autre' => 'Autre motif',
            default => '—',
        };
    }

    public function getMotifArchivageDetails(): ?string
    {
        return $this->motifArchivageDetails;
    }

    public function getDateArchivage(): ?\DateTimeImmutable
    {
        return $this->dateArchivage;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function archiver(string $motif, ?string $details = null): static
    {
        if (!in_array($motif, self::MOTIFS_ARCHIVAGE, true)) {
            throw new \InvalidArgumentException("Motif d'archivage invalide : $motif.");
        }

        $this->statut = self::STATUT_ARCHIVEE;
        $this->motifArchivage = $motif;
        $this->motifArchivageDetails = $details;
        $this->dateArchivage = new \DateTimeImmutable();

        return $this;
    }

    /**
     * @return Collection<int, Candidature>
     */
    public function getCandidatures(): Collection
    {
        return $this->candidatures;
    }

    public function addCandidature(Candidature $candidature): static
    {
        if (!$this->candidatures->contains($candidature)) {
            $this->candidatures->add($candidature);
            $candidature->setOffre($this);
        }
        return $this;
    }

    public function removeCandidature(Candidature $candidature): static
    {
        if ($this->candidatures->removeElement($candidature)) {
            if ($candidature->getOffre() === $this) {
                $candidature->setOffre(null);
            }
        }
        return $this;
    }
}