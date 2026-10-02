<?php

declare(strict_types=1);

namespace App;

use App\Message\GenerateRentDues;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Tâches planifiées, exécutées par le worker (service « worker » de compose.yaml).
 */
#[AsSchedule]
final class Schedule implements ScheduleProviderInterface
{
    public function __construct(
        private readonly CacheInterface $cache,
    ) {
    }

    public function getSchedule(): SymfonySchedule
    {
        return (new SymfonySchedule())
            // Si le worker était arrêté à l'heure prévue, la tâche est rattrapée à son redémarrage (une seule fois)
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true)

            // Tous les jours plutôt qu'une fois par mois : la génération est idempotente,
            // et un mois manqué (serveur arrêté le 1er) est rattrapé dès le lendemain.
            ->add(RecurringMessage::every('1 day', new GenerateRentDues(), from: '06:00'));
    }
}
