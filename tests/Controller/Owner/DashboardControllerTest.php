<?php

declare(strict_types=1);

namespace App\Tests\Controller\Owner;

use App\Entity\Lease;
use App\Enum\PaymentMethod;
use App\Factory\LeaseFactory;
use App\Factory\PropertyFactory;
use App\Factory\UserFactory;
use App\Lease\RentDueGenerator;
use App\Payment\PaymentRecorder;
use App\Repository\RentDueRepository;
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

        // Septembre payé en entier, octobre payé en partie ; le locataire de l'autre propriétaire a payé aussi
        $this->pay($lease, '2026-09-01', 78000, '2026-09-05');
        $this->pay($lease, '2026-10-01', 30000, '2026-10-06');
        $this->pay($otherLease, '2026-10-01', 10000, '2026-10-06');

        $client->loginUser($owner);
        $crawler = $client->request('GET', '/proprietaire');

        $statValue = static fn (string $label): string => $crawler
            ->filter('.stat-card')
            ->reduce(static fn ($card): bool => str_contains($card->filter('.stat-card__label')->text(), $label))
            ->filter('.stat-card__value')
            ->text();
        self::assertSame('780,00 €', $this->normalizeSpaces($statValue('appelés en octobre 2026')));
        self::assertSame('300,00 €', $this->normalizeSpaces($statValue('encaissés en octobre 2026')));
        // Septembre est payé : seul octobre, payé en partie et dû le 5, est en retard
        self::assertSame('1', $statValue('en retard'));
    }

    private function pay(Lease $lease, string $period, int $amount, string $paidOn): void
    {
        $due = self::getContainer()->get(RentDueRepository::class)->findOneBy(['lease' => $lease, 'period' => new \DateTimeImmutable($period)]);
        self::assertNotNull($due);
        self::getContainer()->get(PaymentRecorder::class)->record($due, $amount, new \DateTimeImmutable($paidOn), PaymentMethod::Transfer);
    }

    private function normalizeSpaces(string $text): string
    {
        return str_replace(["\u{00A0}", "\u{202F}"], ' ', $text);
    }
}
