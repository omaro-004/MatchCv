<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\EvenementRepository;

#[ORM\Entity(repositoryClass: EvenementRepository::class)]
class Evenement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: \App\Entity\User::class)]
    #[ORM\JoinColumn(name: 'id_entreprise', referencedColumnName: 'id_user', nullable: false, onDelete: 'CASCADE')]
    private ?\App\Entity\User $entreprise = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $titre;

    #[ORM\Column(type: 'text')]
    private string $description;

    #[ORM\Column(type: 'boolean')]
    private bool $isOnline = false;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $lieu = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $debutAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $finAt;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $capacite = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isAnnule = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $noteAnnulation = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isArchive = false;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntreprise(): ?\App\Entity\User
    {
        return $this->entreprise;
    }

    public function setEntreprise(\App\Entity\User $entreprise): self
    {
        $this->entreprise = $entreprise;
        return $this;
    }

    public function getTitre(): string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): self
    {
        $this->titre = $titre;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function isOnline(): bool
    {
        return $this->isOnline;
    }

    public function setIsOnline(bool $isOnline): self
    {
        $this->isOnline = $isOnline;
        return $this;
    }

    public function getLieu(): ?string
    {
        return $this->lieu;
    }

    public function setLieu(?string $lieu): self
    {
        $this->lieu = $lieu;
        return $this;
    }

    public function getDebutAt(): \DateTimeInterface
    {
        return $this->debutAt;
    }

    public function setDebutAt(\DateTimeInterface $debutAt): self
    {
        $this->debutAt = $debutAt;
        return $this;
    }

    public function getFinAt(): \DateTimeInterface
    {
        return $this->finAt;
    }

    public function setFinAt(\DateTimeInterface $finAt): self
    {
        $this->finAt = $finAt;
        return $this;
    }

    public function getCapacite(): ?int
    {
        return $this->capacite;
    }

    public function setCapacite(?int $capacite): self
    {
        $this->capacite = $capacite;
        return $this;
    }

    public function isAnnule(): bool
    {
        return $this->isAnnule;
    }

    public function setIsAnnule(bool $isAnnule): self
    {
        $this->isAnnule = $isAnnule;
        return $this;
    }

    public function getNoteAnnulation(): ?string
    {
        return $this->noteAnnulation;
    }

    public function setNoteAnnulation(?string $noteAnnulation): self
    {
        $this->noteAnnulation = $noteAnnulation;
        return $this;
    }

    public function isArchive(): bool
    {
        return $this->isArchive;
    }

    public function setIsArchive(bool $isArchive): self
    {
        $this->isArchive = $isArchive;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }
}
