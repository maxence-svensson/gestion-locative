<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Lease;
use App\Entity\Property;
use App\Entity\User;
use App\Enum\EnergyClass;
use App\Enum\HousingType;
use PHPUnit\Framework\TestCase;

final class LeaseTest extends TestCase
{
    public function testMonthlyTotalIsRentPlusCharges(): void
    {
        $lease = $this->lease(rent: 72000, charges: 6000);

        self::assertSame(78000, $lease->getMonthlyTotal());
    }

    public function testDepositAboveTheLegalLimitIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->lease(rent: 72000, deposit: 72001, furnished: false);
    }

    public function testFurnishedLeaseAcceptsTwoMonthsOfDeposit(): void
    {
        $lease = $this->lease(rent: 72000, deposit: 144000, furnished: true);

        self::assertSame(144000, $lease->getDeposit());
    }

    public function testPaymentDayMustExistEveryMonth(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->lease(paymentDay: 29);
    }

    public function testLeaseKeepsTheTypeItWasSignedWith(): void
    {
        $property = $this->property(furnished: true);
        $lease = new Lease($property, new \DateTimeImmutable('2025-09-01'), 72000, 6000, 144000, 5, 2, 2025, new \DateTimeImmutable('2025-09-01'));

        // Le propriétaire retire les meubles après la signature : le bail reste un bail meublé
        $property->update('T2', '12 rue d\'Austerlitz', '69004', 'Lyon', HousingType::Apartment, false, 48.5, EnergyClass::C);

        self::assertTrue($lease->isFurnished());
    }

    public function testRentTrackingStartsOnTheFirstDayOfTheMonth(): void
    {
        $lease = new Lease($this->property(false), new \DateTimeImmutable('2025-09-15'), 72000, 6000, 72000, 5, 2, 2025, new \DateTimeImmutable('2026-10-17'));

        self::assertSame('2026-10-01', $lease->getRentTrackedFrom()->format('Y-m-d'));
    }

    public function testRentTrackingCannotStartBeforeTheLease(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Lease($this->property(false), new \DateTimeImmutable('2025-09-15'), 72000, 6000, 72000, 5, 2, 2025, new \DateTimeImmutable('2025-08-01'));
    }

    public function testTenantEmailIsNormalized(): void
    {
        $lease = $this->lease();
        $tenant = $lease->addTenant('Karim', 'Benali', '  Karim.Benali@Exemple.FR ');

        self::assertSame('karim.benali@exemple.fr', $tenant->getEmail());
        self::assertSame([$tenant], $lease->getTenants());
    }

    private function lease(int $rent = 72000, int $charges = 6000, int $deposit = 72000, int $paymentDay = 5, bool $furnished = false): Lease
    {
        return new Lease($this->property($furnished), new \DateTimeImmutable('2025-09-01'), $rent, $charges, $deposit, $paymentDay, 2, 2025, new \DateTimeImmutable('2025-09-01'));
    }

    private function property(bool $furnished): Property
    {
        return new Property(
            new User('proprietaire@exemple.fr', 'Maxence', 'Svensson'),
            'T2',
            '12 rue d\'Austerlitz',
            '69004',
            'Lyon',
            HousingType::Apartment,
            $furnished,
            48.5,
            EnergyClass::C,
        );
    }
}
