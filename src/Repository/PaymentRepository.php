<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Payment;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 */
final class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    /**
     * Total encaissé par un propriétaire entre deux dates (incluses), tous biens confondus.
     */
    public function sumReceivedByOwner(User $owner, \DateTimeImmutable $from, \DateTimeImmutable $until): int
    {
        return (int) $this->createQueryBuilder('pay')
            ->select('COALESCE(SUM(pay.amount), 0)')
            ->join('pay.rentDue', 'd')
            ->join('d.lease', 'l')
            ->join('l.property', 'p')
            ->andWhere('p.owner = :owner')
            ->andWhere('pay.paidOn BETWEEN :from AND :until')
            ->setParameter('owner', $owner)
            ->setParameter('from', $from, Types::DATE_IMMUTABLE)
            ->setParameter('until', $until, Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
