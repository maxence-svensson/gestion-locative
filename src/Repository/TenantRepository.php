<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tenant>
 */
final class TenantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tenant::class);
    }

    /**
     * Le locataire dont l'invitation correspond à cette empreinte, si elle est encore valable.
     */
    public function findOneByValidInvitation(string $tokenHash, \DateTimeImmutable $now): ?Tenant
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.invitationTokenHash = :hash')
            ->andWhere('t.invitationExpiresAt > :now')
            ->andWhere('t.user IS NULL')
            ->setParameter('hash', $tokenHash)
            ->setParameter('now', $now, Types::DATETIME_IMMUTABLE)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
