<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Attribute\AsTwigFilter;

final class DateExtension
{
    /**
     * Date longue en français : {{ lease.startDate|long_date }} → « 1er juillet 2024 ».
     */
    #[AsTwigFilter('long_date')]
    public function formatLongDate(\DateTimeInterface $date): string
    {
        $formatter = new \IntlDateFormatter('fr_FR', \IntlDateFormatter::LONG, \IntlDateFormatter::NONE, $date->getTimezone());
        $formatted = (string) $formatter->format($date);

        // En français, le premier jour du mois s'écrit « 1er » (le format de la bibliothèque ICU donne « 1 »)
        if (1 === (int) $date->format('j')) {
            return (string) preg_replace('/^1(?=\s)/u', '1er', $formatted);
        }

        return $formatted;
    }
}
