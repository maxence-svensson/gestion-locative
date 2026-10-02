<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TenantRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Locataire signataire d'un bail (un bail en colocation en a plusieurs).
 *
 * Il n'a pas forcément de compte : le propriétaire l'invite par e-mail à rejoindre son espace locataire,
 * et le lien vers un User est fait quand il accepte l'invitation.
 */
#[ORM\Entity(repositoryClass: TenantRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_tenant_invitation_token', fields: ['invitationTokenHash'])]
final class Tenant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'tenants')]
    #[ORM\JoinColumn(nullable: false)]
    private Lease $lease;

    #[ORM\Column(length: 100)]
    private string $firstName;

    #[ORM\Column(length: 100)]
    private string $lastName;

    #[ORM\Column(length: 180)]
    private string $email;

    /**
     * Si le compte est supprimé, le locataire reste : il fait partie du bail.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $invitedAt = null;

    /**
     * Empreinte SHA-256 du lien d'invitation : le lien lui-même n'est jamais enregistré,
     * une fuite de la base ne permettrait donc pas de l'utiliser.
     */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $invitationTokenHash = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $invitationExpiresAt = null;

    /**
     * À appeler uniquement depuis Lease::addTenant().
     */
    public function __construct(Lease $lease, string $firstName, string $lastName, string $email)
    {
        $this->lease = $lease;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->email = User::normalizeEmail($email);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLease(): Lease
    {
        return $this->lease;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getFullName(): string
    {
        return $this->firstName.' '.$this->lastName;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function hasAccount(): bool
    {
        return null !== $this->user;
    }

    /**
     * Une nouvelle invitation remplace la précédente : l'ancien lien ne fonctionne plus.
     */
    public function invite(string $tokenHash, \DateTimeImmutable $invitedAt, \DateTimeImmutable $expiresAt): void
    {
        if ($this->hasAccount()) {
            throw new \LogicException('Ce locataire a déjà accès à son espace.');
        }

        $this->invitationTokenHash = $tokenHash;
        $this->invitedAt = $invitedAt;
        $this->invitationExpiresAt = $expiresAt;
    }

    public function getInvitedAt(): ?\DateTimeImmutable
    {
        return $this->invitedAt;
    }

    public function isInvitationPending(\DateTimeImmutable $now): bool
    {
        return !$this->hasAccount() && null !== $this->invitationExpiresAt && $now < $this->invitationExpiresAt;
    }

    public function hasInvitationExpired(\DateTimeImmutable $now): bool
    {
        return !$this->hasAccount() && null !== $this->invitationExpiresAt && $now >= $this->invitationExpiresAt;
    }

    /**
     * Rattache le compte qui a accepté l'invitation. Le lien d'invitation ne peut plus servir.
     */
    public function attachUser(User $user): void
    {
        // L'invitation a été envoyée à cette adresse : seul le compte qui la porte peut être rattaché
        if ($user->getEmail() !== $this->email) {
            throw new \InvalidArgumentException('Le compte doit avoir l\'adresse e-mail du locataire.');
        }

        $this->user = $user;
        $this->invitationTokenHash = null;
        $this->invitationExpiresAt = null;
    }
}
