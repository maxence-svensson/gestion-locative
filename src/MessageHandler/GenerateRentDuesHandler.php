<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Lease\RentDueGenerator;
use App\Message\GenerateRentDues;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GenerateRentDuesHandler
{
    public function __construct(
        private readonly RentDueGenerator $generator,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(GenerateRentDues $message): void
    {
        $created = $this->generator->generateForAllLeases($this->clock->now());

        if (null === $created) {
            $this->logger->info('Génération des échéances ignorée : une autre est déjà en cours.');

            return;
        }

        $this->logger->info('{count} échéance(s) de loyer créée(s).', ['count' => $created]);
    }
}
