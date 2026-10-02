<?php

declare(strict_types=1);

namespace App\Form\Data;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Mot de passe choisi par un locataire invité, à la création de son compte.
 */
final class AccountActivationData
{
    #[Assert\NotBlank(message: 'Choisissez un mot de passe.')]
    #[Assert\Length(
        min: 12,
        max: 4096,
        minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
    )]
    #[Assert\PasswordStrength(
        minScore: Assert\PasswordStrength::STRENGTH_MEDIUM,
        message: 'Ce mot de passe est trop facile à deviner : allongez-le ou mélangez lettres, chiffres et symboles.',
    )]
    public ?string $plainPassword = null;
}
