<?php

declare(strict_types=1);

namespace App\Entity;

use App\Lease\DepositLimit;
use App\Repository\LeaseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Bail d'habitation. Les montants sont en centimes.
 */
#[ORM\Entity(repositoryClass: LeaseRepository::class)]
final class Lease
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Property $property;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $startDate;

    /**
     * Loyer mensuel hors charges.
     */
    #[ORM\Column]
    private int $rent;

    /**
     * Provision mensuelle sur charges, régularisée chaque année.
     */
    #[ORM\Column]
    private int $charges;

    #[ORM\Column]
    private int $deposit;

    /**
     * Jour du mois où le loyer est dû (1 à 28, pour exister tous les mois).
     */
    #[ORM\Column(type: Types::SMALLINT)]
    private int $paymentDay;

    /**
     * Location meublée ou vide. Copié depuis le bien à la signature : le type de bail ne change plus ensuite.
     */
    #[ORM\Column]
    private bool $furnished;

    /**
     * Indice IRL de référence indiqué dans le bail (par exemple « 2e trimestre 2026 »),
     * qui servira de base à la révision annuelle du loyer.
     */
    #[ORM\Column(type: Types::SMALLINT)]
    private int $irlReferenceQuarter;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $irlReferenceYear;

    /**
     * Premier mois dont l'application suit le loyer (toujours le 1er du mois).
     * Pour un bail déjà en cours quand il est saisi, on ne crée pas des années d'échéances passées.
     */
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $rentTrackedFrom;

    /**
     * @var Collection<int, Tenant>
     */
    #[ORM\OneToMany(targetEntity: Tenant::class, mappedBy: 'lease', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $tenants;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        Property $property,
        \DateTimeImmutable $startDate,
        int $rent,
        int $charges,
        int $deposit,
        int $paymentDay,
        int $irlReferenceQuarter,
        int $irlReferenceYear,
        \DateTimeImmutable $rentTrackedFrom,
    ) {
        $rentTrackedFrom = self::firstDayOfMonth($rentTrackedFrom);

        if ($rent <= 0) {
            throw new \InvalidArgumentException('Le loyer doit être supérieur à 0.');
        }
        if ($charges < 0) {
            throw new \InvalidArgumentException('La provision sur charges ne peut pas être négative.');
        }
        if (!DepositLimit::allows($deposit, $rent, $property->isFurnished())) {
            throw new \InvalidArgumentException('Le dépôt de garantie dépasse le plafond légal.');
        }
        if ($paymentDay < 1 || $paymentDay > 28) {
            throw new \InvalidArgumentException('Le jour de paiement doit être compris entre 1 et 28.');
        }
        if ($irlReferenceQuarter < 1 || $irlReferenceQuarter > 4) {
            throw new \InvalidArgumentException('Le trimestre IRL de référence doit être compris entre 1 et 4.');
        }
        if ($rentTrackedFrom < self::firstDayOfMonth($startDate)) {
            throw new \InvalidArgumentException('Le suivi des loyers ne peut pas commencer avant le début du bail.');
        }

        $this->property = $property;
        $this->startDate = $startDate;
        $this->rent = $rent;
        $this->charges = $charges;
        $this->deposit = $deposit;
        $this->paymentDay = $paymentDay;
        $this->furnished = $property->isFurnished();
        $this->irlReferenceQuarter = $irlReferenceQuarter;
        $this->irlReferenceYear = $irlReferenceYear;
        $this->rentTrackedFrom = $rentTrackedFrom;
        $this->tenants = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * Les locataires sont toujours ajoutés par le bail, qui reste maître de sa liste.
     */
    public function addTenant(string $firstName, string $lastName, string $email): Tenant
    {
        $tenant = new Tenant($this, $firstName, $lastName, $email);
        $this->tenants->add($tenant);

        return $tenant;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProperty(): Property
    {
        return $this->property;
    }

    public function getStartDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    public function getRent(): int
    {
        return $this->rent;
    }

    public function getCharges(): int
    {
        return $this->charges;
    }

    /**
     * Montant appelé chaque mois : loyer hors charges + provision sur charges.
     */
    public function getMonthlyTotal(): int
    {
        return $this->rent + $this->charges;
    }

    public function getDeposit(): int
    {
        return $this->deposit;
    }

    public function getPaymentDay(): int
    {
        return $this->paymentDay;
    }

    public function isFurnished(): bool
    {
        return $this->furnished;
    }

    public function getIrlReferenceQuarter(): int
    {
        return $this->irlReferenceQuarter;
    }

    public function getIrlReferenceYear(): int
    {
        return $this->irlReferenceYear;
    }

    public function getRentTrackedFrom(): \DateTimeImmutable
    {
        return $this->rentTrackedFrom;
    }

    public static function firstDayOfMonth(\DateTimeInterface $date): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($date)->modify('first day of this month')->setTime(0, 0);
    }

    /**
     * @return list<Tenant>
     */
    public function getTenants(): array
    {
        return array_values($this->tenants->toArray());
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
