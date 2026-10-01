<?php

declare(strict_types=1);

namespace App\Tests\Controller\Owner;

use App\Factory\PropertyFactory;
use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DashboardControllerTest extends WebTestCase
{
    public function testNewOwnerIsInvitedToAddAProperty(): void
    {
        $client = static::createClient();
        $client->loginUser(UserFactory::new()->asOwner()->create());

        $client->request('GET', '/proprietaire');

        self::assertSelectorTextContains('.empty-state', 'Aucun bien pour l\'instant');
    }

    public function testDashboardCountsOnlyMyProperties(): void
    {
        $client = static::createClient();
        $owner = UserFactory::new()->asOwner()->create();
        PropertyFactory::new()->many(2)->create(['owner' => $owner]);
        PropertyFactory::new()->create();

        $client->loginUser($owner);
        $client->request('GET', '/proprietaire');

        self::assertSelectorTextContains('.stat-card__value', '2');
        self::assertSelectorTextContains('.stat-card__label', 'biens en gestion');
    }
}
