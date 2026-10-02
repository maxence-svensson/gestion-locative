<?php

declare(strict_types=1);

namespace App\Lease;

/**
 * Plafond légal du dépôt de garantie (loi du 6 juillet 1989) :
 * un mois de loyer hors charges en location vide (article 22), deux mois en meublé (article 25-6).
 */
final class DepositLimit
{
    public static function maximumFor(int $monthlyRent, bool $furnished): int
    {
        return $monthlyRent * ($furnished ? 2 : 1);
    }

    public static function allows(int $deposit, int $monthlyRent, bool $furnished): bool
    {
        return $deposit >= 0 && $deposit <= self::maximumFor($monthlyRent, $furnished);
    }
}
