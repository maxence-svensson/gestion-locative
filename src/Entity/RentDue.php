<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PaymentMethod;
use App\Enum\ReceiptType;
use App\Repository\RentDueRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Échéance mensuelle d'un bail : ce que le locataire doit payer pour un mois donné. Montants en centimes.
 *
 * Les montants sont copiés au moment de la création : une révision de loyer ultérieure
 * ne modifie pas les échéances déjà émises.
 *
 * L'état (à payer, partiellement payée, payée) est géré par le workflow « rent_due » (config/packages/workflow.yaml).
 */
#[ORM\Entity(repositoryClass: RentDueRepository::class)]
// Garantie ultime contre les doublons : jamais deux échéances pour le même bail et le même mois
#[ORM\UniqueConstraint(name: 'uniq_rent_due_lease_period', fields: ['lease', 'period'])]
final class RentDue
{
    public const string STATUS_UNPAID = 'unpaid';
    public const string STATUS_PARTIALLY_PAID = 'partially_paid';
    public const string STATUS_PAID = 'paid';

    public const string TRANSITION_PAY_PARTIALLY = 'pay_partially';
    public const string TRANSITION_PAY_IN_FULL = 'pay_in_full';

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

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_UNPAID;

    /**
     * Total des paiements reçus, tenu à jour à chaque paiement : l'état et les retards
     * se lisent sans additionner les paiements de chaque échéance.
     */
    #[ORM\Column]
    private int $paidAmount = 0;

    /**
     * @var Collection<int, Payment>
     */
    #[ORM\OneToMany(targetEntity: Payment::class, mappedBy: 'rentDue', cascade: ['persist'])]
    #[ORM\OrderBy(['paidOn' => 'ASC', 'id' => 'ASC'])]
    private Collection $payments;

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
        $this->payments = new ArrayCollection();
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
     * Ajoute un paiement. À appeler uniquement via PaymentRecorder, qui verrouille l'échéance
     * et fait avancer son état dans le workflow.
     */
    public function addPayment(int $amount, \DateTimeImmutable $paidOn, PaymentMethod $method): Payment
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Le montant d\'un paiement doit être supérieur à 0.');
        }
        if ($amount > $this->getRemainingAmount()) {
            throw new \InvalidArgumentException('Le paiement dépasse le reste à payer.');
        }

        $payment = new Payment($this, $amount, $paidOn, $method);
        $this->payments->add($payment);
        $this->paidAmount += $amount;

        return $payment;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Utilisé par le workflow (marking store « method ») : ne pas appeler directement.
     *
     * @internal
     */
    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function isPaid(): bool
    {
        return self::STATUS_PAID === $this->status;
    }

    public function getPaidAmount(): int
    {
        return $this->paidAmount;
    }

    public function getRemainingAmount(): int
    {
        return $this->getTotal() - $this->paidAmount;
    }

    /**
     * @return list<Payment>
     */
    public function getPayments(): array
    {
        return array_values($this->payments->toArray());
    }

    /**
     * Quittance si le loyer est payé en entier, reçu s'il ne l'est qu'en partie, rien tant qu'aucun paiement n'est arrivé
     * (article 21 de la loi du 6 juillet 1989).
     */
    public function getReceiptType(): ?ReceiptType
    {
        return match ($this->status) {
            self::STATUS_PAID => ReceiptType::Quittance,
            self::STATUS_PARTIALLY_PAID => ReceiptType::PartialPaymentReceipt,
            default => null,
        };
    }

    /**
     * Premier jour couvert par l'échéance : le 1er du mois, ou le jour d'entrée si le bail commence en cours de mois.
     */
    public function getCoveredFrom(): \DateTimeImmutable
    {
        return max($this->period, $this->lease->getStartDate()->setTime(0, 0));
    }

    public function getCoveredUntil(): \DateTimeImmutable
    {
        return $this->period->modify('last day of this month');
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
     * En retard si la date d'échéance est passée sans que le loyer soit payé en entier.
     */
    public function isOverdue(\DateTimeImmutable $today): bool
    {
        return !$this->isPaid() && $this->dueDate < $today->setTime(0, 0);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
