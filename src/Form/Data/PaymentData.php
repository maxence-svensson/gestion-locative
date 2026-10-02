<?php

declare(strict_types=1);

namespace App\Form\Data;

use App\Entity\RentDue;
use App\Enum\PaymentMethod;
use App\Formatter\MoneyFormatter;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Données saisies pour enregistrer un paiement sur une échéance.
 */
final class PaymentData
{
    #[Assert\NotNull(message: 'Indiquez le montant reçu.')]
    #[Assert\Positive(message: 'Le montant doit être supérieur à 0.')]
    public ?int $amount = null;

    #[Assert\NotNull(message: 'Indiquez la date du paiement.')]
    public ?\DateTimeImmutable $paidOn = null;

    #[Assert\NotNull(message: 'Choisissez le moyen de paiement.')]
    public ?PaymentMethod $method = null;

    private function __construct(
        public readonly int $remainingAmount,
        private readonly \DateTimeImmutable $today,
    ) {
    }

    /**
     * Pré-rempli avec le cas le plus courant : le reste à payer, reçu aujourd'hui par virement.
     */
    public static function forDue(RentDue $due, \DateTimeImmutable $today): self
    {
        $data = new self($due->getRemainingAmount(), $today->setTime(0, 0));
        $data->amount = $due->getRemainingAmount();
        $data->paidOn = $data->today;
        $data->method = PaymentMethod::Transfer;

        return $data;
    }

    #[Assert\Callback]
    public function validateBusinessRules(ExecutionContextInterface $context): void
    {
        if (null !== $this->amount && $this->amount > $this->remainingAmount) {
            $context->buildViolation(\sprintf(
                'Le reste à payer est de %s : le paiement ne peut pas le dépasser.',
                MoneyFormatter::format($this->remainingAmount),
            ))->atPath('amount')->addViolation();
        }

        if (null !== $this->paidOn && $this->paidOn > $this->today) {
            $context->buildViolation('La date du paiement ne peut pas être dans le futur.')
                ->atPath('paidOn')
                ->addViolation();
        }
    }
}
