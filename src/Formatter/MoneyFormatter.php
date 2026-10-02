<?php

declare(strict_types=1);

namespace App\Formatter;

/**
 * Affiche un montant stocké en centimes au format français : 123456 → « 1 234,56 € ».
 */
final class MoneyFormatter
{
    public static function format(int $cents): string
    {
        $formatter = new \NumberFormatter('fr_FR', \NumberFormatter::CURRENCY);

        return (string) $formatter->formatCurrency($cents / 100, 'EUR');
    }
}
