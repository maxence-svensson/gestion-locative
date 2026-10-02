<?php

declare(strict_types=1);

namespace App\Tests;

use App\Factory\LeaseFactory;
use App\Message\GenerateRentDues;
use App\MessageHandler\GenerateRentDuesHandler;
use App\Repository\RentDueRepository;
use App\Schedule;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Scheduler\Generator\MessageContext;

final class ScheduleTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    public function testRentDuesAreGeneratedEveryMorning(): void
    {
        self::bootKernel();
        $recurringMessages = self::getContainer()->get(Schedule::class)->getSchedule()->getRecurringMessages();

        self::assertCount(1, $recurringMessages);
        $recurring = $recurringMessages[array_key_first($recurringMessages)];

        $nextRun = $recurring->getTrigger()->getNextRunDate(new \DateTimeImmutable('2026-10-15 07:00'));
        self::assertSame('2026-10-16 06:00', $nextRun?->format('Y-m-d H:i'));

        $context = new MessageContext('default', $recurring->getId(), $recurring->getTrigger(), new \DateTimeImmutable());
        self::assertInstanceOf(GenerateRentDues::class, iterator_to_array($recurring->getMessages($context))[0] ?? null);
    }

    public function testTheScheduledMessageGeneratesTheDues(): void
    {
        self::bootKernel();
        self::mockTime('2026-10-15 06:00');
        $lease = LeaseFactory::new()->create([
            'startDate' => new \DateTimeImmutable('2026-01-01'),
            'rentTrackedFrom' => new \DateTimeImmutable('2026-10-01'),
        ]);

        (self::getContainer()->get(GenerateRentDuesHandler::class))(new GenerateRentDues());

        self::assertSame(['2026-10'], self::getContainer()->get(RentDueRepository::class)->findPeriodsOf($lease));
    }
}
