<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TenantRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Locataire signataire d'un bail (un bail en colocation en a plusieurs).
 *
 * Il n'a pas forcément de compte : le lien vers un User sera fait quand le propriétaire l'invitera
 * à rejoindre son espace locataire.
 */
#[ORM\Entity(repositoryClass: TenantRepository::class)]
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
}
