<?php

declare(strict_types=1);

namespace App\Enum;

enum PaymentMethod: string
{
    case Transfer = 'transfer';
    case DirectDebit = 'direct_debit';
    case Check = 'check';
    case Cash = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::Transfer => 'Virement',
            self::DirectDebit => 'Prélèvement',
            self::Check => 'Chèque',
            self::Cash => 'Espèces',
        };
    }
}
