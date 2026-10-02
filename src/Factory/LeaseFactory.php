<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Lease;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * Les locataires se passent dans l'attribut « tenants » : [[prénom, nom, e-mail], …].
 * Sans précision, le bail a un locataire.
 *
 * @extends PersistentObjectFactory<Lease>
 */
final class LeaseFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Lease::class;
    }

    protected function defaults(): array
    {
        $rent = self::faker()->numberBetween(400, 1200) * 100;
        $startDate = \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-3 years', '-1 month'))
            ->setTime(0, 0);

        return [
            'property' => PropertyFactory::new(),
            'startDate' => $startDate,
            'rent' => $rent,
            'charges' => self::faker()->numberBetween(2, 15) * 1000,
            // Un mois de loyer : toujours sous le plafond légal, que le logement soit vide ou meublé
            'deposit' => $rent,
            'paymentDay' => self::faker()->randomElement([1, 5, 10]),
            'irlReferenceQuarter' => self::faker()->numberBetween(1, 4),
            'irlReferenceYear' => (int) $startDate->format('Y'),
            'rentTrackedFrom' => $startDate,
        ];
    }

    protected function initialize(): static
    {
        return $this
            // « tenants » n'est pas un argument du constructeur : il est traité juste après la création
            ->instantiateWith(Instantiator::withConstructor()->allowExtra('tenants'))
            ->afterInstantiate(static function (Lease $lease, array $attributes): void {
                /** @var list<array{string, string, string}> $tenants */
                $tenants = $attributes['tenants'] ?? [
                    [self::faker()->firstName(), self::faker()->lastName(), self::faker()->unique()->safeEmail()],
                ];

                foreach ($tenants as [$firstName, $lastName, $email]) {
                    $lease->addTenant($firstName, $lastName, $email);
                }
            });
    }
}
