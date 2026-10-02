<?php

declare(strict_types=1);

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Montant saisi en euros, enregistré en centimes (int).
 *
 * « grouping » est indispensable : sans lui, Symfony refuse « 1 234,56 », la façon habituelle
 * d'écrire un montant en français (avec un espace entre les milliers).
 *
 * @extends AbstractType<int>
 */
final class EuroAmountType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'currency' => 'EUR',
            'divisor' => 100,
            'input' => 'integer',
            'grouping' => true,
            'invalid_message' => 'Saisissez un montant valide, par exemple 650 ou 1 234,56.',
        ]);
    }

    public function getParent(): string
    {
        return MoneyType::class;
    }
}
