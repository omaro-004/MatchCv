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

    /**
     * Mot de passe haché en bcrypt (coût 12).
     * Jamais stocké en clair — règle RM-U02.
     * Pour un compte créé via OAuth (GitHub/LinkedIn), ce champ contient
     * un hash aléatoire inutilisable : la connexion ne se fait QUE via OAuth.
     */
    #[ORM\Column(name: 'mdp', type: 'string', length: 255)]
    private string $password = '';

    /**
     * Rôle fonctionnel : 'candidat' | 'entreprise' | 'admin'.
     * Converti en ROLE_CANDIDAT / ROLE_ENTREPRISE / ROLE_ADMIN pour Symfony Security.
     */
    #[ORM\Column(name: 'role', type: 'string', length: 20, columnDefinition: "ENUM('candidat', 'entreprise', 'admin') NOT NULL")]
    private string $role = 'candidat';

    #[ORM\Column(name: 'date_inscri', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $dateInscri = null;

    #[ORM\Column(name: 'lien_linkedin', type: 'string', length: 500, nullable: true)]
    #[Assert\Url(message: "Le lien LinkedIn '{{ value }}' n'est pas une URL valide.")]
    private ?string $lienLinkedin = null;

    #[ORM\Column(name: 'autres_liens', type: 'text', nullable: true)]
    private ?string $autresLiens = null;

    // ---------------------------------------------------------------
    // Champs Face ID — exclusifs au rôle candidat
    // ---------------------------------------------------------------

    #[ORM\Column(name: 'face_descriptor', type: 'text', nullable: true)]
    private ?string $faceDescriptor = null;

    #[ORM\Column(name: 'face_id_enabled', type: 'boolean', options: ['default' => false])]
    private bool $faceIdEnabled = false;

    #[ORM\Column(name: 'inscription_status', type: 'string', length: 30, options: ['default' => 'complete'])]
    private string $inscriptionStatus = 'complete';

    // ---------------------------------------------------------------
    // Champs OAuth (connexion via GitHub / LinkedIn) — NOUVEAU
    // ---------------------------------------------------------------

    /**
     * Fournisseur OAuth utilisé pour créer/lier ce compte : 'github' | 'linkedin' | null.
     * Null = compte créé via inscription classique (email/mot de passe).
     */
    #[ORM\Column(name: 'oauth_provider', type: 'string', length: 20, nullable: true)]
    private ?string $oauthProvider = null;

    /**
     * Identifiant unique de l'utilisateur chez le fournisseur OAuth
     * (GitHub "id" ou LinkedIn "sub"). Combiné à oauthProvider, permet
     * de retrouver le compte lors des connexions suivantes.
     */
    #[ORM\Column(name: 'oauth_id', type: 'string', length: 255, nullable: true)]
    private ?string $oauthId = null;

    // ---------------------------------------------------------------
    // Relations 1:1 vers les profils (cascade persist/remove)
    // ---------------------------------------------------------------

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

    // ---------------------------------------------------------------
    // UserInterface
    // ---------------------------------------------------------------

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

    // ---------------------------------------------------------------
    // Getters & Setters — champs existants
    // ---------------------------------------------------------------

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

    // ---------------------------------------------------------------
    // Getters & Setters — Face ID
    // ---------------------------------------------------------------

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
        if (!in_array($status, ['pending_face_id', 'complete'])) {
            throw new \InvalidArgumentException("Statut d'inscription invalide : $status.");
        }
        $this->inscriptionStatus = $status;
        return $this;
    }

    public function isInscriptionComplete(): bool
    {
        return $this->inscriptionStatus === 'complete';
    }

    // ---------------------------------------------------------------
    // Getters & Setters — OAuth (NOUVEAU)
    // ---------------------------------------------------------------

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

    /**
     * true si ce compte a été créé/lié via GitHub ou LinkedIn.
     */
    public function isOAuthAccount(): bool
    {
        return $this->oauthProvider !== null && $this->oauthId !== null;
    }

    // ---------------------------------------------------------------
    // Helpers rôle
    // ---------------------------------------------------------------

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
}