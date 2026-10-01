<?php

declare(strict_types=1);

namespace App\Tests\Controller\Owner;

use App\Entity\Property;
use App\Entity\User;
use App\Enum\EnergyClass;
use App\Factory\PropertyFactory;
use App\Factory\UserFactory;
use App\Repository\PropertyRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PropertyControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private User $owner;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->owner = UserFactory::new()->asOwner()->create();
        $this->client->loginUser($this->owner);
    }

    public function testListShowsOnlyMyProperties(): void
    {
        PropertyFactory::new()->create(['owner' => $this->owner, 'name' => 'Mon T2']);
        PropertyFactory::new()->create(['name' => 'Le bien d\'un autre propriétaire']);

        $this->client->request('GET', '/proprietaire/biens');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.property-list', 'Mon T2');
        self::assertSelectorTextNotContains('.property-list', 'Le bien d\'un autre propriétaire');
    }

    public function testEmptyListInvitesToAddAProperty(): void
    {
        $this->client->request('GET', '/proprietaire/biens');

        self::assertSelectorTextContains('.empty-state', 'Aucun bien pour l\'instant');
        self::assertSelectorExists('.empty-state a[href="/proprietaire/biens/nouveau"]');
    }

    public function testOwnerCanAddAProperty(): void
    {
        $this->client->request('GET', '/proprietaire/biens/nouveau');
        $this->client->submitForm('Enregistrer', [
            'property_form[name]' => 'T2 Croix-Rousse',
            'property_form[addressLine]' => '12 rue d\'Austerlitz',
            'property_form[postalCode]' => '69004',
            'property_form[city]' => 'Lyon',
            'property_form[housingType]' => 'apartment',
            'property_form[surface]' => '48.5',
            'property_form[energyClass]' => 'C',
        ]);

        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'T2 Croix-Rousse');
        self::assertSelectorTextContains('.alert--success', 'Le bien a été ajouté.');

        $property = $this->findProperty('T2 Croix-Rousse');
        self::assertTrue($property->isOwnedBy($this->owner));
        self::assertSame(48.5, $property->getSurface());
        self::assertFalse($property->isFurnished());
    }

    public function testInvalidFormIsShownAgainWithErrors(): void
    {
        $this->client->request('GET', '/proprietaire/biens/nouveau');
        $this->client->submitForm('Enregistrer', [
            'property_form[name]' => 'T2 Croix-Rousse',
            'property_form[addressLine]' => '12 rue d\'Austerlitz',
            'property_form[postalCode]' => '6900',
            'property_form[city]' => 'Lyon',
            'property_form[housingType]' => 'apartment',
            'property_form[surface]' => '48.5',
        ]);

        // 422 : le statut qu'attend Turbo pour réafficher le formulaire
        self::assertResponseStatusCodeSame(422);
        self::assertAnySelectorTextContains('.form__errors', 'Le code postal doit contenir 5 chiffres.');
        self::assertAnySelectorTextContains('.form__errors', 'Indiquez la classe énergie du DPE.');
    }

    public function testOwnerCanEditTheirProperty(): void
    {
        $property = PropertyFactory::new()->create(['owner' => $this->owner, 'name' => 'Ancien nom']);

        $this->client->request('GET', \sprintf('/proprietaire/biens/%d/modifier', $property->getId()));
        $this->client->submitForm('Enregistrer', [
            'property_form[name]' => 'Nouveau nom',
            'property_form[energyClass]' => 'B',
        ]);

        self::assertResponseRedirects(\sprintf('/proprietaire/biens/%d', $property->getId()));
        $property = $this->findProperty('Nouveau nom');
        self::assertSame(EnergyClass::B, $property->getEnergyClass());
    }

    public function testOwnerCanDeleteTheirProperty(): void
    {
        $property = PropertyFactory::new()->create(['owner' => $this->owner, 'name' => 'À supprimer']);

        $this->client->request('GET', \sprintf('/proprietaire/biens/%d', $property->getId()));
        $this->client->submitForm('Supprimer');

        self::assertResponseRedirects('/proprietaire/biens');
        self::assertNull($this->propertyRepository()->findOneBy(['name' => 'À supprimer']));
    }

    public function testFrozenRentIsHighlightedForEnergySieves(): void
    {
        $property = PropertyFactory::new()->create(['owner' => $this->owner, 'energyClass' => EnergyClass::G]);

        $this->client->request('GET', \sprintf('/proprietaire/biens/%d', $property->getId()));

        self::assertSelectorTextContains('.alert--warning', 'son loyer ne peut plus augmenter');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function actionsOnAProperty(): iterable
    {
        yield 'consulter' => ['GET', '/proprietaire/biens/%d'];
        yield 'afficher le formulaire de modification' => ['GET', '/proprietaire/biens/%d/modifier'];
        yield 'envoyer une modification' => ['POST', '/proprietaire/biens/%d/modifier'];
        yield 'supprimer' => ['POST', '/proprietaire/biens/%d/supprimer'];
    }

    #[DataProvider('actionsOnAProperty')]
    public function testOwnerCannotTouchSomeoneElsesProperty(string $method, string $url): void
    {
        $someoneElsesProperty = PropertyFactory::new()->create(['name' => 'Pas à moi']);

        // Jeton CSRF valide, récupéré sur l'un de mes propres biens : seul le Voter doit pouvoir bloquer
        $parameters = 'POST' === $method ? ['_token' => $this->validDeleteToken()] : [];
        $this->client->request($method, \sprintf($url, $someoneElsesProperty->getId()), $parameters);

        self::assertResponseStatusCodeSame(403);
        self::assertNotNull($this->propertyRepository()->findOneBy(['name' => 'Pas à moi']));
    }

    private function validDeleteToken(): string
    {
        $myProperty = PropertyFactory::new()->create(['owner' => $this->owner]);
        $crawler = $this->client->request('GET', \sprintf('/proprietaire/biens/%d', $myProperty->getId()));

        return (string) $crawler->filter('input[name="_token"]')->attr('value');
    }

    private function findProperty(string $name): Property
    {
        $property = $this->propertyRepository()->findOneBy(['name' => $name]);
        self::assertNotNull($property, \sprintf('Le bien « %s » devrait exister.', $name));

        return $property;
    }

    private function propertyRepository(): PropertyRepository
    {
        return self::getContainer()->get(PropertyRepository::class);
    }
}
