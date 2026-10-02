<?php

declare(strict_types=1);

namespace App\Tests\Controller\Tenant;

use App\Entity\Lease;
use App\Entity\RentDue;
use App\Entity\User;
use App\Enum\PaymentMethod;
use App\Factory\LeaseFactory;
use App\Factory\PropertyFactory;
use App\Factory\UserFactory;
use App\Lease\RentDueGenerator;
use App\Payment\PaymentRecorder;
use App\Repository\RentDueRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class TenantSpaceTest extends WebTestCase
{
    use ClockSensitiveTrait;

    private KernelBrowser $client;
    private User $tenantAccount;

    protected function setUp(): void
    {
        self::mockTime('2026-10-15 10:00');
        $this->client = static::createClient();
        $this->tenantAccount = UserFactory::new()->asTenant()->create(['email' => 'karim@exemple.fr', 'firstName' => 'Karim']);
    }

    public function testTenantSeesTheirDuesAndQuittances(): void
    {
        $lease = $this->leaseOf($this->tenantAccount);
        $september = $this->due($lease, '2026-09-01');
        $this->pay($september, $september->getTotal());

        $this->client->loginUser($this->tenantAccount);
        $this->client->request('GET', '/locataire');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.card h2', '12 rue d\'Austerlitz, 69004 Lyon');
        self::assertSelectorTextContains('.card', 'Maxence Svensson');
        self::assertSelectorTextContains('.table', 'Octobre 2026');
        self::assertSelectorExists(\sprintf('a[href="/locataire/echeances/%d/justificatif"]', $september->getId()));
        // Le locataire consulte : il n'enregistre pas de paiement
        self::assertSelectorNotExists('a[href$="/paiement"]');
    }

    public function testTenantDownloadsTheirQuittance(): void
    {
        $due = $this->due($this->leaseOf($this->tenantAccount), '2026-09-01');
        $this->pay($due, $due->getTotal());

        $this->client->loginUser($this->tenantAccount);
        $this->client->request('GET', \sprintf('/locataire/echeances/%d/justificatif', $due->getId()));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
    }

    public function testEachRoommateSeesTheSharedLease(): void
    {
        $roommate = UserFactory::new()->asTenant()->create(['email' => 'lea@exemple.fr']);
        $lease = $this->leaseOf($this->tenantAccount, [['Léa', 'Moreau', 'lea@exemple.fr']]);
        $lease->getTenants()[1]->attachUser($roommate);
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        $this->client->loginUser($roommate);
        $this->client->request('GET', '/locataire');

        self::assertSelectorTextContains('.card h2', '12 rue d\'Austerlitz, 69004 Lyon');
    }

    public function testTenantCannotDownloadAnotherTenantsQuittance(): void
    {
        $someoneElse = UserFactory::new()->asTenant()->create(['email' => 'autre@exemple.fr']);
        $due = $this->due($this->leaseOf($someoneElse), '2026-09-01');
        $this->pay($due, $due->getTotal());

        $this->client->loginUser($this->tenantAccount);
        $this->client->request('GET', \sprintf('/locataire/echeances/%d/justificatif', $due->getId()));

        self::assertResponseStatusCodeSame(403);
    }

    public function testTenantCannotUseTheOwnerSpace(): void
    {
        $due = $this->due($this->leaseOf($this->tenantAccount), '2026-09-01');
        $this->pay($due, $due->getTotal());

        $this->client->loginUser($this->tenantAccount);

        foreach (['justificatif', 'paiement'] as $action) {
            $this->client->request('GET', \sprintf('/proprietaire/echeances/%d/%s', $due->getId(), $action));
            self::assertResponseStatusCodeSame(403);
        }
    }

    public function testTenantWithoutRentalSeesAnExplanation(): void
    {
        $this->client->loginUser($this->tenantAccount);
        $this->client->request('GET', '/locataire');

        self::assertSelectorTextContains('.empty-state', 'Aucune location pour l\'instant');
    }

    /**
     * @param list<array{string, string, string}> $roommates
     */
    private function leaseOf(User $account, array $roommates = []): Lease
    {
        $owner = UserFactory::new()->asOwner()->create(['firstName' => 'Maxence', 'lastName' => 'Svensson']);
        $lease = LeaseFactory::new()->create([
            'property' => PropertyFactory::new([
                'owner' => $owner,
                'addressLine' => '12 rue d\'Austerlitz',
                'postalCode' => '69004',
                'city' => 'Lyon',
            ]),
            'startDate' => new \DateTimeImmutable('2025-09-01'),
            'rentTrackedFrom' => new \DateTimeImmutable('2026-09-01'),
            'tenants' => [[$account->getFirstName(), $account->getLastName(), $account->getEmail()], ...$roommates],
        ]);
        $lease->getTenants()[0]->attachUser($account);
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        self::getContainer()->get(RentDueGenerator::class)->generateFor($lease, new \DateTimeImmutable('2026-10-15'));

        return $lease;
    }

    private function due(Lease $lease, string $period): RentDue
    {
        $due = self::getContainer()->get(RentDueRepository::class)->findOneBy(['lease' => $lease, 'period' => new \DateTimeImmutable($period)]);
        self::assertNotNull($due);

        return $due;
    }

    private function pay(RentDue $due, int $amount): void
    {
        self::getContainer()->get(PaymentRecorder::class)->record($due, $amount, new \DateTimeImmutable('2026-09-05'), PaymentMethod::Transfer);
    }
}
