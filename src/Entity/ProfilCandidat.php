<?php

namespace App\Entity;

use App\Repository\ProfilCandidatRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

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
    // NOUVEAU — Champs "profil enrichi par IA" (parsing du CV PDF)
    // Remplis automatiquement par App\Service\CvAiProfileAnalyzer.
    // Jamais saisis manuellement par le candidat dans le formulaire
    // d'inscription — uniquement dérivés du CV + des données du form.
    // ---------------------------------------------------------------

    #[ORM\Column(name: 'annees_experience', type: 'integer', nullable: true)]
    private ?int $anneesExperience = null;

    /** Stocké en base sous forme de JSON (tableau de chaînes). */
    #[ORM\Column(name: 'langues_parlees', type: 'text', nullable: true)]
    private ?string $languesParlees = null;

    /** Stocké en base sous forme de JSON (tableau de chaînes). */
    #[ORM\Column(name: 'competences_techniques', type: 'text', nullable: true)]
    private ?string $competencesTechniques = null;

    /** Stocké en base sous forme de JSON (tableau de chaînes). */
    #[ORM\Column(name: 'formations', type: 'text', nullable: true)]
    private ?string $formations = null;

    /** Stocké en base sous forme de JSON (tableau de chaînes). */
    #[ORM\Column(name: 'experiences_professionnelles', type: 'text', nullable: true)]
    private ?string $experiencesProfessionnelles = null;

    /** Stocké en base sous forme de JSON (tableau de chaînes) — projets académiques/personnels. */
    #[ORM\Column(name: 'projets_academiques', type: 'text', nullable: true)]
    private ?string $projetsAcademiques = null;

    /** Stocké en base sous forme de JSON (tableau de chaînes) — soft skills. */
    #[ORM\Column(name: 'soft_skills', type: 'text', nullable: true)]
    private ?string $softSkills = null;

    #[ORM\Column(name: 'resume_ia', type: 'text', nullable: true)]
    private ?string $resumeIa = null;

    #[ORM\Column(name: 'cv_ai_parsed_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $cvAiParsedAt = null;

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

    // ---------------------------------------------------------------
    // NOUVEAU — Accesseurs "profil enrichi par IA"
    // ---------------------------------------------------------------

    public function getAnneesExperience(): ?int
    {
        return $this->anneesExperience;
    }

    public function setAnneesExperience(?int $anneesExperience): static
    {
        $this->anneesExperience = $anneesExperience;
        return $this;
    }

    public function getLanguesParlees(): ?string
    {
        return $this->languesParlees;
    }

    public function setLanguesParlees(?string $languesParlees): static
    {
        $this->languesParlees = $languesParlees;
        return $this;
    }

    /** @return string[] */
    public function getLanguesParleesArray(): array
    {
        return $this->decodeJsonArray($this->languesParlees);
    }

    /** @param string[] $values */
    public function setLanguesParleesArray(array $values): static
    {
        $this->languesParlees = $this->encodeJsonArray($values);
        return $this;
    }

    public function getCompetencesTechniques(): ?string
    {
        return $this->competencesTechniques;
    }

    public function setCompetencesTechniques(?string $competencesTechniques): static
    {
        $this->competencesTechniques = $competencesTechniques;
        return $this;
    }

    /** @return string[] */
    public function getCompetencesTechniquesArray(): array
    {
        return $this->decodeJsonArray($this->competencesTechniques);
    }

    /** @param string[] $values */
    public function setCompetencesTechniquesArray(array $values): static
    {
        $this->competencesTechniques = $this->encodeJsonArray($values);
        return $this;
    }

    public function getFormations(): ?string
    {
        return $this->formations;
    }

    public function setFormations(?string $formations): static
    {
        $this->formations = $formations;
        return $this;
    }

    /** @return string[] */
    public function getFormationsArray(): array
    {
        return $this->decodeJsonArray($this->formations);
    }

    /** @param string[] $values */
    public function setFormationsArray(array $values): static
    {
        $this->formations = $this->encodeJsonArray($values);
        return $this;
    }

    public function getExperiencesProfessionnelles(): ?string
    {
        return $this->experiencesProfessionnelles;
    }

    public function setExperiencesProfessionnelles(?string $experiencesProfessionnelles): static
    {
        $this->experiencesProfessionnelles = $experiencesProfessionnelles;
        return $this;
    }

    /** @return string[] */
    public function getExperiencesProfessionnellesArray(): array
    {
        return $this->decodeJsonArray($this->experiencesProfessionnelles);
    }

    /** @param string[] $values */
    public function setExperiencesProfessionnellesArray(array $values): static
    {
        $this->experiencesProfessionnelles = $this->encodeJsonArray($values);
        return $this;
    }

    public function getProjetsAcademiques(): ?string
    {
        return $this->projetsAcademiques;
    }

    public function setProjetsAcademiques(?string $projetsAcademiques): static
    {
        $this->projetsAcademiques = $projetsAcademiques;
        return $this;
    }

    /** @return string[] */
    public function getProjetsAcademiquesArray(): array
    {
        return $this->decodeJsonArray($this->projetsAcademiques);
    }

    /** @param string[] $values */
    public function setProjetsAcademiquesArray(array $values): static
    {
        $this->projetsAcademiques = $this->encodeJsonArray($values);
        return $this;
    }

    public function getSoftSkills(): ?string
    {
        return $this->softSkills;
    }

    public function setSoftSkills(?string $softSkills): static
    {
        $this->softSkills = $softSkills;
        return $this;
    }

    /** @return string[] */
    public function getSoftSkillsArray(): array
    {
        return $this->decodeJsonArray($this->softSkills);
    }

    /** @param string[] $values */
    public function setSoftSkillsArray(array $values): static
    {
        $this->softSkills = $this->encodeJsonArray($values);
        return $this;
    }

    public function getResumeIa(): ?string
    {
        return $this->resumeIa;
    }

    public function setResumeIa(?string $resumeIa): static
    {
        $this->resumeIa = $resumeIa;
        return $this;
    }

    public function getCvAiParsedAt(): ?\DateTimeImmutable
    {
        return $this->cvAiParsedAt;
    }

    public function setCvAiParsedAt(?\DateTimeImmutable $cvAiParsedAt): static
    {
        $this->cvAiParsedAt = $cvAiParsedAt;
        return $this;
    }

    /**
     * true si le CV a déjà été analysé au moins une fois par l'IA.
     */
    public function hasAiParsedData(): bool
    {
        return $this->cvAiParsedAt !== null;
    }

    // ---------------------------------------------------------------
    // Helpers privés JSON
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