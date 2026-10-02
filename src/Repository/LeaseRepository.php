<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Lease;
use App\Entity\Property;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Lease>
 */
final class LeaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lease::class);
    }

    /**
     * Le bail d'un bien, avec ses locataires chargés dans la même requête.
     */
    public function findOneByProperty(Property $property): ?Lease
    {
        return $this->createQueryBuilder('l')
            ->addSelect('t')
            ->leftJoin('l.tenants', 't')
            ->andWhere('l.property = :property')
            ->setParameter('property', $property)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Identifiants des biens loués d'un propriétaire, en une seule requête :
     * la liste des biens n'interroge pas la base une fois par bien pour savoir s'il est loué.
     *
     * @return list<int>
     */
    public function findLeasedPropertyIds(User $owner): array
    {
        return array_values(array_map(intval(...), $this->createQueryBuilder('l')
            ->select('IDENTITY(l.property)')
            ->join('l.property', 'p')
            ->andWhere('p.owner = :owner')
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getSingleColumnResult()));
    }

    /**
     * Les baux d'un locataire (plusieurs s'il loue plusieurs logements), avec le bien et le propriétaire.
     *
     * @return list<Lease>
     */
    public function findForTenantAccount(User $user): array
    {
        return $this->createQueryBuilder('l')
            ->addSelect('p', 'o')
            ->join('l.property', 'p')
            ->join('p.owner', 'o')
            ->join('l.tenants', 'me')
            ->andWhere('me.user = :user')
            ->setParameter('user', $user)
            ->orderBy('l.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
