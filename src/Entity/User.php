<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'user')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(
    fields: ['email'],
    message: 'Cette adresse email est déjà utilisée. Veuillez vous connecter ou utiliser une autre adresse.'
)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_user', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'email', type: 'string', length: 255, unique: true)]
    #[Assert\NotBlank(message: "L'email est obligatoire.")]
    #[Assert\Email(message: "L'adresse email '{{ value }}' n'est pas valide.")]
    #[Assert\Length(max: 255, maxMessage: "L'email ne peut pas dépasser {{ limit }} caractères.")]
    private string $email = '';

    #[ORM\Column(name: 'mdp', type: 'string', length: 255)]
    private string $password = '';

    #[ORM\Column(name: 'role', type: 'string', length: 20, columnDefinition: "ENUM('candidat', 'entreprise', 'admin') NOT NULL")]
    private string $role = 'candidat';

    #[ORM\Column(name: 'date_inscri', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $dateInscri = null;

    #[ORM\Column(name: 'lien_linkedin', type: 'string', length: 500, nullable: true)]
    #[Assert\Url(message: "Le lien LinkedIn '{{ value }}' n'est pas une URL valide.")]
    private ?string $lienLinkedin = null;

    #[ORM\Column(name: 'autres_liens', type: 'text', nullable: true)]
    private ?string $autresLiens = null;

    #[ORM\Column(name: 'face_descriptor', type: 'text', nullable: true)]
    private ?string $faceDescriptor = null;

    #[ORM\Column(name: 'face_id_enabled', type: 'boolean', options: ['default' => false])]
    private bool $faceIdEnabled = false;

    #[ORM\Column(name: 'inscription_status', type: 'string', length: 30, options: ['default' => 'complete'])]
    private string $inscriptionStatus = 'complete';

    #[ORM\Column(name: 'oauth_provider', type: 'string', length: 20, nullable: true)]
    private ?string $oauthProvider = null;

    #[ORM\Column(name: 'oauth_id', type: 'string', length: 255, nullable: true)]
    private ?string $oauthId = null;

    /**
     * Secret TOTP (base32), généré lors de l'activation de l'application
     * d'authentification. Permet de récupérer le mot de passe sans email.
     */
    #[ORM\Column(name: 'totp_secret', type: 'string', length: 255, nullable: true)]
    private ?string $totpSecret = null;

    /**
     * true si l'utilisateur a confirmé au moins une fois un code valide
     * après avoir scanné le QR code (donc l'application est bien configurée).
     */
    #[ORM\Column(name: 'totp_enabled', type: 'boolean', options: ['default' => false])]
    private bool $totpEnabled = false;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: ProfilCandidat::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private ?ProfilCandidat $profilCandidat = null;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: ProfilEntreprise::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private ?ProfilEntreprise $profilEntreprise = null;

    #[ORM\PrePersist]
    public function setDateInscriOnCreate(): void
    {
        if ($this->dateInscri === null) {
            $this->dateInscri = new \DateTimeImmutable();
        }
    }

    public function getRoles(): array
    {
        return ['ROLE_' . strtoupper($this->role)];
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function eraseCredentials(): void
    {
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        if (!in_array($role, ['candidat', 'entreprise', 'admin'])) {
            throw new \InvalidArgumentException("Rôle invalide : $role. Valeurs autorisées : candidat, entreprise, admin.");
        }
        $this->role = $role;
        return $this;
    }

    public function getDateInscri(): ?\DateTimeImmutable
    {
        return $this->dateInscri;
    }

    public function setDateInscri(\DateTimeImmutable $dateInscri): static
    {
        $this->dateInscri = $dateInscri;
        return $this;
    }

    public function getLienLinkedin(): ?string
    {
        return $this->lienLinkedin;
    }

    public function setLienLinkedin(?string $lienLinkedin): static
    {
        $this->lienLinkedin = $lienLinkedin;
        return $this;
    }

    public function getAutresLiens(): ?string
    {
        return $this->autresLiens;
    }

    public function setAutresLiens(?string $autresLiens): static
    {
        $this->autresLiens = $autresLiens;
        return $this;
    }

    public function getProfilCandidat(): ?ProfilCandidat
    {
        return $this->profilCandidat;
    }

    public function setProfilCandidat(?ProfilCandidat $profilCandidat): static
    {
        if ($profilCandidat !== null && $profilCandidat->getUser() !== $this) {
            $profilCandidat->setUser($this);
        }
        $this->profilCandidat = $profilCandidat;
        return $this;
    }

    public function getProfilEntreprise(): ?ProfilEntreprise
    {
        return $this->profilEntreprise;
    }

    public function setProfilEntreprise(?ProfilEntreprise $profilEntreprise): static
    {
        if ($profilEntreprise !== null && $profilEntreprise->getUser() !== $this) {
            $profilEntreprise->setUser($this);
        }
        $this->profilEntreprise = $profilEntreprise;
        return $this;
    }

    public function getFaceDescriptor(): ?string
    {
        return $this->faceDescriptor;
    }

    public function setFaceDescriptor(?string $faceDescriptor): static
    {
        $this->faceDescriptor = $faceDescriptor;
        return $this;
    }

    public function getFaceDescriptorArray(): ?array
    {
        if ($this->faceDescriptor === null) {
            return null;
        }
        $decoded = json_decode($this->faceDescriptor, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function isFaceIdEnabled(): bool
    {
        return $this->faceIdEnabled;
    }

    public function setFaceIdEnabled(bool $faceIdEnabled): static
    {
        $this->faceIdEnabled = $faceIdEnabled;
        return $this;
    }

    public function hasFaceDescriptor(): bool
    {
        return $this->faceDescriptor !== null && $this->faceDescriptor !== '';
    }

    public function getInscriptionStatus(): string
    {
        return $this->inscriptionStatus;
    }

    public function setInscriptionStatus(string $status): static
    {
        if (!in_array($status, ['pending_face_id', 'pending_profile_completion', 'complete'])) {
            throw new \InvalidArgumentException("Statut d'inscription invalide : $status.");
        }
        $this->inscriptionStatus = $status;
        return $this;
    }

    public function isInscriptionComplete(): bool
    {
        return $this->inscriptionStatus === 'complete';
    }

    public function getOauthProvider(): ?string
    {
        return $this->oauthProvider;
    }

    public function setOauthProvider(?string $oauthProvider): static
    {
        $this->oauthProvider = $oauthProvider;
        return $this;
    }

    public function getOauthId(): ?string
    {
        return $this->oauthId;
    }

    public function setOauthId(?string $oauthId): static
    {
        $this->oauthId = $oauthId;
        return $this;
    }

    public function isOAuthAccount(): bool
    {
        return $this->oauthProvider !== null && $this->oauthId !== null;
    }

    public function isCandidat(): bool
    {
        return $this->role === 'candidat';
    }

    public function isEntreprise(): bool
    {
        return $this->role === 'entreprise';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function getTotpSecret(): ?string
    {
        return $this->totpSecret;
    }

    public function setTotpSecret(?string $totpSecret): static
    {
        $this->totpSecret = $totpSecret;
        return $this;
    }

    public function isTotpEnabled(): bool
    {
        return $this->totpEnabled;
    }

    public function setTotpEnabled(bool $totpEnabled): static
    {
        $this->totpEnabled = $totpEnabled;
        return $this;
    }
}