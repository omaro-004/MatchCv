<?php

namespace App\Entity;

use App\Repository\AvisEntrepriseRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AvisEntrepriseRepository::class)]
#[ORM\Table(name: 'avis_entreprise')]
#[ORM\UniqueConstraint(name: 'uniq_entreprise_candidat', columns: ['id_entreprise', 'id_candidat'])]
#[ORM\HasLifecycleCallbacks]
class AvisEntreprise
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_avis', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ProfilEntreprise::class)]
    #[ORM\JoinColumn(name: 'id_entreprise', referencedColumnName: 'id_profil', nullable: false, onDelete: 'CASCADE')]
    private ?ProfilEntreprise $entreprise = null;

    #[ORM\ManyToOne(targetEntity: ProfilCandidat::class)]
    #[ORM\JoinColumn(name: 'id_candidat', referencedColumnName: 'id_profil', nullable: false, onDelete: 'CASCADE')]
    private ?ProfilCandidat $candidat = null;

    #[ORM\Column(name: 'note', type: 'integer')]
    #[Assert\Range(min: 1, max: 5, notInRangeMessage: 'La note doit être comprise entre {{ min }} et {{ max }}.')]
    private int $note = 5;

    #[ORM\Column(name: 'commentaire', type: 'text', nullable: true)]
    #[Assert\Length(max: 1000, maxMessage: 'Le commentaire ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $commentaire = null;

    #[ORM\Column(name: 'date_avis', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $dateAvis = null;

    #[ORM\PrePersist]
    public function setDateAvisOnCreate(): void
    {
        if ($this->dateAvis === null) {
            $this->dateAvis = new \DateTimeImmutable();
        }
    }

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

    public function getCandidat(): ?ProfilCandidat
    {
        return $this->candidat;
    }

    public function setCandidat(?ProfilCandidat $candidat): static
    {
        $this->candidat = $candidat;
        return $this;
    }

    public function getNote(): int
    {
        return $this->note;
    }

    public function setNote(int $note): static
    {
        $this->note = max(1, min(5, $note));
        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;
        return $this;
    }

    public function getDateAvis(): ?\DateTimeImmutable
    {
        return $this->dateAvis;
    }

    public function setDateAvis(\DateTimeImmutable $dateAvis): static
    {
        $this->dateAvis = $dateAvis;
        return $this;
    }
}