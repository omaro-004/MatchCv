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

    /**
     * Descripteur facial encodé en JSON.
     * Tableau de 128 valeurs float produit par face-api.js (FaceNet / TinyFaceDetector).
     * Null si aucune capture biométrique n'a été effectuée.
     * Stocké en LONGTEXT pour absorber les 128 floats sérialisés.
     *
     * IMPORTANT sécurité : ce champ ne contient PAS une image, seulement
     * un vecteur mathématique non réversible en image originale.
     */
    #[ORM\Column(name: 'face_descriptor', type: 'text', nullable: true)]
    private ?string $faceDescriptor = null;

    /**
     * Toggle Face ID : true = vérification faciale obligatoire à chaque login.
     * false = login classique email/password uniquement.
     * Uniquement pertinent si faceDescriptor n'est pas null.
     */
    #[ORM\Column(name: 'face_id_enabled', type: 'boolean', options: ['default' => false])]
    private bool $faceIdEnabled = false;

    /**
     * Statut de l'inscription en 2 étapes.
     * 'pending_face_id' → le candidat a validé l'étape 1 mais n'a pas encore
     *                      passé l'étape Face ID.
     * 'complete'        → inscription terminée (avec ou sans Face ID activé).
     * Pour les entreprises, toujours 'complete'.
     */
    #[ORM\Column(name: 'inscription_status', type: 'string', length: 30, options: ['default' => 'complete'])]
    private string $inscriptionStatus = 'complete';

    // ---------------------------------------------------------------
    // Relations 1:1 vers les profils (cascade persist/remove)
    // ---------------------------------------------------------------

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: ProfilCandidat::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private ?ProfilCandidat $profilCandidat = null;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: ProfilEntreprise::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private ?ProfilEntreprise $profilEntreprise = null;

    // ---------------------------------------------------------------
    // Lifecycle callback : date_inscri auto à la création
    // ---------------------------------------------------------------

    #[ORM\PrePersist]
    public function setDateInscriOnCreate(): void
    {
        if ($this->dateInscri === null) {
            $this->dateInscri = new \DateTimeImmutable();
        }
    }

    // ---------------------------------------------------------------
    // UserInterface — requis par Symfony Security
    // ---------------------------------------------------------------

    /**
     * Retourne le tableau de rôles Symfony.
     * Un User ne possède qu'un seul rôle fonctionnel (règle RM-U03).
     */
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
        // Rien à effacer ici : on ne stocke jamais le mot de passe en clair.
    }

    // ---------------------------------------------------------------
    // PasswordAuthenticatedUserInterface
    // ---------------------------------------------------------------

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

    /**
     * Retourne le descripteur facial (JSON string des 128 floats) ou null.
     */
    public function getFaceDescriptor(): ?string
    {
        return $this->faceDescriptor;
    }

    /**
     * Persiste le descripteur facial.
     * Accepte directement la chaîne JSON produite par face-api.js côté client.
     */
    public function setFaceDescriptor(?string $faceDescriptor): static
    {
        $this->faceDescriptor = $faceDescriptor;
        return $this;
    }

    /**
     * Retourne le descripteur facial désérialisé en tableau PHP, ou null.
     * Utile pour d'éventuels calculs de distance côté serveur.
     */
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

    /**
     * Vérifie si le candidat a bien enregistré un descripteur facial.
     * Un Face ID activé sans descripteur est une incohérence — ce helper le détecte.
     */
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
