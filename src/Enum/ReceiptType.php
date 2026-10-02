<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Justificatif remis au locataire (article 21 de la loi du 6 juillet 1989) : une quittance quand le loyer
 * est payé en entier, un reçu quand il ne l'est qu'en partie.
 */
enum ReceiptType: string
{
    case Quittance = 'quittance';
    case PartialPaymentReceipt = 'recu';

    public function label(): string
    {
        return match ($this) {
            self::Quittance => 'Quittance de loyer',
            self::PartialPaymentReceipt => 'Reçu de paiement partiel',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Quittance => 'Quittance',
            self::PartialPaymentReceipt => 'Reçu',
        };
    }
}
