<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Console;

use Mgrunder\Fuzz\Console\Command\KillClientsCommand;
use Mgrunder\Fuzz\Console\Command\WorkCommand;
use Mgrunder\Fuzz\Fuzz\Command\CommandRegistry;
use Mgrunder\Fuzz\Runtime\ClientKiller;
use Mgrunder\Fuzz\Runtime\PhpRedisClientFactory;
use Mgrunder\Fuzz\Runtime\RelayClientFactory;
use Mgrunder\Fuzz\Runtime\RelayAdminClientFactory;
use Mgrunder\Fuzz\Runtime\RelayStatsProvider;
use Mgrunder\Fuzz\Runtime\WorkerOrchestrator;
use Symfony\Component\Console\Application;

final class ApplicationFactory
{
    public function create(string $defaultCommand = WorkCommand::NAME): Application
    {
        $application = new Application('fuzz');
        $application->addCommands([
            new KillClientsCommand(
                new ClientKiller(
                    new RelayAdminClientFactory(),
                ),
            ),
            new WorkCommand(
                new WorkerOrchestrator(
                    new RelayClientFactory(),
                    new PhpRedisClientFactory(),
                    CommandRegistry::default(),
                    new RelayStatsProvider(),
                ),
            ),
        ]);
        $application->setDefaultCommand($defaultCommand, true);

        return $application;
    }
}
