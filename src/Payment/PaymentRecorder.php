<?php

declare(strict_types=1);

namespace App\Payment;

use App\Entity\Payment;
use App\Entity\RentDue;
use App\Enum\PaymentMethod;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Enregistre un paiement sur une échéance et fait avancer son état dans le workflow « rent_due ».
 */
final class PaymentRecorder
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Target('rent_due')]
        private readonly WorkflowInterface $rentDueStateMachine,
    ) {
    }

    /**
     * @throws PaymentExceedsRemainingAmount
     */
    public function record(RentDue $due, int $amount, \DateTimeImmutable $paidOn, PaymentMethod $method): Payment
    {
        // Transaction gérée à la main : EntityManager::wrapInTransaction() fermerait l'EntityManager au premier refus,
        // et la page ne pourrait plus afficher le formulaire avec son message d'erreur.
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            // Verrouille la ligne de l'échéance jusqu'à la fin de la transaction (SELECT … FOR UPDATE) et la relit.
            // Si deux paiements arrivent en même temps, le second attend le premier puis part du montant à jour :
            // impossible d'encaisser plus que le montant dû.
            $this->entityManager->refresh($due, LockMode::PESSIMISTIC_WRITE);

            if ($amount > $due->getRemainingAmount()) {
                throw new PaymentExceedsRemainingAmount($due->getRemainingAmount());
            }

            $payment = $due->addPayment($amount, $paidOn, $method);
            $this->rentDueStateMachine->apply($due, 0 === $due->getRemainingAmount()
                ? RentDue::TRANSITION_PAY_IN_FULL
                : RentDue::TRANSITION_PAY_PARTIALLY);

            $this->entityManager->flush();
            $connection->commit();

            return $payment;
        } catch (\Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }
    }
}
