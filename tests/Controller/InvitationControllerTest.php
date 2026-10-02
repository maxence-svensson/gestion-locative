<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Tenant;
use App\Entity\User;
use App\Factory\LeaseFactory;
use App\Factory\PropertyFactory;
use App\Factory\UserFactory;
use App\Repository\TenantRepository;
use App\Repository\UserRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Mime\Email;

/**
 * Parcours complet : le propriétaire invite son locataire, qui reçoit un e-mail et active son espace.
 */
final class InvitationControllerTest extends WebTestCase
{
    use ClockSensitiveTrait;

    private const string STRONG_PASSWORD = 'Un loyer bien payé, 4 fois !';

    private KernelBrowser $client;
    private User $owner;

    protected function setUp(): void
    {
        self::mockTime('2026-10-15 10:00');
        $this->client = static::createClient();
        $this->owner = UserFactory::new()->asOwner()->create(['firstName' => 'Maxence', 'lastName' => 'Svensson']);
    }

    public function testOwnerSendsAnInvitationByEmail(): void
    {
        $tenant = $this->tenant();

        $this->sendInvitation($tenant);

        self::assertResponseRedirects(\sprintf('/proprietaire/biens/%d', $tenant->getLease()->getProperty()->getId()));
        self::assertQueuedEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertEmailAddressContains($email, 'To', 'karim@exemple.fr');
        self::assertEmailHeaderSame($email, 'Subject', 'Maxence Svensson vous invite dans votre espace locataire');
        self::assertEmailTextBodyContains($email, '12 rue d\'Austerlitz, 69004 Lyon');

        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert--success', 'Invitation envoyée à karim@exemple.fr');
        self::assertSelectorTextContains('.tenant-summary', 'Invité le 15 octobre 2026');
    }

    public function testTheLinkItselfIsNeverStored(): void
    {
        $tenant = $this->tenant();
        $token = $this->invite($tenant);

        $stored = self::getContainer()->get(Connection::class)
            ->fetchOne('SELECT invitation_token_hash FROM tenant WHERE id = ?', [$tenant->getId()]);

        self::assertNotSame($token, $stored);
        self::assertSame(hash('sha256', $token), $stored);
    }

    public function testNewTenantChoosesAPasswordAndLandsInTheirSpace(): void
    {
        $tenant = $this->tenant();
        $token = $this->invite($tenant);

        $this->client->request('GET', '/invitation/'.$token);
        self::assertSelectorTextContains('h1', 'Bonjour Karim');

        $this->client->submitForm('Activer mon espace', [
            'account_activation_form[plainPassword][first]' => self::STRONG_PASSWORD,
            'account_activation_form[plainPassword][second]' => self::STRONG_PASSWORD,
        ]);

        self::assertResponseRedirects('/locataire');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert--success', 'Votre espace locataire est prêt.');
        self::assertSelectorTextContains('.card', '12 rue d\'Austerlitz, 69004 Lyon');

        $account = $this->account('karim@exemple.fr');
        self::assertTrue($account->hasRole(User::ROLE_TENANT));
        self::assertFalse($account->hasRole(User::ROLE_OWNER));
        self::assertSame('Karim Benali', $account->getFullName());
        self::assertTrue($this->reload($tenant)->hasAccount());
    }

    public function testTheLinkWorksOnlyOnce(): void
    {
        $token = $this->invite($this->tenant());
        $this->activate($token);

        $this->client->request('GET', '/deconnexion');
        $this->client->request('GET', '/invitation/'.$token);

        self::assertResponseStatusCodeSame(404);
        self::assertSelectorTextContains('h1', 'Ce lien n\'est plus valable');
    }

