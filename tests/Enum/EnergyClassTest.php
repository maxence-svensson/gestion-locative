<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\EnergyClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EnergyClassTest extends TestCase
{
    /**
     * @return iterable<string, array{EnergyClass, bool}>
     */
    public static function energyClasses(): iterable
    {
        foreach (EnergyClass::cases() as $class) {
            yield 'classe '.$class->value => [$class, \in_array($class, [EnergyClass::F, EnergyClass::G], true)];
        }
    }

    #[DataProvider('energyClasses')]
    public function testRentIsFrozenOnlyForClassesFAndG(EnergyClass $class, bool $expected): void
    {
        self::assertSame($expected, $class->freezesRent());
    }
}
