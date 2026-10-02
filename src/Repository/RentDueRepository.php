<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Lease;
use App\Entity\RentDue;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RentDue>
 */
final class RentDueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RentDue::class);
    }

    /**
     * Mois déjà couverts par une échéance, au format « 2026-10 ».
     *
     * @return list<string>
     */
    public function findPeriodsOf(Lease $lease): array
    {
        $periods = $this->createQueryBuilder('d')
            ->select('d.period')
            ->andWhere('d.lease = :lease')
            ->setParameter('lease', $lease)
            ->getQuery()
            ->getSingleColumnResult();

        return array_values(array_map(
            static fn (\DateTimeInterface|string $period): string => \is_string($period)
                ? substr($period, 0, 7)
                : $period->format('Y-m'),
            $periods,
        ));
    }

    /**
     * Les dernières échéances d'un bail, de la plus récente à la plus ancienne.
     *
     * @return list<RentDue>
     */
    public function findLatestOf(Lease $lease, int $limit = 12): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.lease = :lease')
            ->setParameter('lease', $lease)
            ->orderBy('d.period', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Total appelé pour un mois donné sur l'ensemble des biens d'un propriétaire.
     */
    public function sumForOwnerAndPeriod(User $owner, \DateTimeImmutable $period): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COALESCE(SUM(d.rent + d.charges), 0)')
            ->join('d.lease', 'l')
            ->join('l.property', 'p')
            ->andWhere('p.owner = :owner')
            ->andWhere('d.period = :period')
            ->setParameter('owner', $owner)
            ->setParameter('period', $period, Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Échéances dont la date est passée sans que le loyer soit payé en entier (paiements partiels compris).
     */
    public function countOverdueForOwner(User $owner, \DateTimeImmutable $today): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->join('d.lease', 'l')
            ->join('l.property', 'p')
            ->andWhere('p.owner = :owner')
            ->andWhere('d.dueDate < :today')
            ->andWhere('d.status != :paid')
            ->setParameter('owner', $owner)
            ->setParameter('today', $today->setTime(0, 0), Types::DATE_IMMUTABLE)
            ->setParameter('paid', RentDue::STATUS_PAID)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
