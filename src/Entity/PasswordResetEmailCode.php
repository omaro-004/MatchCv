<?php

namespace App\Entity;

use App\Repository\PasswordResetEmailCodeRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * PasswordResetEmailCode
 *
 * Code à 6 chiffres envoyé par email pour la récupération de mot de passe
 * (alternative à l'application d'authentification TOTP et au lien de
 * réinitialisation par email — toutes ces méthodes coexistent).
 *
 * Sécurité :
 *  - Seul le hash SHA-256 du code est stocké (jamais le code en clair).
 *  - Le code expire après 15 minutes.
 *  - Usage unique (used = true après vérification réussie).
 *  - Limité à 5 tentatives de saisie (brute-force).
 */
#[ORM\Entity(repositoryClass: PasswordResetEmailCodeRepository::class)]
#[ORM\Table(name: 'password_reset_email_code')]
class PasswordResetEmailCode
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_user', referencedColumnName: 'id_user', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(name: 'code_hash', type: 'string', length: 255)]
    private string $codeHash = '';

    #[ORM\Column(name: 'requested_at', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $requestedAt = null;

    #[ORM\Column(name: 'expires_at', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(name: 'used', type: 'boolean', options: ['default' => false])]
    private bool $used = false;

    #[ORM\Column(name: 'attempts', type: 'integer', options: ['default' => 0])]
    private int $attempts = 0;

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

    public function getCodeHash(): string
    {
        return $this->codeHash;
    }

    public function setCodeHash(string $codeHash): static
    {
        $this->codeHash = $codeHash;
        return $this;
    }

    public function getRequestedAt(): ?\DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function setRequestedAt(\DateTimeImmutable $requestedAt): static
    {
        $this->requestedAt = $requestedAt;
        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function isUsed(): bool
    {
        return $this->used;
    }

    public function setUsed(bool $used): static
    {
        $this->used = $used;
        return $this;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function setAttempts(int $attempts): static
    {
        $this->attempts = $attempts;
        return $this;
    }

    public function incrementAttempts(): static
    {
        $this->attempts++;
        return $this;
    }

    /**
     * Un code est valide s'il n'a jamais été utilisé, qu'il n'est pas expiré
     * (15 min) et que le nombre de tentatives (5 max) n'est pas dépassé.
     */
    public function isValid(): bool
    {
        return !$this->used
            && $this->attempts < 5
            && $this->expiresAt !== null
            && $this->expiresAt > new \DateTimeImmutable();
    }
}