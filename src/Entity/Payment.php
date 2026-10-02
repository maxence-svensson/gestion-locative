<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PaymentMethod;
use App\Repository\PaymentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Paiement reçu pour une échéance. Montant en centimes.
 */
#[ORM\Entity(repositoryClass: PaymentRepository::class)]
final class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'payments')]
    #[ORM\JoinColumn(nullable: false)]
    private RentDue $rentDue;

    #[ORM\Column]
    private int $amount;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $paidOn;

    #[ORM\Column(length: 20, enumType: PaymentMethod::class)]
    private PaymentMethod $method;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * À appeler uniquement depuis RentDue::addPayment().
     */
    public function __construct(RentDue $rentDue, int $amount, \DateTimeImmutable $paidOn, PaymentMethod $method)
    {
        $this->rentDue = $rentDue;
        $this->amount = $amount;
        $this->paidOn = $paidOn;
        $this->method = $method;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRentDue(): RentDue
    {
        return $this->rentDue;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getPaidOn(): \DateTimeImmutable
    {
        return $this->paidOn;
    }

    public function getMethod(): PaymentMethod
    {
        return $this->method;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
