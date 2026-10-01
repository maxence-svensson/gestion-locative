<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Property;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Property>
 */
final class PropertyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Property::class);
    }

    /**
     * Toujours filtrer par propriétaire dans la requête : le Voter protège l'accès à un bien,
     * mais c'est la requête qui garantit qu'une liste ne contient que les biens de l'utilisateur.
     *
     * @return list<Property>
     */
    public function findByOwner(User $owner): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countByOwner(User $owner): int
    {
        return $this->count(['owner' => $owner]);
    }
}
