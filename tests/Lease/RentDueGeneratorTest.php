<?php

declare(strict_types=1);

namespace App\Tests\Lease;

use App\Entity\Lease;
use App\Entity\RentDue;
use App\Factory\LeaseFactory;
use App\Lease\RentDueGenerator;
use App\Repository\RentDueRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Lock\LockFactory;

final class RentDueGeneratorTest extends KernelTestCase
{
    private RentDueGenerator $generator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->generator = self::getContainer()->get(RentDueGenerator::class);
    }

    public function testCreatesOneDuePerMonthUpToTheCurrentMonth(): void
    {
        $lease = $this->leaseTrackedFrom('2026-08-01');

        $created = $this->generator->generateFor($lease, new \DateTimeImmutable('2026-10-15'));

        self::assertSame(3, $created);
        self::assertSame(['2026-08', '2026-09', '2026-10'], $this->periodsOf($lease));
    }

    public function testRunningTwiceCreatesNoDuplicate(): void
    {
        $lease = $this->leaseTrackedFrom('2026-08-01');
        $today = new \DateTimeImmutable('2026-10-15');

        $this->generator->generateFor($lease, $today);
        $createdOnSecondRun = $this->generator->generateFor($lease, $today);

        self::assertSame(0, $createdOnSecondRun);
        self::assertCount(3, $this->periodsOf($lease));
    }

    public function testAMissedMonthIsCaughtUpOnTheNextRun(): void
    {
        $lease = $this->leaseTrackedFrom('2026-08-01');
        $this->generator->generateFor($lease, new \DateTimeImmutable('2026-08-20'));

        // Aucune génération en septembre (serveur arrêté…) : celle d'octobre rattrape les deux mois
        $created = $this->generator->generateFor($lease, new \DateTimeImmutable('2026-10-02'));

        self::assertSame(2, $created);
        self::assertSame(['2026-08', '2026-09', '2026-10'], $this->periodsOf($lease));
    }

    public function testAllLeasesAreProcessed(): void
    {
        LeaseFactory::new()->many(3)->create(['rentTrackedFrom' => new \DateTimeImmutable('2026-10-01'), 'startDate' => new \DateTimeImmutable('2026-01-01')]);

        self::assertSame(3, $this->generator->generateForAllLeases(new \DateTimeImmutable('2026-10-15')));
    }

    public function testASecondGenerationStopsWhileOneIsRunning(): void
    {
        $runningGeneration = self::getContainer()->get(LockFactory::class)->createLock('rent-due-generation');
        self::assertTrue($runningGeneration->acquire());

        try {
            self::assertNull($this->generator->generateForAllLeases(new \DateTimeImmutable('2026-10-15')));
        } finally {
            $runningGeneration->release();
        }
    }

    public function testTheDatabaseRefusesTwoDuesForTheSameMonth(): void
    {
        $lease = $this->leaseTrackedFrom('2026-10-01');
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $october = new \DateTimeImmutable('2026-10-01');

        // Dernier filet de sécurité, si deux générations passaient malgré le verrou
        $this->expectException(UniqueConstraintViolationException::class);

        $entityManager->persist(new RentDue($lease, $october, new \DateTimeImmutable('2026-10-05'), 72000, 6000));
        $entityManager->persist(new RentDue($lease, $october, new \DateTimeImmutable('2026-10-05'), 72000, 6000));
        $entityManager->flush();
    }

    private function leaseTrackedFrom(string $date): Lease
    {
        return LeaseFactory::new()->create([
            'startDate' => new \DateTimeImmutable('2025-09-01'),
            'rentTrackedFrom' => new \DateTimeImmutable($date),
        ]);
    }

    /**
     * @return list<string>
     */
    private function periodsOf(Lease $lease): array
    {
        $periods = self::getContainer()->get(RentDueRepository::class)->findPeriodsOf($lease);
        sort($periods);

        return $periods;
    }
}
