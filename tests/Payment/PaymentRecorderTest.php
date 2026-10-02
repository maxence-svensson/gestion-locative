<?php

declare(strict_types=1);

namespace App\Tests\Payment;

use App\Entity\RentDue;
use App\Enum\PaymentMethod;
use App\Enum\ReceiptType;
use App\Factory\LeaseFactory;
use App\Lease\RentDueGenerator;
use App\Payment\PaymentExceedsRemainingAmount;
use App\Payment\PaymentRecorder;
use App\Repository\RentDueRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PaymentRecorderTest extends KernelTestCase
{
    private PaymentRecorder $recorder;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->recorder = self::getContainer()->get(PaymentRecorder::class);
    }

    public function testAPartialPaymentGivesAReceipt(): void
    {
        $due = $this->octoberDue();

        $this->recorder->record($due, 50000, new \DateTimeImmutable('2026-10-05'), PaymentMethod::Transfer);

        self::assertSame(RentDue::STATUS_PARTIALLY_PAID, $due->getStatus());
        self::assertSame(ReceiptType::PartialPaymentReceipt, $due->getReceiptType());
        self::assertSame(28000, $due->getRemainingAmount());
    }

    public function testTheLastPaymentGivesAQuittance(): void
    {
        $due = $this->octoberDue();

        $this->recorder->record($due, 50000, new \DateTimeImmutable('2026-10-05'), PaymentMethod::Transfer);
        $this->recorder->record($due, 28000, new \DateTimeImmutable('2026-10-12'), PaymentMethod::Check);

        self::assertSame(RentDue::STATUS_PAID, $due->getStatus());
        self::assertSame(ReceiptType::Quittance, $due->getReceiptType());
        // Payée en retard, mais payée : elle n'est plus en retard
        self::assertFalse($due->isOverdue(new \DateTimeImmutable('2026-10-20')));
    }

    public function testAPaymentAboveTheRemainingAmountIsRefusedAndNothingIsSaved(): void
    {
        $due = $this->octoberDue();

        try {
            $this->recorder->record($due, 78001, new \DateTimeImmutable('2026-10-05'), PaymentMethod::Transfer);
            self::fail('Le paiement aurait dû être refusé.');
        } catch (PaymentExceedsRemainingAmount $exception) {
            self::assertSame(78000, $exception->remainingAmount);
        }

        self::assertSame(0, $this->savedPaidAmount($due));
        self::assertSame(RentDue::STATUS_UNPAID, $due->getStatus());
    }

    /**
     * Deux onglets ouverts sur la même échéance : le premier enregistre 500 €, le second, qui affichait
     * encore « reste 780 € », tente d'enregistrer 780 €. Le verrou et la relecture de l'échéance l'empêchent.
     */
    public function testUsesTheAmountSavedByAPaymentRecordedInTheMeantime(): void
    {
        $due = $this->octoberDue();

        // Paiement enregistré par une autre requête : l'objet $due chargé ici n'est pas au courant
        self::getContainer()->get(Connection::class)->executeStatement(
            'UPDATE rent_due SET paid_amount = 50000, status = ? WHERE id = ?',
            [RentDue::STATUS_PARTIALLY_PAID, $due->getId()],
        );

        try {
            $this->recorder->record($due, 78000, new \DateTimeImmutable('2026-10-05'), PaymentMethod::Transfer);
            self::fail('Le paiement aurait dû être refusé : il ne reste que 280 € à payer.');
        } catch (PaymentExceedsRemainingAmount $exception) {
            self::assertSame(28000, $exception->remainingAmount);
        }

        // Après un refus, l'application continue de fonctionner normalement (l'EntityManager n'a pas été fermé)
        $this->recorder->record($due, 28000, new \DateTimeImmutable('2026-10-05'), PaymentMethod::Transfer);
        self::assertSame(RentDue::STATUS_PAID, $due->getStatus());
    }

    public function testAFullyPaidDueAcceptsNoMorePayment(): void
    {
        $due = $this->octoberDue();
        $this->recorder->record($due, 78000, new \DateTimeImmutable('2026-10-05'), PaymentMethod::Transfer);

        $this->expectException(PaymentExceedsRemainingAmount::class);
        $this->expectExceptionMessage('déjà entièrement payée');

        $this->recorder->record($due, 1, new \DateTimeImmutable('2026-10-06'), PaymentMethod::Transfer);
    }

    /**
     * Le workflow est décrit en YAML et utilisé en PHP via des constantes : ce test vérifie qu'ils concordent.
     */
    public function testWorkflowConfigurationMatchesTheEntityConstants(): void
    {
        $definition = self::getContainer()->get('state_machine.rent_due')->getDefinition();

        self::assertSame(
            [RentDue::STATUS_UNPAID, RentDue::STATUS_PARTIALLY_PAID, RentDue::STATUS_PAID],
            array_keys($definition->getPlaces()),
        );
        self::assertSame([RentDue::STATUS_UNPAID], $definition->getInitialPlaces());

        // Une transition qui part de deux états apparaît deux fois dans la définition
        $transitionNames = array_values(array_unique(array_map(
            static fn ($transition): string => $transition->getName(),
            $definition->getTransitions(),
        )));
        self::assertEqualsCanonicalizing([RentDue::TRANSITION_PAY_PARTIALLY, RentDue::TRANSITION_PAY_IN_FULL], $transitionNames);
    }

    private function octoberDue(): RentDue
    {
        $lease = LeaseFactory::new()->create([
            'startDate' => new \DateTimeImmutable('2025-09-01'),
            'rentTrackedFrom' => new \DateTimeImmutable('2026-10-01'),
            'rent' => 72000,
            'charges' => 6000,
            'deposit' => 72000,
            'paymentDay' => 5,
        ]);
        self::getContainer()->get(RentDueGenerator::class)->generateFor($lease, new \DateTimeImmutable('2026-10-15'));

        $due = self::getContainer()->get(RentDueRepository::class)->findOneBy(['lease' => $lease]);
        self::assertNotNull($due);

        return $due;
    }

    private function savedPaidAmount(RentDue $due): int
    {
        return (int) self::getContainer()->get(Connection::class)
            ->fetchOne('SELECT paid_amount FROM rent_due WHERE id = ?', [$due->getId()]);
    }
}
