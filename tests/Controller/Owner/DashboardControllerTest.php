<?php

declare(strict_types=1);

namespace App\Tests\Controller\Owner;

use App\Factory\LeaseFactory;
use App\Factory\PropertyFactory;
use App\Factory\UserFactory;
use App\Lease\RentDueGenerator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class DashboardControllerTest extends WebTestCase
{
    use ClockSensitiveTrait;

    public function testNewOwnerIsInvitedToAddAProperty(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::new()->asOwner()->create());

        $client->request('GET', '/proprietaire');

        self::assertSelectorTextContains('.empty-state', 'Aucun bien pour l\'instant');
    }

    public function testDashboardCountsOnlyMyProperties(): void
    {
        $client = static::createClient();
        $owner = UserFactory::new()->asOwner()->create();
        PropertyFactory::new()->many(2)->create(['owner' => $owner]);
        PropertyFactory::new()->create();

        $client->loginUser($owner);
        $client->request('GET', '/proprietaire');

        self::assertSelectorTextContains('.stat-card__value', '2');
        self::assertSelectorTextContains('.stat-card__label', 'biens en gestion');
    }

    public function testDashboardShowsTheRentsOfTheMonthAndOverdueDues(): void
    {
        self::mockTime('2026-10-15 10:00');
        $client = static::createClient();
        $owner = UserFactory::new()->asOwner()->create();
        $lease = LeaseFactory::new()->create([
            'property' => PropertyFactory::new(['owner' => $owner]),
            'startDate' => new \DateTimeImmutable('2025-09-01'),
            'rentTrackedFrom' => new \DateTimeImmutable('2026-09-01'),
            'rent' => 72000,
            'charges' => 6000,
            'deposit' => 72000,
            'paymentDay' => 5,
        ]);
        // Le bail d'un autre propriétaire ne doit pas compter
        $otherLease = LeaseFactory::new()->create(['startDate' => new \DateTimeImmutable('2025-09-01'), 'rentTrackedFrom' => new \DateTimeImmutable('2026-10-01')]);
        $generator = self::getContainer()->get(RentDueGenerator::class);
        $generator->generateFor($lease, new \DateTimeImmutable('2026-10-15'));
        $generator->generateFor($otherLease, new \DateTimeImmutable('2026-10-15'));

        $client->loginUser($owner);
        $crawler = $client->request('GET', '/proprietaire');

        $values = $crawler->filter('.stat-card__value')->each(static fn ($node): string => $node->text());
        self::assertStringContainsString('780,00', $values[1]);
        // Septembre et octobre sont tous deux dus avant le 15 octobre
        self::assertSame('2', $values[2]);
    }
}
