<?php

declare(strict_types=1);

namespace App\Story;

use App\Entity\Lease;
use App\Enum\EnergyClass;
use App\Enum\HousingType;
use App\Enum\PaymentMethod;
use App\Factory\LeaseFactory;
use App\Factory\PropertyFactory;
use App\Factory\UserFactory;
use App\Lease\RentDueGenerator;
use App\Payment\PaymentRecorder;
use App\Repository\RentDueRepository;
use Doctrine\ORM\EntityManagerInterface;
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
        private readonly RentDueRepository $dues,
        private readonly PaymentRecorder $paymentRecorder,
        private readonly EntityManagerInterface $entityManager,
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

        $tenantAccount = UserFactory::new()->asTenant()->create([
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

        // T2 : août payé (quittance), septembre payé en partie (reçu, en retard), octobre à payer
        $this->pay($croixRousseLease, '2026-08-01', 78000, '2026-08-04', PaymentMethod::Transfer);
        $this->pay($croixRousseLease, '2026-09-01', 50000, '2026-09-07', PaymentMethod::Transfer);

        // Colocation : chacun paie sa moitié ; en octobre, un seul des deux a payé
        foreach (['2026-07-01', '2026-08-01', '2026-09-01'] as $period) {
            $this->pay($villeurbanneLease, $period, 72000, $period, PaymentMethod::Transfer);
            $this->pay($villeurbanneLease, $period, 72000, $period, PaymentMethod::DirectDebit);
        }
        $this->pay($villeurbanneLease, '2026-10-01', 72000, '2026-10-01', PaymentMethod::Transfer);

        // Accès à l'espace locataire : Karim l'a activé, Léa a reçu une invitation, Hugo pas encore
        $croixRousseLease->getTenants()[0]->attachUser($tenantAccount);
        $now = $this->clock->now();
        $villeurbanneLease->getTenants()[0]->invite(hash('sha256', random_bytes(32)), $now, $now->modify('+7 days'));
        $this->entityManager->flush();
    }

    private function pay(Lease $lease, string $period, int $amount, string $paidOn, PaymentMethod $method): void
    {
        $due = $this->dues->findOneBy(['lease' => $lease, 'period' => new \DateTimeImmutable($period)]);

        // Données chargées avant cette date : l'échéance n'existe pas encore
        if (null !== $due) {
            $this->paymentRecorder->record($due, $amount, new \DateTimeImmutable($paidOn), $method);
        }
    }
}
