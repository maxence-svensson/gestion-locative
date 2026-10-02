<?php

declare(strict_types=1);

namespace App\Tests\Lease;

use App\Entity\Lease;
use App\Entity\Property;
use App\Entity\User;
use App\Enum\EnergyClass;
use App\Enum\HousingType;
use App\Lease\RentSchedule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RentScheduleTest extends TestCase
{
    private RentSchedule $schedule;

    protected function setUp(): void
    {
        $this->schedule = new RentSchedule();
    }

    public function testPeriodsGoFromTrackingStartToTheCurrentMonth(): void
    {
        $lease = $this->lease(startDate: '2025-09-01', trackedFrom: '2026-08-01');

        $periods = $this->schedule->periods($lease, new \DateTimeImmutable('2026-10-15'));

        self::assertSame(['2026-08-01', '2026-09-01', '2026-10-01'], array_map(
            static fn (\DateTimeImmutable $period): string => $period->format('Y-m-d'),
            $periods,
        ));
    }

    public function testNoPeriodBeforeALeaseStarts(): void
    {
        $lease = $this->lease(startDate: '2026-11-01', trackedFrom: '2026-11-01');

        self::assertSame([], $this->schedule->periods($lease, new \DateTimeImmutable('2026-10-15')));
    }

    public function testFullMonthIsDueOnThePaymentDay(): void
    {
        $lease = $this->lease(startDate: '2025-09-01', rent: 72000, charges: 6000, paymentDay: 5);

        $due = $this->schedule->dueFor($lease, new \DateTimeImmutable('2026-10-01'));

        self::assertSame(72000, $due->getRent());
        self::assertSame(6000, $due->getCharges());
        self::assertSame('2026-10-05', $due->getDueDate()->format('Y-m-d'));
        self::assertFalse($due->isProrated());
    }

    /**
     * @return iterable<string, array{string, int, int, int}>
     */
    public static function firstMonths(): iterable
    {
        // Septembre a 30 jours : du 15 au 30, le locataire occupe 16 jours
        yield '15 septembre : 16 jours sur 30' => ['2026-09-15', 72000, 6000, 38400 + 3200];
        // 100 000 × 17 / 31 = 54 838,71 centimes : arrondi au centime le plus proche
        yield '15 octobre : arrondi au centime' => ['2026-10-15', 100000, 0, 54839];
        // Février 2027 a 28 jours : du 10 au 28, 19 jours
        yield '10 février : mois de 28 jours' => ['2027-02-10', 72000, 0, 48857];
        yield 'dernier jour du mois : 1 jour' => ['2026-10-31', 31000, 0, 1000];
    }

    #[DataProvider('firstMonths')]
    public function testFirstMonthIsProratedWhenTheLeaseStartsMidMonth(string $startDate, int $rent, int $charges, int $expectedTotal): void
    {
        $lease = $this->lease(startDate: $startDate, rent: $rent, charges: $charges);

        $due = $this->schedule->dueFor($lease, Lease::firstDayOfMonth(new \DateTimeImmutable($startDate)));

        self::assertSame($expectedTotal, $due->getTotal());
        self::assertTrue($due->isProrated());
    }

    public function testFirstRentIsNeverDueBeforeTheLeaseStarts(): void
    {
        $lease = $this->lease(startDate: '2026-10-20', paymentDay: 5);

        $due = $this->schedule->dueFor($lease, new \DateTimeImmutable('2026-10-01'));

        self::assertSame('2026-10-20', $due->getDueDate()->format('Y-m-d'));
    }

    public function testOnlyTheFirstMonthIsProrated(): void
    {
        $lease = $this->lease(startDate: '2026-09-15', rent: 72000, charges: 6000);

        $due = $this->schedule->dueFor($lease, new \DateTimeImmutable('2026-10-01'));

        self::assertSame(78000, $due->getTotal());
        self::assertFalse($due->isProrated());
    }

    private function lease(
        string $startDate,
        int $rent = 72000,
        int $charges = 6000,
        int $paymentDay = 5,
        ?string $trackedFrom = null,
    ): Lease {
        $property = new Property(
            new User('proprietaire@exemple.fr', 'Maxence', 'Svensson'),
            'T2', '12 rue d\'Austerlitz', '69004', 'Lyon', HousingType::Apartment, false, 48.5, EnergyClass::C,
        );

        return new Lease(
            $property,
            new \DateTimeImmutable($startDate),
            $rent,
            $charges,
            $rent,
            $paymentDay,
            2,
            2025,
            new \DateTimeImmutable($trackedFrom ?? $startDate),
        );
    }
}
