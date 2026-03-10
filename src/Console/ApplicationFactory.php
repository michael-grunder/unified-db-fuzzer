<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Console;

use Mgrunder\Fuzz\Console\Command\WorkCommand;
use Mgrunder\Fuzz\Fuzz\Command\CommandRegistry;
use Mgrunder\Fuzz\Runtime\RelayClientFactory;
use Mgrunder\Fuzz\Runtime\RelayStatsProvider;
use Mgrunder\Fuzz\Runtime\WorkerOrchestrator;
use Symfony\Component\Console\Application;

final class ApplicationFactory
{
    public function create(): Application
    {
        $application = new Application('fuzz');
        $application->addCommands([
            new WorkCommand(
                new WorkerOrchestrator(
                    new RelayClientFactory(),
                    CommandRegistry::default(),
                    new RelayStatsProvider(),
                ),
            ),
        ]);
        $application->setDefaultCommand(WorkCommand::NAME, true);

        return $application;
    }
}
