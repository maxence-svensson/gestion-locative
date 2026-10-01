<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\EnergyClass;
use App\Enum\HousingType;
use App\Repository\PropertyRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un logement mis en location par un propriétaire.
 */
#[ORM\Entity(repositoryClass: PropertyRepository::class)]
final class Property
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $owner;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(length: 255)]
    private string $addressLine;

    #[ORM\Column(length: 5)]
    private string $postalCode;

    #[ORM\Column(length: 100)]
    private string $city;

    #[ORM\Column(length: 20, enumType: HousingType::class)]
    private HousingType $housingType;

    #[ORM\Column]
    private bool $furnished;

    /**
     * Surface habitable en m² (une surface n'est pas un montant : un float suffit).
     */
    #[ORM\Column]
    private float $surface;

    #[ORM\Column(length: 1, enumType: EnergyClass::class)]
    private EnergyClass $energyClass;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        User $owner,
        string $name,
        string $addressLine,
        string $postalCode,
        string $city,
        HousingType $housingType,
        bool $furnished,
        float $surface,
        EnergyClass $energyClass,
    ) {
        $this->owner = $owner;
        $this->createdAt = new \DateTimeImmutable();
        $this->update($name, $addressLine, $postalCode, $city, $housingType, $furnished, $surface, $energyClass);
    }

    public function update(
        string $name,
        string $addressLine,
        string $postalCode,
        string $city,
        HousingType $housingType,
        bool $furnished,
        float $surface,
        EnergyClass $energyClass,
    ): void {
        $this->name = $name;
        $this->addressLine = $addressLine;
        $this->postalCode = $postalCode;
        $this->city = $city;
        $this->housingType = $housingType;
        $this->furnished = $furnished;
        $this->surface = $surface;
        $this->energyClass = $energyClass;
    }

    public function isOwnedBy(User $user): bool
    {
        return null !== $user->getId() && $user->getId() === $this->owner->getId();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAddressLine(): string
    {
        return $this->addressLine;
    }

    public function getPostalCode(): string
    {
        return $this->postalCode;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getFullAddress(): string
    {
        return \sprintf('%s, %s %s', $this->addressLine, $this->postalCode, $this->city);
    }

    public function getHousingType(): HousingType
    {
        return $this->housingType;
    }

    public function isFurnished(): bool
    {
        return $this->furnished;
    }

    public function getSurface(): float
    {
        return $this->surface;
    }

    public function getEnergyClass(): EnergyClass
    {
        return $this->energyClass;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
