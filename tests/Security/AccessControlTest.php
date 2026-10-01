<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\User;
use App\Factory\UserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Vérifie, pour chaque espace, qui peut y entrer et qui en est refoulé.
 */
final class AccessControlTest extends WebTestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function protectedPages(): iterable
    {
        yield 'espace propriétaire' => ['/proprietaire', User::ROLE_OWNER];
        yield 'espace locataire' => ['/locataire', User::ROLE_TENANT];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function protectedUrls(): iterable
    {
        foreach (self::protectedPages() as $name => [$url]) {
            yield $name => [$url];
        }
    }

    #[DataProvider('protectedUrls')]
    public function testAnonymousVisitorIsSentToLoginPage(string $url): void
    {
        $client = static::createClient();
        $client->request('GET', $url);

        self::assertResponseRedirects('/connexion');
    }

    #[DataProvider('protectedPages')]
    public function testUserWithTheRightRoleCanEnter(string $url, string $role): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::new()->create(['roles' => [$role]]));

        $client->request('GET', $url);

        self::assertResponseIsSuccessful();
    }

    public function testTenantCannotEnterOwnerSpace(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::new()->asTenant()->create());

        $client->request('GET', '/proprietaire');

        self::assertResponseStatusCodeSame(403);
    }

    public function testOwnerCannotEnterTenantSpace(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::new()->asOwner()->create());

        $client->request('GET', '/locataire');

        self::assertResponseStatusCodeSame(403);
    }
}
