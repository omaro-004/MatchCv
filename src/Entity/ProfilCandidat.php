<?php

namespace App\Entity;

use App\Repository\ProfilCandidatRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: ProfilCandidatRepository::class)]
#[ORM\Table(name: 'profil_candidat')]
class ProfilCandidat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_profil', type: 'integer')]
    private ?int $id = null;

    /**
     * Clé étrangère 1:1 vers User.
     * Un ProfilCandidat ne peut exister sans User (NOT NULL).
     */
    #[ORM\OneToOne(inversedBy: 'profilCandidat', targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_user', referencedColumnName: 'id_user', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(name: 'nom_complet', type: 'string', length: 150)]
    #[Assert\NotBlank(message: 'Le nom complet est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 150,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'
    )]
    private string $nomComplet = '';

    #[ORM\Column(name: 'num_tel', type: 'string', length: 20, nullable: true)]
    #[Assert\Regex(
        pattern: '/^\+?[0-9\s\-\(\)]{7,20}$/',
        message: 'Le numéro de téléphone {{ value }} n\'est pas valide.'
    )]
    private ?string $numTel = null;

    /**
     * Chemin relatif vers la photo de profil stockée sur le serveur.
     * Ex: 'uploads/photos/abc123.jpg'
     */
    #[ORM\Column(name: 'photo', type: 'string', length: 500, nullable: true)]
    private ?string $photo = null;

    /**
     * Chemin relatif vers le fichier CV PDF.
     * Ex: 'uploads/cv/abc123.pdf'
     * Obligatoire pour postuler — règle RM-U06.
     */
    #[ORM\Column(name: 'cv', type: 'string', length: 500, nullable: true)]
    private ?string $cv = null;

    #[ORM\Column(name: 'bio', type: 'text', nullable: true)]
    #[Assert\Length(max: 1000, maxMessage: 'La bio ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $bio = null;

    #[ORM\Column(name: 'localisation', type: 'string', length: 255, nullable: true)]
    private ?string $localisation = null;

    /**
     * Type de contrat recherché.
     * Valeurs : 'stage' | 'emploi' | 'les_deux'
     */
    #[ORM\Column(
        name: 'type_contrat',
        type: 'string',
        length: 20,
        columnDefinition: "ENUM('stage', 'emploi', 'les_deux') NOT NULL DEFAULT 'stage'"
    )]
    #[Assert\Choice(
        choices: ['stage', 'emploi', 'les_deux'],
        message: 'Le type de contrat doit être : stage, emploi ou les_deux.'
    )]
    private string $typeContrat = 'stage';

    // ---------------------------------------------------------------
    // Getters & Setters
    // ---------------------------------------------------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getNomComplet(): string
    {
        return $this->nomComplet;
    }

    public function setNomComplet(string $nomComplet): static
    {
        $this->nomComplet = $nomComplet;
        return $this;
    }

    public function getNumTel(): ?string
    {
        return $this->numTel;
    }

    public function setNumTel(?string $numTel): static
    {
        $this->numTel = $numTel;
        return $this;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): static
    {
        $this->photo = $photo;
        return $this;
    }

    public function getCv(): ?string
    {
        return $this->cv;
    }

    public function setCv(?string $cv): static
    {
        $this->cv = $cv;
        return $this;
    }

    /**
     * Vérifie qu'un CV est uploadé — prérequis avant candidature (règle RM-U06).
     */
    public function hasCv(): bool
    {
        return $this->cv !== null && $this->cv !== '';
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): static
    {
        $this->bio = $bio;
        return $this;
    }

    public function getLocalisation(): ?string
    {
        return $this->localisation;
    }

    public function setLocalisation(?string $localisation): static
    {
        $this->localisation = $localisation;
        return $this;
    }

    public function getTypeContrat(): string
    {
        return $this->typeContrat;
    }

    public function setTypeContrat(string $typeContrat): static
    {
        if (!in_array($typeContrat, ['stage', 'emploi', 'les_deux'])) {
            throw new \InvalidArgumentException("Type de contrat invalide : $typeContrat.");
        }
        $this->typeContrat = $typeContrat;
        return $this;
    }

    /**
     * Retourne le libellé lisible du type de contrat.
     */
    public function getTypeContratLabel(): string
    {
        return match($this->typeContrat) {
            'stage'    => 'Stage',
            'emploi'   => 'Emploi',
            'les_deux' => 'Stage & Emploi',
            default    => $this->typeContrat,
        };
    }
}