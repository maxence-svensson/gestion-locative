<?php

declare(strict_types=1);

namespace App\Tests\Controller\Owner;

use App\Entity\RentDue;
use App\Entity\User;
use App\Enum\PaymentMethod;
use App\Factory\LeaseFactory;
use App\Factory\PropertyFactory;
use App\Factory\UserFactory;
use App\Lease\RentDueGenerator;
use App\Payment\PaymentRecorder;
use App\Repository\RentDueRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use Smalot\PdfParser\Parser;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class RentDueControllerTest extends WebTestCase
{
    use ClockSensitiveTrait;

    private KernelBrowser $client;
    private User $owner;

    protected function setUp(): void
    {
        self::mockTime('2026-10-15 10:00');
        $this->client = static::createClient();
        $this->owner = UserFactory::new()->asOwner()->create(['firstName' => 'Maxence', 'lastName' => 'Svensson']);
        $this->client->loginUser($this->owner);
    }

    public function testFormIsPrefilledWithTheRemainingAmount(): void
    {
        $due = $this->octoberDue();

        $crawler = $this->client->request('GET', $this->paymentUrl($due));

        self::assertResponseIsSuccessful();
        self::assertSame('780,00', $crawler->filter('#payment_form_amount')->attr('value'));
        self::assertSame('2026-10-15', $crawler->filter('#payment_form_paidOn')->attr('value'));
    }

    public function testOwnerRecordsAFullPayment(): void
    {
        $due = $this->octoberDue();

        $this->client->request('GET', $this->paymentUrl($due));
        $this->client->submitForm('Enregistrer le paiement', [
            'payment_form[amount]' => '780',
            'payment_form[paidOn]' => '2026-10-05',
            'payment_form[method]' => 'transfer',
        ]);

        self::assertResponseRedirects($this->propertyUrl($due));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert--success', 'La quittance est disponible.');
        self::assertSelectorTextContains('.table', 'Payé');
        self::assertSelectorExists(\sprintf('a[href="/proprietaire/echeances/%d/justificatif"]', $due->getId()));
        self::assertSelectorNotExists(\sprintf('a[href="%s"]', $this->paymentUrl($due)));
    }

    public function testAPartialPaymentLeavesTheRestToPay(): void
    {
        $due = $this->octoberDue();

        $this->client->request('GET', $this->paymentUrl($due));
        $this->client->submitForm('Enregistrer le paiement', ['payment_form[amount]' => '500']);
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert--success', 'Un reçu est disponible.');
        self::assertSelectorTextContains('.table', 'reste 280,00');
        self::assertSelectorTextContains('.table', 'Reçu (PDF)');
    }

    public function testPaymentCannotExceedTheRemainingAmount(): void
    {
        $due = $this->octoberDue();

        $this->client->request('GET', $this->paymentUrl($due));
        $this->client->submitForm('Enregistrer le paiement', ['payment_form[amount]' => '780,01']);

        self::assertResponseStatusCodeSame(422);
        self::assertAnySelectorTextContains('.form__errors', 'Le reste à payer est de 780,00');
        self::assertSame(0, $this->reload($due)->getPaidAmount());
    }

    public function testPaymentDateCannotBeInTheFuture(): void
    {
        $due = $this->octoberDue();

        $this->client->request('GET', $this->paymentUrl($due));
        $this->client->submitForm('Enregistrer le paiement', ['payment_form[paidOn]' => '2026-10-16']);

        self::assertResponseStatusCodeSame(422);
        self::assertAnySelectorTextContains('.form__errors', 'ne peut pas être dans le futur');
    }

    public function testAFullyPaidDueAcceptsNoMorePayment(): void
    {
        $due = $this->octoberDue();
        $this->pay($due, 78000);

        $this->client->request('GET', $this->paymentUrl($due));

        self::assertResponseRedirects($this->propertyUrl($due));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert--warning', 'déjà entièrement payée');
    }

    public function testQuittanceDetailsRentAndCharges(): void
    {
        $due = $this->octoberDue();
        $this->pay($due, 78000);

        $this->client->request('GET', $this->receiptUrl($due));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertStringContainsString('quittance-2026-10-t2-croix-rousse.pdf', (string) $this->client->getResponse()->headers->get('Content-Disposition'));

        $text = $this->pdfText();
        self::assertStringContainsString('Quittance de loyer', $text);
        self::assertStringContainsString('Maxence Svensson', $text);
        self::assertStringContainsString('Karim Benali', $text);
        // Article 21 de la loi de 1989 : la quittance distingue le loyer et les charges
        self::assertStringContainsString('Loyer hors charges 720,00 €', $text);
        self::assertStringContainsString('Provision sur charges 60,00 €', $text);
        self::assertStringContainsString('Du 1er octobre 2026 au 31 octobre 2026', $text);
        self::assertStringContainsString('lui en donne quittance', $text);
    }

    public function testAPartialPaymentGivesAReceiptThatIsNotAQuittance(): void
    {
        $due = $this->octoberDue();
        $this->pay($due, 50000);

        $this->client->request('GET', $this->receiptUrl($due));

        self::assertStringContainsString('recu-2026-10-t2-croix-rousse.pdf', (string) $this->client->getResponse()->headers->get('Content-Disposition'));
        $text = $this->pdfText();
        self::assertStringContainsString('Reçu de paiement partiel', $text);
        self::assertStringContainsString('Reste à payer 280,00 €', $text);
        self::assertStringContainsString('Il ne vaut pas quittance', $text);
        self::assertStringNotContainsString('en donne quittance', $text);
    }

    public function testNoReceiptBeforeAnyPayment(): void
    {
        $due = $this->octoberDue();

        $this->client->request('GET', $this->receiptUrl($due));

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function actionsOnSomeoneElsesDue(): iterable
    {
        yield 'afficher le formulaire de paiement' => ['GET', 'paiement'];
        yield 'enregistrer un paiement' => ['POST', 'paiement'];
        yield 'télécharger la quittance' => ['GET', 'justificatif'];
    }

    #[DataProvider('actionsOnSomeoneElsesDue')]
    public function testOwnerCannotAccessSomeoneElsesDue(string $method, string $action): void
    {
        $someoneElsesDue = $this->octoberDue(UserFactory::new()->asOwner()->create());
        $this->pay($someoneElsesDue, 50000);

        $this->client->request($method, \sprintf('/proprietaire/echeances/%d/%s', $someoneElsesDue->getId(), $action), [
            'payment_form' => ['amount' => '280', 'paidOn' => '2026-10-15', 'method' => 'cash'],
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(50000, $this->reload($someoneElsesDue)->getPaidAmount());
    }

    private function octoberDue(?User $owner = null): RentDue
    {
        $lease = LeaseFactory::new()->create([
            'property' => PropertyFactory::new(['owner' => $owner ?? $this->owner, 'name' => 'T2 Croix-Rousse']),
            'startDate' => new \DateTimeImmutable('2025-09-01'),
            'rentTrackedFrom' => new \DateTimeImmutable('2026-10-01'),
            'rent' => 72000,
            'charges' => 6000,
            'deposit' => 72000,
            'paymentDay' => 5,
            'tenants' => [['Karim', 'Benali', 'karim@exemple.fr']],
        ]);
        self::getContainer()->get(RentDueGenerator::class)->generateFor($lease, new \DateTimeImmutable('2026-10-15'));

        $due = self::getContainer()->get(RentDueRepository::class)->findOneBy(['lease' => $lease]);
        self::assertNotNull($due);

        return $due;
    }

    private function pay(RentDue $due, int $amount): void
    {
        self::getContainer()->get(PaymentRecorder::class)->record($due, $amount, new \DateTimeImmutable('2026-10-05'), PaymentMethod::Transfer);
    }

    private function reload(RentDue $due): RentDue
    {
        $reloaded = self::getContainer()->get(RentDueRepository::class)->find((int) $due->getId());
        self::assertNotNull($reloaded);

        return $reloaded;
    }

    /**
     * Texte du PDF renvoyé, espaces normalisés (le format français utilise des espaces insécables).
     */
    private function pdfText(): string
    {
        $text = (new Parser())->parseContent((string) $this->client->getResponse()->getContent())->getText();
        $text = str_replace(["\u{00A0}", "\u{202F}"], ' ', $text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private function paymentUrl(RentDue $due): string
    {
        return \sprintf('/proprietaire/echeances/%d/paiement', $due->getId());
    }

    private function receiptUrl(RentDue $due): string
    {
        return \sprintf('/proprietaire/echeances/%d/justificatif', $due->getId());
    }

    private function propertyUrl(RentDue $due): string
    {
        return \sprintf('/proprietaire/biens/%d', $due->getLease()->getProperty()->getId());
    }
}
