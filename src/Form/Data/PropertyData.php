<?php

declare(strict_types=1);

namespace App\Form\Data;

use App\Entity\Property;
use App\Entity\User;
use App\Enum\EnergyClass;
use App\Enum\HousingType;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Données saisies dans le formulaire d'un bien.
 *
 * Le formulaire travaille sur cet objet, qui accepte des champs vides pendant la saisie.
 * L'entité Property n'est créée ou modifiée qu'une fois les données validées : elle reste toujours complète.
 */
final class PropertyData
{
    #[Assert\NotBlank(message: 'Donnez un nom à ce bien.')]
    #[Assert\Length(max: 100)]
    public ?string $name = null;

    #[Assert\NotBlank(message: 'Indiquez l\'adresse du bien.')]
    #[Assert\Length(max: 255)]
    public ?string $addressLine = null;

    #[Assert\NotBlank(message: 'Indiquez le code postal.')]
    #[Assert\Regex(pattern: '/^\d{5}$/', message: 'Le code postal doit contenir 5 chiffres.')]
    public ?string $postalCode = null;

    #[Assert\NotBlank(message: 'Indiquez la ville.')]
    #[Assert\Length(max: 100)]
    public ?string $city = null;

    #[Assert\NotNull(message: 'Choisissez le type de logement.')]
    public ?HousingType $housingType = null;

    public bool $furnished = false;

    #[Assert\NotNull(message: 'Indiquez la surface habitable.')]
    #[Assert\Positive(message: 'La surface doit être supérieure à 0.')]
    #[Assert\LessThan(1000, message: 'La surface doit être inférieure à 1 000 m².')]
    public ?float $surface = null;

    #[Assert\NotNull(message: 'Indiquez la classe énergie du DPE.')]
    public ?EnergyClass $energyClass = null;

    public static function fromProperty(Property $property): self
    {
        $data = new self();
        $data->name = $property->getName();
        $data->addressLine = $property->getAddressLine();
        $data->postalCode = $property->getPostalCode();
        $data->city = $property->getCity();
        $data->housingType = $property->getHousingType();
        $data->furnished = $property->isFurnished();
        $data->surface = $property->getSurface();
        $data->energyClass = $property->getEnergyClass();

        return $data;
    }

    public function toProperty(User $owner): Property
    {
        $this->assertValid();

        return new Property(
            $owner,
            $this->name,
            $this->addressLine,
            $this->postalCode,
            $this->city,
            $this->housingType,
            $this->furnished,
            $this->surface,
            $this->energyClass,
        );
    }

    public function applyTo(Property $property): void
    {
        $this->assertValid();

        $property->update(
            $this->name,
            $this->addressLine,
            $this->postalCode,
            $this->city,
            $this->housingType,
            $this->furnished,
            $this->surface,
            $this->energyClass,
        );
    }

    /**
     * Appelé uniquement après validation du formulaire : aucun champ obligatoire ne peut être vide.
     *
     * @phpstan-assert !null $this->name
     * @phpstan-assert !null $this->addressLine
     * @phpstan-assert !null $this->postalCode
     * @phpstan-assert !null $this->city
     * @phpstan-assert !null $this->housingType
     * @phpstan-assert !null $this->surface
     * @phpstan-assert !null $this->energyClass
     */
    private function assertValid(): void
    {
        if (null === $this->name || null === $this->addressLine || null === $this->postalCode || null === $this->city
            || null === $this->housingType || null === $this->surface || null === $this->energyClass) {
            throw new \LogicException('Les données du bien doivent être validées avant d\'être enregistrées.');
        }
    }
}
