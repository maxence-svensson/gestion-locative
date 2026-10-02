<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Factory\LeaseFactory;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Tester\CommandTester;

final class GenerateRentDuesCommandTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    public function testCreatesTheMissingDuesAndCanBeRunAgain(): void
    {
        self::mockTime('2026-10-15 10:00');
        LeaseFactory::new()->create([
            'startDate' => new \DateTimeImmutable('2025-09-01'),
            'rentTrackedFrom' => new \DateTimeImmutable('2026-09-01'),
        ]);

        $command = new CommandTester((new Application(self::bootKernel()))->find('app:rent-dues:generate'));

        $command->execute([]);
        $command->assertCommandIsSuccessful();
        self::assertStringContainsString('2 échéance(s) créée(s)', $command->getDisplay());

        $command->execute([]);
        self::assertStringContainsString('0 échéance(s) créée(s)', $command->getDisplay());
    }
}
