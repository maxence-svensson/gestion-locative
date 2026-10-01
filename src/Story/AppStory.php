<?php

declare(strict_types=1);

namespace App\Story;

use App\Enum\EnergyClass;
use App\Enum\HousingType;
use App\Factory\PropertyFactory;
use App\Factory\UserFactory;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

/**
 * Données de démonstration, chargées avec `make fixtures`.
 * Les comptes de démo sont listés dans le README.
 */
#[AsFixture(name: 'main')]
final class AppStory extends Story
{
    public const string DEMO_PASSWORD = 'demo1234';

    public function build(): void
    {
        $owner = UserFactory::new()->asOwner()->create([
            'email' => 'proprietaire@demo.test',
            'firstName' => 'Maxence',
            'lastName' => 'Svensson',
            'password' => self::DEMO_PASSWORD,
        ]);

        UserFactory::new()->asTenant()->create([
            'email' => 'locataire@demo.test',
            'firstName' => 'Karim',
            'lastName' => 'Benali',
            'password' => self::DEMO_PASSWORD,
        ]);

        PropertyFactory::new()->create([
            'owner' => $owner,
            'name' => 'T2 Croix-Rousse',
            'addressLine' => '12 rue d\'Austerlitz',
            'postalCode' => '69004',
            'city' => 'Lyon',
            'housingType' => HousingType::Apartment,
            'furnished' => false,
            'surface' => 48.5,
            'energyClass' => EnergyClass::C,
        ]);

        // Classé F : montre le gel du loyer des « passoires thermiques »
        PropertyFactory::new()->create([
            'owner' => $owner,
            'name' => 'Studio Guillotière',
            'addressLine' => '8 rue de Marseille',
            'postalCode' => '69007',
            'city' => 'Lyon',
            'housingType' => HousingType::Apartment,
            'furnished' => true,
            'surface' => 21.0,
            'energyClass' => EnergyClass::F,
        ]);

        PropertyFactory::new()->create([
            'owner' => $owner,
            'name' => 'Maison Villeurbanne',
            'addressLine' => '3 impasse des Tilleuls',
            'postalCode' => '69100',
            'city' => 'Villeurbanne',
            'housingType' => HousingType::House,
            'furnished' => false,
            'surface' => 92.0,
            'energyClass' => EnergyClass::D,
        ]);
    }
}
