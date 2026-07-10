<?php

namespace App\Entity;

use App\Repository\CandidatureRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CandidatureRepository::class)]
#[ORM\Table(name: 'candidature')]
#[ORM\UniqueConstraint(name: 'uniq_offre_candidat', columns: ['id_offre', 'id_candidat'])]
#[ORM\HasLifecycleCallbacks]
class Candidature
{
    public const STATUT_EN_ATTENTE = 'en_attente';
    public const STATUT_ACCEPTE = 'accepte';
    public const STATUT_REFUSE = 'refuse';

    public const STATUTS = [self::STATUT_EN_ATTENTE, self::STATUT_ACCEPTE, self::STATUT_REFUSE];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_candidature', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Offre::class, inversedBy: 'candidatures')]
    #[ORM\JoinColumn(name: 'id_offre', referencedColumnName: 'id_offre', nullable: false, onDelete: 'CASCADE')]
    private ?Offre $offre = null;

    #[ORM\ManyToOne(targetEntity: ProfilCandidat::class)]
    #[ORM\JoinColumn(name: 'id_candidat', referencedColumnName: 'id_profil', nullable: false, onDelete: 'CASCADE')]
    private ?ProfilCandidat $candidat = null;

    #[ORM\Column(name: 'cv', type: 'string', length: 500, nullable: true)]
    private ?string $cv = null;

    /**
     * Message de motivation optionnel saisi par le candidat au moment de postuler.
     */
    #[ORM\Column(name: 'lettre_motivation', type: 'text', nullable: true)]
    private ?string $lettreMotivation = null;

    #[ORM\Column(
        name: 'statut',
        type: 'string',
        length: 20,
        columnDefinition: "ENUM('en_attente', 'accepte', 'refuse') NOT NULL DEFAULT 'en_attente'"
    )]
    private string $statut = self::STATUT_EN_ATTENTE;

    #[ORM\Column(name: 'score_matching', type: 'float', nullable: true)]
    private ?float $scoreMatching = null;

    /** Stocké en JSON — compétences requises par l'offre que le candidat possède. */
    #[ORM\Column(name: 'competences_matchees', type: 'text', nullable: true)]
    private ?string $competencesMatchees = null;

    /** Stocké en JSON — compétences requises par l'offre que le candidat ne possède pas. */
    #[ORM\Column(name: 'competences_manquantes', type: 'text', nullable: true)]
    private ?string $competencesManquantes = null;

    #[ORM\Column(name: 'date_candidature', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $dateCandidature = null;

    #[ORM\PrePersist]
    public function setDateCandidatureOnCreate(): void
    {
        if ($this->dateCandidature === null) {
            $this->dateCandidature = new \DateTimeImmutable();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOffre(): ?Offre
    {
        return $this->offre;
    }

    public function setOffre(?Offre $offre): static
    {
        $this->offre = $offre;
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

    public function getCv(): ?string
    {
        return $this->cv;
    }

    public function setCv(?string $cv): static
    {
        $this->cv = $cv;
        return $this;
    }

    public function getLettreMotivation(): ?string
    {
        return $this->lettreMotivation;
    }

    public function setLettreMotivation(?string $lettreMotivation): static
    {
        $this->lettreMotivation = $lettreMotivation;
        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        if (!in_array($statut, self::STATUTS, true)) {
            throw new \InvalidArgumentException("Statut de candidature invalide : $statut.");
        }
        $this->statut = $statut;
        return $this;
    }

    public function getStatutLabel(): string
    {
        return match ($this->statut) {
            self::STATUT_EN_ATTENTE => 'En attente',
            self::STATUT_ACCEPTE => 'Accepté',
            self::STATUT_REFUSE => 'Refusé',
            default => $this->statut,
        };
    }

    public function getScoreMatching(): ?float
    {
        return $this->scoreMatching;
    }

    public function setScoreMatching(?float $scoreMatching): static
    {
        $this->scoreMatching = $scoreMatching;
        return $this;
    }

    public function getCompetencesMatchees(): ?string
    {
        return $this->competencesMatchees;
    }

    public function setCompetencesMatchees(?string $competencesMatchees): static
    {
        $this->competencesMatchees = $competencesMatchees;
        return $this;
    }

    /** @return string[] */
    public function getCompetencesMatcheesArray(): array
    {
        return $this->decodeJsonArray($this->competencesMatchees);
    }

    /** @param string[] $values */
    public function setCompetencesMatcheesArray(array $values): static
    {
        $this->competencesMatchees = $this->encodeJsonArray($values);
        return $this;
    }

    public function getCompetencesManquantes(): ?string
    {
        return $this->competencesManquantes;
    }

    public function setCompetencesManquantes(?string $competencesManquantes): static
    {
        $this->competencesManquantes = $competencesManquantes;
        return $this;
    }

    /** @return string[] */
    public function getCompetencesManquantesArray(): array
    {
        return $this->decodeJsonArray($this->competencesManquantes);
    }

    /** @param string[] $values */
    public function setCompetencesManquantesArray(array $values): static
    {
        $this->competencesManquantes = $this->encodeJsonArray($values);
        return $this;
    }

    public function getDateCandidature(): ?\DateTimeImmutable
    {
        return $this->dateCandidature;
    }

    public function setDateCandidature(\DateTimeImmutable $dateCandidature): static
    {
        $this->dateCandidature = $dateCandidature;
        return $this;
    }

    // ---------------------------------------------------------------
    // Helpers privés JSON (même pattern que ProfilCandidat)
    // ---------------------------------------------------------------

    /** @return string[] */
    private function decodeJsonArray(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? array_values($decoded) : [];
    }

    /** @param string[] $values */
    private function encodeJsonArray(array $values): string
    {
        return json_encode(array_values(array_filter($values, static fn ($v) => is_string($v) && trim($v) !== '')), JSON_UNESCAPED_UNICODE);
    }
}