<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RentDueRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Échéance mensuelle d'un bail : ce que le locataire doit payer pour un mois donné. Montants en centimes.
 *
 * Les montants sont copiés au moment de la création : une révision de loyer ultérieure
 * ne modifie pas les échéances déjà émises.
 */
#[ORM\Entity(repositoryClass: RentDueRepository::class)]
// Garantie ultime contre les doublons : jamais deux échéances pour le même bail et le même mois
#[ORM\UniqueConstraint(name: 'uniq_rent_due_lease_period', fields: ['lease', 'period'])]
final class RentDue
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Lease $lease;

    /**
     * Mois concerné, toujours au 1er du mois.
     */
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $period;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $dueDate;

    #[ORM\Column]
    private int $rent;

    #[ORM\Column]
    private int $charges;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Lease $lease, \DateTimeImmutable $period, \DateTimeImmutable $dueDate, int $rent, int $charges)
    {
        if ('1' !== $period->format('j')) {
            throw new \InvalidArgumentException('Une échéance porte sur un mois entier : sa période commence le 1er du mois.');
        }

        $this->lease = $lease;
        $this->period = $period;
        $this->dueDate = $dueDate;
        $this->rent = $rent;
        $this->charges = $charges;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLease(): Lease
    {
        return $this->lease;
    }

    public function getPeriod(): \DateTimeImmutable
    {
        return $this->period;
    }

    public function getDueDate(): \DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function getRent(): int
    {
        return $this->rent;
    }

    public function getCharges(): int
    {
        return $this->charges;
    }

    public function getTotal(): int
    {
        return $this->rent + $this->charges;
    }

    /**
     * Premier mois d'un bail commencé en cours de mois : le montant ne couvre que les jours occupés.
     */
    public function isProrated(): bool
    {
        $startDate = $this->lease->getStartDate();

        return Lease::firstDayOfMonth($startDate) == $this->period && '1' !== $startDate->format('j');
    }

    /**
     * En retard si la date d'échéance est passée (les paiements seront pris en compte à l'étape suivante).
     */
    public function isOverdue(\DateTimeImmutable $today): bool
    {
        return $this->dueDate < $today->setTime(0, 0);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
