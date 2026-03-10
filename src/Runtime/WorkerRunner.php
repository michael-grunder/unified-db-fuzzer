<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime;

use Mgrunder\Fuzz\Fuzz\Command\CommandRegistry;
use Mgrunder\Fuzz\Fuzz\Command\RedisCommand;
use Mgrunder\Fuzz\Fuzz\FuzzContext;
use Throwable;

final class WorkerRunner
{
    public function __construct(
        private readonly ClientFactory $clientFactory,
        private readonly CommandRegistry $commandRegistry,
        private readonly StatsProvider $statsProvider,
    ) {
    }

    public function connect(WorkOptions $options): RedisClient
    {
        return $this->clientFactory->connect($options->host, $options->port);
    }

    public function statsSummary(): string
    {
        return $this->statsProvider->summary();
    }

    public function run(WorkOptions $options, int $workerIndex, WorkerLogger $logger): RunSummary
    {
        $commands = $this->commandRegistry->filterByTypes($options->commandTypes);
        $seed = $options->seed ?? random_int(1, PHP_INT_MAX);
        $context = new FuzzContext($options->keys, $options->members, $seed + $workerIndex);
        $statistics = new WorkerStatistics();
        $client = $this->connect($options);
        $lastReport = microtime(true);

        $logger->log(
            sprintf(
                'worker started: worker=%d seed=%d ops=%s keys=%d mems=%d report_interval=%.1fs age_unit=%s %s %s',
                $workerIndex,
                $context->seed,
                StatusFormatter::formatOps($options->ops),
                $options->keys,
                $options->members,
                $options->reportInterval,
                $options->ageUnit->value,
                $options->commandTypes === [] ? 'cmd_types=all' : 'cmd_types=' . implode(',', array_map(
                    static fn ($type): string => $type->value,
                    $options->commandTypes,
                )),
                $this->statsSummary(),
            ),
        );

        $terminatedEarly = false;

        for ($i = 0; $options->ops < 0 || $i < $options->ops; $i++) {
            try {
                $command = $this->pickCommand($commands, $context);
                $operation = $command->createOperation($context);
                $result = $client->execute($operation);
                $statistics->recordCompleted();

                $observation = $command->observeAge($operation, $result);
                if ($observation !== null) {
                    $statistics->observeAge($observation);
                }
            } catch (Throwable $throwable) {
                $statistics->recordException($throwable);
                $logger->log(
                    sprintf(
                        'command exception after %d/%s ops: %s; reconnecting',
                        $statistics->done,
                        StatusFormatter::formatOps($options->ops),
                        $statistics->lastException,
                    ),
                );

                try {
                    $client = $this->connect($options);
                    $logger->log('reconnect succeeded');
                } catch (Throwable $reconnectThrowable) {
                    $statistics->recordException($reconnectThrowable);
                    $statistics->recordReconnectFailure();
                    $terminatedEarly = true;

                    $logger->log(
                        sprintf(
                            'worker exiting early after reconnect failure: %s',
                            $statistics->lastException ?? WorkerStatistics::summarizeThrowable($reconnectThrowable),
                        ),
                    );

                    break;
                }
            }

            $now = microtime(true);
            if (($now - $lastReport) < $options->reportInterval) {
                continue;
            }

            $logger->log($statistics->formatProgress($options, $this->statsSummary()));
            $lastReport = $now;
        }

        $logger->log($statistics->formatFinished($options, $terminatedEarly, $this->statsSummary()));

        return new RunSummary($terminatedEarly, $statistics);
    }

    /**
     * @param list<RedisCommand> $commands
     */
    private function pickCommand(array $commands, FuzzContext $context): RedisCommand
    {
        return $commands[$context->randomIndex(count($commands) - 1)];
    }
}
