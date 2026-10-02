<?php

declare(strict_types=1);

namespace App\Payment;

use App\Formatter\MoneyFormatter;

/**
 * Le paiement dépasse le reste à payer, par exemple parce qu'un autre paiement vient d'être enregistré
 * sur la même échéance (deux onglets ouverts, double clic).
 */
final class PaymentExceedsRemainingAmount extends \DomainException
{
    public function __construct(
        public readonly int $remainingAmount,
    ) {
        parent::__construct(0 === $remainingAmount
            ? 'Cette échéance est déjà entièrement payée.'
            : \sprintf('Le reste à payer est de %s : le paiement ne peut pas le dépasser.', MoneyFormatter::format($remainingAmount)));
    }
}
