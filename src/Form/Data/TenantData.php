<?php

declare(strict_types=1);

namespace App\Form\Data;

use Symfony\Component\Validator\Constraints as Assert;

final class TenantData
{
    #[Assert\NotBlank(message: 'Indiquez le prénom.')]
    #[Assert\Length(max: 100)]
    public ?string $firstName = null;

    #[Assert\NotBlank(message: 'Indiquez le nom.')]
    #[Assert\Length(max: 100)]
    public ?string $lastName = null;

    #[Assert\NotBlank(message: 'Indiquez l\'adresse e-mail.')]
    #[Assert\Email(message: 'Cette adresse e-mail n\'est pas valide.')]
    #[Assert\Length(max: 180)]
    public ?string $email = null;
}
