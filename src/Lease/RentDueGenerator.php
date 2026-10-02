<?php

declare(strict_types=1);

namespace App\Lease;

use App\Entity\Lease;
use App\Repository\LeaseRepository;
use App\Repository\RentDueRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Lock\LockFactory;

/**
 * Crée les échéances manquantes des baux.
 *
 * Idempotent : relancer la génération ne crée jamais de doublon. Elle peut donc tourner chaque jour,
 * et rattraper toute seule un mois manqué (serveur arrêté, tâche en échec…).
 */
final class RentDueGenerator
{
    public function __construct(
        private readonly RentSchedule $schedule,
        private readonly RentDueRepository $dues,
        private readonly LeaseRepository $leases,
        private readonly EntityManagerInterface $entityManager,
        private readonly LockFactory $lockFactory,
    ) {
    }

    /**
     * @return int nombre d'échéances créées
     */
    public function generateFor(Lease $lease, \DateTimeImmutable $until): int
    {
        $existingPeriods = array_flip($this->dues->findPeriodsOf($lease));
        $created = 0;

        foreach ($this->schedule->periods($lease, $until) as $period) {
            if (isset($existingPeriods[$period->format('Y-m')])) {
                continue;
            }

            $this->entityManager->persist($this->schedule->dueFor($lease, $period));
            ++$created;
        }

        $this->entityManager->flush();

        return $created;
    }

    /**
     * @return int|null nombre d'échéances créées, ou null si une autre génération est déjà en cours
     */
    public function generateForAllLeases(\DateTimeImmutable $until): ?int
    {
        // Deux générations simultanées (tâche planifiée et commande lancée à la main) liraient les mêmes mois
        // manquants : la seconde s'arrête aussitôt. La contrainte d'unicité en base reste le dernier filet.
        $lock = $this->lockFactory->createLock('rent-due-generation');
        if (!$lock->acquire()) {
            return null;
        }

        try {
            $created = 0;
            foreach ($this->leases->findAll() as $lease) {
                $created += $this->generateFor($lease, $until);
            }

            return $created;
        } finally {
            $lock->release();
        }
    }
}
