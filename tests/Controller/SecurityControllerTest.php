<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SecurityControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        // Le compteur de tentatives de connexion est stocké dans le cache, qui survit d'un test à l'autre :
        // sans remise à zéro, les échecs d'un test pourraient bloquer la connexion dans le suivant.
        self::getContainer()->get('cache.rate_limiter')->clear();
    }

    public function testLoginPageIsDisplayed(): void
    {
        $this->client->request('GET', '/connexion');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Connexion');
    }

    public function testOwnerLandsOnOwnerSpaceAfterLogin(): void
    {
        UserFactory::new()->asOwner()->create(['email' => 'claire@exemple.fr']);

        $this->logIn('claire@exemple.fr', UserFactory::DEFAULT_PASSWORD);

        self::assertRouteSame('owner_dashboard');
    }

    public function testTenantLandsOnTenantSpaceAfterLogin(): void
    {
        UserFactory::new()->asTenant()->create(['email' => 'karim@exemple.fr']);

        $this->logIn('karim@exemple.fr', UserFactory::DEFAULT_PASSWORD);

        self::assertRouteSame('tenant_dashboard');
    }

    public function testEmailIsCaseInsensitive(): void
    {
        UserFactory::new()->asOwner()->create(['email' => 'claire@exemple.fr']);

        $this->logIn('  Claire@Exemple.FR ', UserFactory::DEFAULT_PASSWORD);

        self::assertRouteSame('owner_dashboard');
    }

    public function testWrongPasswordShowsAnError(): void
    {
        UserFactory::new()->asOwner()->create(['email' => 'claire@exemple.fr']);

        $this->logIn('claire@exemple.fr', 'mauvais-mot-de-passe');

        self::assertRouteSame('app_login');
        self::assertSelectorTextContains('.alert--error', 'Identifiants invalides.');
    }

    public function testLoginIsBlockedAfterFiveFailedAttempts(): void
    {
        UserFactory::new()->asOwner()->create(['email' => 'claire@exemple.fr']);

        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $this->logIn('claire@exemple.fr', 'mauvais-mot-de-passe');
        }

        // Même avec le bon mot de passe, la 6e tentative est refusée
        $this->logIn('claire@exemple.fr', UserFactory::DEFAULT_PASSWORD);

        self::assertRouteSame('app_login');
        self::assertSelectorTextContains('.alert--error', 'Trop de tentatives');
    }

    public function testLoggedInUserIsRedirectedAwayFromLoginPage(): void
    {
        $this->client->loginUser(UserFactory::new()->asOwner()->create());

        $this->client->request('GET', '/connexion');

        self::assertResponseRedirects('/espace');
    }

    public function testLogoutBringsBackToHomePage(): void
    {
        $this->client->loginUser(UserFactory::new()->asOwner()->create());

        $this->client->request('GET', '/deconnexion');
        $this->client->followRedirect();

        self::assertRouteSame('app_home');
        self::assertSelectorTextContains('.topbar', 'Se connecter');
    }

    /**
     * Remplit le vrai formulaire de connexion et suit les redirections jusqu'à la page finale.
     */
    private function logIn(string $email, string $password): void
    {
        $this->client->followRedirects();
        $this->client->request('GET', '/connexion');
        $this->client->submitForm('Se connecter', [
            'email' => $email,
            'password' => $password,
        ]);
        $this->client->followRedirects(false);
    }
}
