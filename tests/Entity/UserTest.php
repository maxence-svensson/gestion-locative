<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testEmailIsStoredLowercaseAndTrimmed(): void
    {
        $user = new User('  Claire.Martin@Exemple.FR ', 'Claire', 'Martin');

        self::assertSame('claire.martin@exemple.fr', $user->getEmail());
        self::assertSame('claire.martin@exemple.fr', $user->getUserIdentifier());
    }

    public function testEmailCannotBeBlank(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new User('   ', 'Claire', 'Martin');
    }

    public function testEveryUserHasRoleUserExactlyOnce(): void
    {
        $user = new User('claire@exemple.fr', 'Claire', 'Martin');
        $user->setRoles([User::ROLE_OWNER, 'ROLE_USER']);

        self::assertSame([User::ROLE_OWNER, 'ROLE_USER'], $user->getRoles());
    }

    public function testSessionDoesNotContainThePasswordHash(): void
    {
        $user = new User('claire@exemple.fr', 'Claire', 'Martin');
        $user->setPassword('$2y$13$hash-du-mot-de-passe');

        self::assertStringNotContainsString('hash-du-mot-de-passe', serialize($user));
    }
}
