<?php

declare(strict_types=1);

namespace App\Story;

use App\Factory\UserFactory;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

/**
 * Données de démonstration, chargées avec `make fixtures`.
 * Les comptes de démo sont listés dans le README.
 */
#[AsFixture(name: 'main')]
final class AppStory extends Story
{
    public const string DEMO_PASSWORD = 'demo1234';

    public function build(): void
    {
        UserFactory::new()->asOwner()->create([
            'email' => 'proprietaire@demo.test',
            'firstName' => 'Claire',
            'lastName' => 'Martin',
            'password' => self::DEMO_PASSWORD,
        ]);

        UserFactory::new()->asTenant()->create([
            'email' => 'locataire@demo.test',
            'firstName' => 'Karim',
            'lastName' => 'Benali',
            'password' => self::DEMO_PASSWORD,
        ]);
    }
}
