<?php

declare(strict_types=1);

namespace App\Lease;

use App\Entity\Lease;
use App\Entity\RentDue;

/**
 * Calendrier des loyers d'un bail : quels mois sont dus, à quelle date et pour quel montant.
 * Calcul pur, sans base de données : testé unitairement.
 */
final class RentSchedule
{
    /**
     * Mois à facturer, du début du suivi jusqu'au mois de $until inclus.
     *
     * @return list<\DateTimeImmutable>
     */
    public function periods(Lease $lease, \DateTimeImmutable $until): array
    {
        $periods = [];
        $lastPeriod = Lease::firstDayOfMonth($until);

        for ($period = $lease->getRentTrackedFrom(); $period <= $lastPeriod; $period = $period->modify('+1 month')) {
            $periods[] = $period;
        }

        return $periods;
    }

    public function dueFor(Lease $lease, \DateTimeImmutable $period): RentDue
    {
        $rent = $lease->getRent();
        $charges = $lease->getCharges();

        // Bail commencé en cours de mois : le premier loyer est calculé au prorata des jours occupés
        $startDate = $lease->getStartDate();
        if (Lease::firstDayOfMonth($startDate) == $period && '1' !== $startDate->format('j')) {
            $daysInMonth = (int) $period->format('t');
            $occupiedDays = $daysInMonth - (int) $startDate->format('j') + 1;
            $rent = self::prorate($rent, $occupiedDays, $daysInMonth);
            $charges = self::prorate($charges, $occupiedDays, $daysInMonth);
        }

        return new RentDue($lease, $period, $this->dueDateFor($lease, $period), $rent, $charges);
    }

    /**
     * Le loyer est dû au jour prévu par le bail, mais jamais avant le début du bail.
     */
    private function dueDateFor(Lease $lease, \DateTimeImmutable $period): \DateTimeImmutable
    {
        $dueDate = $period->setDate((int) $period->format('Y'), (int) $period->format('n'), $lease->getPaymentDay());

        return max($dueDate, $lease->getStartDate()->setTime(0, 0));
    }

    /**
     * Arrondi au centime le plus proche, en restant en nombres entiers :
     * round(a × j / n) = floor((2 × a × j + n) / (2 × n)).
     */
    private static function prorate(int $amount, int $days, int $daysInMonth): int
    {
        return intdiv(2 * $amount * $days + $daysInMonth, 2 * $daysInMonth);
    }
}