    /**
     * Certaines messageries ouvrent les liens pour les analyser : la simple ouverture ne doit rien activer.
     */
    public function testOpeningTheLinkChangesNothing(): void
    {
        $tenant = $this->tenant();
        $token = $this->invite($tenant);

        $this->client->request('GET', '/invitation/'.$token);

        self::assertResponseIsSuccessful();
        self::assertFalse($this->reload($tenant)->hasAccount());
        self::assertNull(self::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'karim@exemple.fr']));
    }

    public function testAnExpiredLinkIsRefused(): void
    {
        $token = $this->invite($this->tenant());

        self::mockTime('2026-10-22 10:00');
        $this->client->request('GET', '/invitation/'.$token);

        self::assertResponseStatusCodeSame(404);
        self::assertSelectorTextContains('.auth__intro', 'valable 7 jours');
    }

    public function testANewInvitationReplacesThePreviousLink(): void
    {
        $tenant = $this->tenant();
        $firstToken = $this->invite($tenant);
        $secondToken = $this->invite($tenant);

        $this->client->request('GET', '/invitation/'.$firstToken);
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/invitation/'.$secondToken);
        self::assertResponseIsSuccessful();
    }

    public function testAWeakPasswordIsRefused(): void
    {
        $token = $this->invite($this->tenant());

        $this->client->request('GET', '/invitation/'.$token);
        $this->client->submitForm('Activer mon espace', [
            'account_activation_form[plainPassword][first]' => 'azertyuiop12',
            'account_activation_form[plainPassword][second]' => 'azertyuiop12',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertAnySelectorTextContains('.form__errors', 'trop facile à deviner');
    }

    public function testBothPasswordsMustMatch(): void
    {
        $token = $this->invite($this->tenant());

        $this->client->request('GET', '/invitation/'.$token);
        $this->client->submitForm('Activer mon espace', [
            'account_activation_form[plainPassword][first]' => self::STRONG_PASSWORD,
            'account_activation_form[plainPassword][second]' => self::STRONG_PASSWORD.'!',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertAnySelectorTextContains('.form__errors', 'ne correspondent pas');
    }

    /**
     * Un propriétaire peut aussi être locataire ailleurs : la location est ajoutée à son compte existant.
     */
    public function testAnExistingAccountGetsTheRentalAdded(): void
    {
        $otherOwner = UserFactory::new()->asOwner()->create(['email' => 'karim@exemple.fr']);
        $token = $this->invite($this->tenant());

        // Connecté à son compte au moment où il ouvre le lien
        $this->client->loginUser($otherOwner);
        $this->client->request('GET', '/invitation/'.$token);
        self::assertSelectorTextContains('.auth__card', 'Un compte existe déjà');

        $this->client->submitForm('Ajouter à mon compte');

        self::assertResponseRedirects('/locataire');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.card', '12 rue d\'Austerlitz, 69004 Lyon');
        // Il garde son rôle de propriétaire et peut passer d'un espace à l'autre
        self::assertSelectorExists('.topbar__switch');

        $account = $this->account('karim@exemple.fr');
        self::assertTrue($account->hasRole(User::ROLE_OWNER));
        self::assertTrue($account->hasRole(User::ROLE_TENANT));
    }

    public function testAnExistingAccountNotLoggedInIsAskedToLogIn(): void
    {
        UserFactory::new()->asOwner()->create(['email' => 'karim@exemple.fr']);
        $token = $this->invite($this->tenant());

        $this->client->request('GET', '/invitation/'.$token);
        $this->client->submitForm('Ajouter à mon compte');

        self::assertResponseRedirects('/connexion');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert--success', 'Connectez-vous pour y accéder.');
    }

    public function testOwnerCannotInviteSomeoneElsesTenant(): void
    {
        $someoneElsesTenant = $this->tenant(UserFactory::new()->asOwner()->create());

        $this->sendInvitation($someoneElsesTenant);

        self::assertResponseStatusCodeSame(403);
        self::assertQueuedEmailCount(0);
    }

    public function testATenantWithAnAccountIsNotInvitedAgain(): void
    {
        $tenant = $this->tenant();
        $this->activate($this->invite($tenant));
        $this->client->request('GET', '/deconnexion');

        $this->sendInvitation($tenant);

        self::assertQueuedEmailCount(0);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert--warning', 'a déjà accès à son espace locataire');
    }

    private function tenant(?User $owner = null): Tenant
    {
        $lease = LeaseFactory::new()->create([
            'property' => PropertyFactory::new([
                'owner' => $owner ?? $this->owner,
                'addressLine' => '12 rue d\'Austerlitz',
                'postalCode' => '69004',
                'city' => 'Lyon',
            ]),
            'tenants' => [['Karim', 'Benali', 'karim@exemple.fr']],
        ]);

        return $lease->getTenants()[0];
    }

    /**
     * Le propriétaire clique sur « Inviter dans son espace » depuis la fiche du bien.
     */
    private function sendInvitation(Tenant $tenant): void
    {
        $this->client->loginUser($this->owner);
        $crawler = $this->client->request('GET', \sprintf('/proprietaire/biens/%d', $this->propertyOfOwner()));
        $token = (string) $crawler->filter('form[action$="/inviter"] input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/proprietaire/locataires/%d/inviter', $tenant->getId()), ['_token' => $token]);
    }

    /**
     * Envoie l'invitation et renvoie le jeton lu dans l'e-mail, puis déconnecte le propriétaire.
     */
    private function invite(Tenant $tenant): string
    {
        $this->sendInvitation($tenant);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame(1, preg_match('#/invitation/([A-Za-z0-9_-]{43})#', (string) $email->getTextBody(), $matches));
        $this->client->request('GET', '/deconnexion');

        return $matches[1];
    }

    private function activate(string $token): void
    {
        $this->client->request('GET', '/invitation/'.$token);
        $this->client->submitForm('Activer mon espace', [
            'account_activation_form[plainPassword][first]' => self::STRONG_PASSWORD,
            'account_activation_form[plainPassword][second]' => self::STRONG_PASSWORD,
        ]);
        self::assertResponseRedirects('/locataire');
    }

    /**
     * Une fiche de bien du propriétaire, pour y lire un jeton CSRF valide (il en crée une si besoin).
     */
    private function propertyOfOwner(): int
    {
        $property = PropertyFactory::new()->create(['owner' => $this->owner]);
        LeaseFactory::new()->create(['property' => $property]);

        return (int) $property->getId();
    }

    private function account(string $email): User
    {
        $account = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        self::assertNotNull($account);

        return $account;
    }

    private function reload(Tenant $tenant): Tenant
    {
        $reloaded = self::getContainer()->get(TenantRepository::class)->find((int) $tenant->getId());
        self::assertNotNull($reloaded);

        return $reloaded;
    }
}
