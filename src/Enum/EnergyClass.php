<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Classe énergie du diagnostic de performance énergétique (DPE).
 */
enum EnergyClass: string
{
    case A = 'A';
    case B = 'B';
    case C = 'C';
    case D = 'D';
    case E = 'E';
    case F = 'F';
    case G = 'G';

    /**
     * Logements classés F ou G (« passoires thermiques ») : depuis le 24 août 2022 en métropole,
     * le loyer ne peut plus augmenter, ni à la révision annuelle, ni à la relocation (loi Climat et résilience).
     */
    public function freezesRent(): bool
    {
        return self::F === $this || self::G === $this;
    }
}
