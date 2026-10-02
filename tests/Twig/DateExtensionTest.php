<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Twig\DateExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DateExtensionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function dates(): iterable
    {
        yield 'premier du mois' => ['2024-07-01', '1er juillet 2024'];
        yield 'autre jour' => ['2025-09-15', '15 septembre 2025'];
        yield 'le 11 ne devient pas « 1er1 »' => ['2025-11-11', '11 novembre 2025'];
    }

    #[DataProvider('dates')]
    public function testLongDateInFrench(string $date, string $expected): void
    {
        self::assertSame($expected, (new DateExtension())->formatLongDate(new \DateTimeImmutable($date)));
    }

    public function testMonthAndYearInFrench(): void
    {
        self::assertSame('octobre 2026', (new DateExtension())->formatMonthYear(new \DateTimeImmutable('2026-10-01')));
    }
}
