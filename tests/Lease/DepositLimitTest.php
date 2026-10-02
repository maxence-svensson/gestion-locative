<?php

declare(strict_types=1);

namespace App\Tests\Lease;

use App\Lease\DepositLimit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DepositLimitTest extends TestCase
{
    public function testMaximumIsOneMonthOfRentForAnUnfurnishedLease(): void
    {
        self::assertSame(72000, DepositLimit::maximumFor(72000, furnished: false));
    }

    public function testMaximumIsTwoMonthsOfRentForAFurnishedLease(): void
    {
        self::assertSame(144000, DepositLimit::maximumFor(72000, furnished: true));
    }

    /**
     * @return iterable<string, array{int, bool, bool}>
     */
    public static function deposits(): iterable
    {
        yield 'pas de dépôt' => [0, false, true];
        yield 'vide : exactement un mois' => [72000, false, true];
        yield 'vide : un centime de trop' => [72001, false, false];
        yield 'meublé : exactement deux mois' => [144000, true, true];
        yield 'meublé : un centime de trop' => [144001, true, false];
        yield 'montant négatif' => [-1, false, false];
    }

    #[DataProvider('deposits')]
    public function testAllows(int $deposit, bool $furnished, bool $expected): void
    {
        self::assertSame($expected, DepositLimit::allows($deposit, 72000, $furnished));
    }
}
