<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Lease;
use App\Entity\Property;
use App\Entity\RentDue;
use App\Entity\User;
use App\Enum\EnergyClass;
use App\Enum\HousingType;
use App\Enum\PaymentMethod;
use PHPUnit\Framework\TestCase;

final class RentDueTest extends TestCase
{
    public function testPaymentsReduceTheRemainingAmount(): void
    {
        $due = $this->due();

        $due->addPayment(50000, new \DateTimeImmutable('2026-10-05'), PaymentMethod::Transfer);
        $due->addPayment(10000, new \DateTimeImmutable('2026-10-08'), PaymentMethod::Cash);

        self::assertSame(60000, $due->getPaidAmount());
        self::assertSame(18000, $due->getRemainingAmount());
        self::assertCount(2, $due->getPayments());
    }

    public function testAPaymentCannotExceedTheRemainingAmount(): void
    {
        $due = $this->due();
        $due->addPayment(50000, new \DateTimeImmutable('2026-10-05'), PaymentMethod::Transfer);

        $this->expectException(\InvalidArgumentException::class);

        $due->addPayment(28001, new \DateTimeImmutable('2026-10-06'), PaymentMethod::Transfer);
    }

    public function testAPaymentMustBePositive(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->due()->addPayment(0, new \DateTimeImmutable('2026-10-05'), PaymentMethod::Transfer);
    }

    public function testNoReceiptBeforeAnyPayment(): void
    {
        self::assertNull($this->due()->getReceiptType());
    }

    public function testOverdueOnceTheDueDateHasPassed(): void
    {
        $due = $this->due();

        self::assertFalse($due->isOverdue(new \DateTimeImmutable('2026-10-05 18:00')));
        self::assertTrue($due->isOverdue(new \DateTimeImmutable('2026-10-06 08:00')));
    }

    public function testCoveredPeriodStartsOnTheMoveInDay(): void
    {
        $due = $this->due(leaseStart: '2026-10-15');

        self::assertSame('2026-10-15', $due->getCoveredFrom()->format('Y-m-d'));
        self::assertSame('2026-10-31', $due->getCoveredUntil()->format('Y-m-d'));
    }

    private function due(string $leaseStart = '2025-09-01'): RentDue
    {
        $property = new Property(
            new User('proprietaire@exemple.fr', 'Maxence', 'Svensson'),
            'T2', '12 rue d\'Austerlitz', '69004', 'Lyon', HousingType::Apartment, false, 48.5, EnergyClass::C,
        );
        $lease = new Lease($property, new \DateTimeImmutable($leaseStart), 72000, 6000, 72000, 5, 2, 2025, new \DateTimeImmutable($leaseStart));

        return new RentDue($lease, new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable('2026-10-05'), 72000, 6000);
    }
}
