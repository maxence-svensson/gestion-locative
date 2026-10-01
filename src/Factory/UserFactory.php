<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<User>
 */
final class UserFactory extends PersistentObjectFactory
{
    public const string DEFAULT_PASSWORD = 'password';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    public static function class(): string
    {
        return User::class;
    }

    public function asOwner(): static
    {
        return $this->with(['roles' => [User::ROLE_OWNER]]);
    }

    public function asTenant(): static
    {
        return $this->with(['roles' => [User::ROLE_TENANT]]);
    }

    protected function defaults(): array
    {
        return [
            'email' => self::faker()->unique()->safeEmail(),
            'firstName' => self::faker()->firstName(),
            'lastName' => self::faker()->lastName(),
            'password' => self::DEFAULT_PASSWORD,
            'roles' => [],
        ];
    }

    protected function initialize(): static
    {
        // Le mot de passe est fourni en clair à la factory, puis haché avant l'enregistrement
        return $this->afterInstantiate(function (User $user): void {
            $plainPassword = $user->getPassword();

            if (null !== $plainPassword) {
                $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
            }
        });
    }
}
