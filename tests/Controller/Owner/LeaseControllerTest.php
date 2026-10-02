<?php

declare(strict_types=1);

namespace App\Tests\Controller\Owner;

use App\Entity\Lease;
use App\Entity\Property;
use App\Entity\User;
use App\Factory\LeaseFactory;
use App\Factory\PropertyFactory;
use App\Factory\UserFactory;
use App\Repository\LeaseRepository;
use App\Repository\PropertyRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LeaseControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private User $owner;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->owner = UserFactory::new()->asOwner()->create();
        $this->client->loginUser($this->owner);
    }

    public function testOwnerCreatesALease(): void
    {
        $property = $this->myProperty();

        $this->client->request('GET', $this->newLeaseUrl($property));
        $this->client->submitForm('Créer le bail', [
            ...$this->validValues(),
            // Montants écrits comme le ferait un utilisateur français
            'lease_form[rent]' => '1 234,56',
            'lease_form[deposit]' => '1 000',
        ]);

        self::assertResponseRedirects(\sprintf('/proprietaire/biens/%d', $property->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert--success', 'Le bail a été créé.');
        self::assertSelectorTextContains('.card', 'Karim Benali');

        $lease = $this->leaseOf($property);
        self::assertSame(123456, $lease->getRent());
        self::assertSame(6000, $lease->getCharges());
        self::assertSame(100000, $lease->getDeposit());
        self::assertSame(129456, $lease->getMonthlyTotal());
        self::assertSame('2025-09-01', $lease->getStartDate()->format('Y-m-d'));
    }

    public function testColocationWithTwoTenants(): void
    {
        $property = $this->myProperty();

        // Le formulaire n'affiche qu'une ligne : la seconde est celle qu'ajouterait le bouton « Ajouter un colocataire »
        $this->submitWithTenants($property, [
            ['firstName' => 'Léa', 'lastName' => 'Moreau', 'email' => 'lea@exemple.fr'],
            ['firstName' => 'Hugo', 'lastName' => 'Lambert', 'email' => 'hugo@exemple.fr'],
        ]);

        self::assertResponseRedirects();
        self::assertSame(
            ['Léa Moreau', 'Hugo Lambert'],
            array_map(static fn ($tenant): string => $tenant->getFullName(), $this->leaseOf($property)->getTenants()),
        );
    }

    public function testDepositAboveOneMonthIsRefusedForAnUnfurnishedProperty(): void
    {
        $property = $this->myProperty(furnished: false);

        $this->client->request('GET', $this->newLeaseUrl($property));
        $this->client->submitForm('Créer le bail', [...$this->validValues(), 'lease_form[deposit]' => '1440']);

        self::assertResponseStatusCodeSame(422);
        self::assertAnySelectorTextContains('.form__errors', 'ne peut pas dépasser 720,00');
        self::assertAnySelectorTextContains('.form__errors', 'un mois de loyer hors charges pour une location vide');
        self::assertNull($this->leases()->findOneByProperty($property));
    }

    public function testFurnishedPropertyAcceptsTwoMonthsOfDeposit(): void
    {
        $property = $this->myProperty(furnished: true);

        $this->client->request('GET', $this->newLeaseUrl($property));
        $this->client->submitForm('Créer le bail', [...$this->validValues(), 'lease_form[deposit]' => '1440']);

        self::assertResponseRedirects();
        self::assertSame(144000, $this->leaseOf($property)->getDeposit());
    }

    public function testEachTenantNeedsTheirOwnEmail(): void
    {
        $property = $this->myProperty();

        $this->submitWithTenants($property, [
            ['firstName' => 'Léa', 'lastName' => 'Moreau', 'email' => 'colocation@exemple.fr'],
            ['firstName' => 'Hugo', 'lastName' => 'Lambert', 'email' => 'Colocation@Exemple.fr'],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertAnySelectorTextContains('.form__errors', 'Chaque locataire doit avoir sa propre adresse e-mail.');
    }

    public function testAtLeastOneTenantIsRequired(): void
    {
        $property = $this->myProperty();

        $this->submitWithTenants($property, []);

        self::assertResponseStatusCodeSame(422);
        self::assertAnySelectorTextContains('.form__errors', 'Ajoutez au moins un locataire.');
    }

    public function testIrlReferenceCannotBeAfterTheLeaseStart(): void
    {
        $property = $this->myProperty();

        $this->client->request('GET', $this->newLeaseUrl($property));
        $this->client->submitForm('Créer le bail', [...$this->validValues(), 'lease_form[irlReferenceYear]' => '2026']);

        self::assertResponseStatusCodeSame(422);
        self::assertAnySelectorTextContains('.form__errors', 'ne peut pas être postérieur au début du bail');
    }

    public function testAPropertyCannotHaveTwoLeases(): void
    {
        $property = $this->myProperty();
        LeaseFactory::new()->create(['property' => $property]);

        $this->client->request('GET', $this->newLeaseUrl($property));

        self::assertResponseRedirects(\sprintf('/proprietaire/biens/%d', $property->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert--warning', 'Ce bien a déjà un bail en cours.');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function methods(): iterable
    {
        yield 'afficher le formulaire' => ['GET'];
        yield 'envoyer le formulaire' => ['POST'];
    }

    #[DataProvider('methods')]
    public function testOwnerCannotCreateALeaseOnSomeoneElsesProperty(string $method): void
    {
        $someoneElsesProperty = PropertyFactory::new()->create();

        $this->client->request($method, $this->newLeaseUrl($someoneElsesProperty));

        self::assertResponseStatusCodeSame(403);
        self::assertNull($this->leases()->findOneByProperty($someoneElsesProperty));
    }

    public function testLeasedPropertyCannotBeDeleted(): void
    {
        $leasedProperty = $this->myProperty();
        LeaseFactory::new()->create(['property' => $leasedProperty]);

        // Le bouton n'est pas affiché pour un bien loué : on récupère un jeton valide sur un bien vacant
        $crawler = $this->client->request('GET', \sprintf('/proprietaire/biens/%d', $this->myProperty()->getId()));
        $token = (string) $crawler->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/proprietaire/biens/%d/supprimer', $leasedProperty->getId()), ['_token' => $token]);

        self::assertResponseRedirects(\sprintf('/proprietaire/biens/%d', $leasedProperty->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert--warning', 'Ce bien est loué : il ne peut pas être supprimé.');
        self::assertNotNull(self::getContainer()->get(PropertyRepository::class)->find((int) $leasedProperty->getId()));
    }

    /**
     * @return array<string, string>
     */
    private function validValues(): array
    {
        return [
            'lease_form[startDate]' => '2025-09-01',
            'lease_form[paymentDay]' => '5',
            'lease_form[rent]' => '720',
            'lease_form[charges]' => '60',
            'lease_form[deposit]' => '720',
            'lease_form[irlReferenceQuarter]' => '2',
            'lease_form[irlReferenceYear]' => '2025',
            'lease_form[tenants][0][firstName]' => 'Karim',
            'lease_form[tenants][0][lastName]' => 'Benali',
            'lease_form[tenants][0][email]' => 'karim@exemple.fr',
        ];
    }

    /**
     * Envoie le formulaire avec une liste de locataires choisie, comme après des clics sur « Ajouter » ou « Retirer ».
     *
     * @param list<array{firstName: string, lastName: string, email: string}> $tenants
     */
    private function submitWithTenants(Property $property, array $tenants): void
    {
        $crawler = $this->client->request('GET', $this->newLeaseUrl($property));
        $form = $crawler->selectButton('Créer le bail')->form($this->validValues());

        $values = $form->getPhpValues();
        $values['lease_form']['tenants'] = $tenants;

        $this->client->request('POST', $form->getUri(), $values);
    }

    private function myProperty(bool $furnished = false): Property
    {
        return PropertyFactory::new()->create(['owner' => $this->owner, 'furnished' => $furnished]);
    }

    private function newLeaseUrl(Property $property): string
    {
        return \sprintf('/proprietaire/biens/%d/bail/nouveau', $property->getId());
    }

    private function leaseOf(Property $property): Lease
    {
        $lease = $this->leases()->findOneByProperty($property);
        self::assertNotNull($lease, 'Le bail aurait dû être créé.');

        return $lease;
    }

    private function leases(): LeaseRepository
    {
        return self::getContainer()->get(LeaseRepository::class);
    }
}
