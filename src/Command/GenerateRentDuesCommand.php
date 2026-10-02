<?php

declare(strict_types=1);

namespace App\Command;

use App\Lease\RentDueGenerator;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:rent-dues:generate',
    description: 'Crée les échéances de loyer manquantes jusqu\'au mois en cours (peut être relancée sans risque)',
)]
final class GenerateRentDuesCommand
{
    public function __construct(
        private readonly RentDueGenerator $generator,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $created = $this->generator->generateForAllLeases($this->clock->now());

        if (null === $created) {
            $io->warning('Une génération est déjà en cours.');

            return Command::FAILURE;
        }

        $io->success(\sprintf('%d échéance(s) créée(s).', $created));

        return Command::SUCCESS;
    }
}
