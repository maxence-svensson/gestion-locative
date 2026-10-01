<?php

declare(strict_types=1);

namespace App\Enum;

enum HousingType: string
{
    case Apartment = 'apartment';
    case House = 'house';

    public function label(): string
    {
        return match ($this) {
            self::Apartment => 'Appartement',
            self::House => 'Maison',
        };
    }
}
