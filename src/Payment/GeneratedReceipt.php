<?php

declare(strict_types=1);

namespace App\Payment;

final readonly class GeneratedReceipt
{
    public function __construct(
        public string $filename,
        public string $pdf,
    ) {
    }
}
