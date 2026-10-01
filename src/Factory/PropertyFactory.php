<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Property;
use App\Enum\EnergyClass;
use App\Enum\HousingType;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Property>
 */
final class PropertyFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Property::class;
    }

    protected function defaults(): array
    {
        return [
            // Sans précision, chaque bien appartient à un nouveau propriétaire
            'owner' => UserFactory::new()->asOwner(),
            'name' => self::faker()->randomElement(['Studio', 'T2', 'T3', 'T4']).' '.self::faker()->lastName(),
            'addressLine' => self::faker()->streetAddress(),
            'postalCode' => self::faker()->numerify('#####'),
            'city' => self::faker()->city(),
            'housingType' => self::faker()->randomElement(HousingType::cases()),
            'furnished' => self::faker()->boolean(30),
            'surface' => self::faker()->randomFloat(1, 15, 120),
            'energyClass' => self::faker()->randomElement(EnergyClass::cases()),
        ];
    }
}
