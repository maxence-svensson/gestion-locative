<?php

declare(strict_types=1);

namespace App\Story;

use App\Enum\EnergyClass;
use App\Enum\HousingType;
use App\Factory\LeaseFactory;
use App\Factory\PropertyFactory;
use App\Factory\UserFactory;
use App\Lease\RentDueGenerator;
use Psr\Clock\ClockInterface;
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

    public function __construct(
        private readonly RentDueGenerator $rentDueGenerator,
        private readonly ClockInterface $clock,
    ) {
    }

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

        $croixRousse = PropertyFactory::new()->create([
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

        // Classé F et vacant : montre le gel du loyer des « passoires thermiques »
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

        $villeurbanne = PropertyFactory::new()->create([
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

        // Le locataire de démo loue le T2 (son espace locataire sera relié à ce bail)
        $croixRousseLease = LeaseFactory::new()->create([
            'property' => $croixRousse,
            'startDate' => new \DateTimeImmutable('2025-09-01'),
            'rent' => 72000,
            'charges' => 6000,
            'deposit' => 72000,
            'paymentDay' => 5,
            'irlReferenceQuarter' => 2,
            'irlReferenceYear' => 2025,
            'rentTrackedFrom' => new \DateTimeImmutable('2026-08-01'),
            'tenants' => [['Karim', 'Benali', 'locataire@demo.test']],
        ]);

        // Une colocation
        $villeurbanneLease = LeaseFactory::new()->create([
            'property' => $villeurbanne,
            'startDate' => new \DateTimeImmutable('2024-07-01'),
            'rent' => 135000,
            'charges' => 9000,
            'deposit' => 135000,
            'paymentDay' => 1,
            'irlReferenceQuarter' => 1,
            'irlReferenceYear' => 2024,
            'rentTrackedFrom' => new \DateTimeImmutable('2026-07-01'),
            'tenants' => [
                ['Léa', 'Moreau', 'lea.moreau@demo.test'],
                ['Hugo', 'Lambert', 'hugo.lambert@demo.test'],
            ],
        ]);

        // Échéances des derniers mois, comme si la tâche quotidienne avait tourné
        foreach ([$croixRousseLease, $villeurbanneLease] as $lease) {
            $this->rentDueGenerator->generateFor($lease, $this->clock->now());
        }
    }
}
