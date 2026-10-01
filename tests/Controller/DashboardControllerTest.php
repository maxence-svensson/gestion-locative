<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DashboardControllerTest extends WebTestCase
{
    public function testOwnerIsSentToOwnerSpace(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::new()->asOwner()->create());

        $client->request('GET', '/espace');

        self::assertResponseRedirects('/proprietaire');
    }

    public function testTenantIsSentToTenantSpace(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::new()->asTenant()->create());

        $client->request('GET', '/espace');

        self::assertResponseRedirects('/locataire');
    }

    public function testUserWithBothRolesCanSwitchBetweenSpaces(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::new()->create(['roles' => [User::ROLE_OWNER, User::ROLE_TENANT]]));

        $client->request('GET', '/espace');
        self::assertResponseRedirects('/proprietaire');

        $client->followRedirect();
        self::assertSelectorTextContains('.topbar__switch [aria-current="page"]', 'Propriétaire');

        $client->clickLink('Locataire');
        self::assertRouteSame('tenant_dashboard');
    }

    public function testAnonymousVisitorIsSentToLoginPage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/espace');

        self::assertResponseRedirects('/connexion');
    }
}
