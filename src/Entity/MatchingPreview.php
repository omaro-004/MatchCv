<?php

namespace App\Entity;

use App\Repository\MatchingPreviewRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * MatchingPreview
 *
 * Cache le score IA "aperçu" calculé par MatchingService entre un candidat
 * et une offre à laquelle il n'a PAS encore postulé. Sert de base à la
 * liste "Offres recommandées pour vous" du dashboard, en garantissant que
 * le score affiché est EXACTEMENT le même moteur IA que celui utilisé au
 * moment du dépôt réel d'une candidature (App\Service\MatchingService).
 *
 * Le cache est invalidé automatiquement si :
 *   - le CV du candidat a été ré-analysé depuis (ProfilCandidat::cvAiParsedAt)
 *   - l'offre a été modifiée depuis (Offre::updatedAt)
 *   - le cache dépasse la durée de fraîcheur définie dans MatchingPreviewService
 */
#[ORM\Entity(repositoryClass: MatchingPreviewRepository::class)]
#[ORM\Table(name: 'matching_preview')]
#[ORM\UniqueConstraint(name: 'uniq_candidat_offre_preview', columns: ['id_candidat', 'id_offre'])]
class MatchingPreview
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_preview', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ProfilCandidat::class)]
    #[ORM\JoinColumn(name: 'id_candidat', referencedColumnName: 'id_profil', nullable: false, onDelete: 'CASCADE')]
    private ?ProfilCandidat $candidat = null;

    #[ORM\ManyToOne(targetEntity: Offre::class)]
    #[ORM\JoinColumn(name: 'id_offre', referencedColumnName: 'id_offre', nullable: false, onDelete: 'CASCADE')]
    private ?Offre $offre = null;

    #[ORM\Column(name: 'score', type: 'integer', nullable: true)]
    private ?int $score = null;

    /** Stocké en JSON. */
    #[ORM\Column(name: 'competences_matchees', type: 'text', nullable: true)]
    private ?string $competencesMatchees = null;

    /** Stocké en JSON. */
    #[ORM\Column(name: 'competences_manquantes', type: 'text', nullable: true)]
    private ?string $competencesManquantes = null;

    #[ORM\Column(name: 'computed_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $computedAt = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getOffre(): ?Offre
    {
        return $this->offre;
    }

    public function setOffre(?Offre $offre): static
    {
        $this->offre = $offre;
        return $this;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(?int $score): static
    {
        $this->score = $score;
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

    public function getComputedAt(): ?\DateTimeImmutable
    {
        return $this->computedAt;
    }

    public function setComputedAt(?\DateTimeImmutable $computedAt): static
    {
        $this->computedAt = $computedAt;
        return $this;
    }

    // ---------------------------------------------------------------
    // Helpers privés JSON (même pattern que ProfilCandidat / Candidature)
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