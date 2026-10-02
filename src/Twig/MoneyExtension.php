<?php

declare(strict_types=1);

namespace App\Twig;

use App\Formatter\MoneyFormatter;
use Twig\Attribute\AsTwigFilter;

final class MoneyExtension
{
    /**
     * Dans un template : {{ lease.rent|money }}.
     */
    #[AsTwigFilter('money')]
    public function formatMoney(int $cents): string
    {
        return MoneyFormatter::format($cents);
    }
}
