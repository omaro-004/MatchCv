<?php

namespace App\Entity;

use App\Repository\ProfilEntrepriseRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProfilEntrepriseRepository::class)]
#[ORM\Table(name: 'profil_entreprise')]
class ProfilEntreprise
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_profil', type: 'integer')]
    private ?int $id = null;

    /**
     * Clé étrangère 1:1 vers User.
     * Un ProfilEntreprise ne peut exister sans User (NOT NULL).
     */
    #[ORM\OneToOne(inversedBy: 'profilEntreprise', targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_user', referencedColumnName: 'id_user', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(name: 'raison_sociale', type: 'string', length: 255)]
    #[Assert\NotBlank(message: 'La raison sociale est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'La raison sociale doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'La raison sociale ne peut pas dépasser {{ limit }} caractères.'
    )]
    private string $raisonSociale = '';

    #[ORM\Column(name: 'num_tel', type: 'string', length: 20, nullable: true)]
    #[Assert\Regex(
        pattern: '/^\+?[0-9\s\-\(\)]{7,20}$/',
        message: 'Le numéro de téléphone {{ value }} n\'est pas valide.'
    )]
    private ?string $numTel = null;

    #[ORM\Column(name: 'logo', type: 'string', length: 500, nullable: true)]
    private ?string $logo = null;

    #[ORM\Column(name: 'rne', type: 'string', length: 50, nullable: true)]
    #[Assert\Length(max: 50, maxMessage: 'Le RNE ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $rne = null;

    #[ORM\Column(name: 'type', type: 'string', length: 100, nullable: true)]
    #[Assert\Length(max: 100, maxMessage: 'Le type d\'entreprise ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $type = null;

    #[ORM\Column(name: 'secteur', type: 'string', length: 150, nullable: true)]
    #[Assert\Length(max: 150, maxMessage: 'Le secteur ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $secteur = null;

    #[ORM\Column(name: 'localisation', type: 'string', length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'La localisation ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $localisation = null;

    #[ORM\Column(name: 'lien_site', type: 'string', length: 500, nullable: true)]
    #[Assert\Url(message: "Le lien du site '{{ value }}' n'est pas une URL valide.")]
    private ?string $lienSite = null;

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

    public function getRaisonSociale(): string
    {
        return $this->raisonSociale;
    }

    public function setRaisonSociale(string $raisonSociale): static
    {
        $this->raisonSociale = $raisonSociale;
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

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function setLogo(?string $logo): static
    {
        $this->logo = $logo;
        return $this;
    }

    public function getRne(): ?string
    {
        return $this->rne;
    }

    public function setRne(?string $rne): static
    {
        $this->rne = $rne;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getSecteur(): ?string
    {
        return $this->secteur;
    }

    public function setSecteur(?string $secteur): static
    {
        $this->secteur = $secteur;
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

    public function getLienSite(): ?string
    {
        return $this->lienSite;
    }

    public function setLienSite(?string $lienSite): static
    {
        $this->lienSite = $lienSite;
        return $this;
    }
}
