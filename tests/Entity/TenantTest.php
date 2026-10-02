<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Lease;
use App\Entity\Property;
use App\Entity\Tenant;
use App\Entity\User;
use App\Enum\EnergyClass;
use App\Enum\HousingType;
use PHPUnit\Framework\TestCase;

final class TenantTest extends TestCase
{
    public function testInvitationIsPendingUntilItExpires(): void
    {
        $tenant = $this->tenant();
        $tenant->invite('hash', new \DateTimeImmutable('2026-10-01 10:00'), new \DateTimeImmutable('2026-10-08 10:00'));

        self::assertTrue($tenant->isInvitationPending(new \DateTimeImmutable('2026-10-08 09:59')));
        self::assertFalse($tenant->hasInvitationExpired(new \DateTimeImmutable('2026-10-08 09:59')));

        self::assertFalse($tenant->isInvitationPending(new \DateTimeImmutable('2026-10-08 10:00')));
        self::assertTrue($tenant->hasInvitationExpired(new \DateTimeImmutable('2026-10-08 10:00')));
    }

    public function testAttachingTheAccountEndsTheInvitation(): void
    {
        $tenant = $this->tenant();
        $tenant->invite('hash', new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable('2026-10-08'));

        $tenant->attachUser(new User('Karim.Benali@Exemple.fr', 'Karim', 'Benali'));

        self::assertTrue($tenant->hasAccount());
        self::assertFalse($tenant->isInvitationPending(new \DateTimeImmutable('2026-10-02')));
        self::assertFalse($tenant->hasInvitationExpired(new \DateTimeImmutable('2026-10-09')));
    }

    public function testOnlyTheAccountWithTheTenantEmailCanBeAttached(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->tenant()->attachUser(new User('quelquun@exemple.fr', 'Karim', 'Benali'));
    }

    public function testATenantWithAnAccountCannotBeInvitedAgain(): void
    {
        $tenant = $this->tenant();
        $tenant->attachUser(new User('karim.benali@exemple.fr', 'Karim', 'Benali'));

        $this->expectException(\LogicException::class);

        $tenant->invite('hash', new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable('2026-10-08'));
    }

    private function tenant(): Tenant
    {
        $property = new Property(
            new User('proprietaire@exemple.fr', 'Maxence', 'Svensson'),
            'T2', '12 rue d\'Austerlitz', '69004', 'Lyon', HousingType::Apartment, false, 48.5, EnergyClass::C,
        );
        $lease = new Lease($property, new \DateTimeImmutable('2025-09-01'), 72000, 6000, 72000, 5, 2, 2025, new \DateTimeImmutable('2025-09-01'));

        return $lease->addTenant('Karim', 'Benali', 'karim.benali@exemple.fr');
    }
}
