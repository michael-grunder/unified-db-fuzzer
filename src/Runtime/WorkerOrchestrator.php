<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime;

use Mgrunder\Fuzz\Fuzz\Command\CommandRegistry;
use RuntimeException;

final class WorkerOrchestrator implements WorkApplication
{
    private readonly WorkerRunner $workerRunner;
    private readonly StalenessWorkerRunner $stalenessWorkerRunner;

    public function __construct(
        ClientFactory $clientFactory,
        ClientFactory $truthClientFactory,
        CommandRegistry $commandRegistry,
        StatsProvider $statsProvider,
    ) {
        $this->workerRunner = new WorkerRunner($clientFactory, $commandRegistry, $statsProvider);
        $this->stalenessWorkerRunner = new StalenessWorkerRunner($clientFactory, $truthClientFactory);
    }

    public function run(WorkOptions $options, WorkerLogger $logger): int
    {
        $startedAt = microtime(true);

        if ($options->flush) {
            $client = $this->workerRunner->connect($options);
            $client->flushDatabase();
        }

        if ($options->staleness) {
            return $this->runStalenessMode($options, $logger, $startedAt);
        }

        if ($options->workers === 0) {
            if (!$options->afl) {
                $logger->log(
                    sprintf(
                        'running in non-forking mode: ops=%s keys=%d mems=%d interval=%.1fs age_unit=%s keyspace=%s cmd_types=%s',
                        StatusFormatter::formatOps($options->ops),
                        $options->keys,
                        $options->members,
                        $options->reportInterval,
                        $options->ageUnit->value,
                        $options->workerKeyspace ? 'worker-scoped' : 'shared',
                        $this->formatCommandTypes($options),
                    ),
                );
            }

            $summary = $this->workerRunner->run($options, 0, $logger);
            $this->renderStatusPage($logger, $options, 1, $startedAt, true);

            return $summary->terminatedEarly ? 1 : 0;
        }

        if (!function_exists('pcntl_fork') || !function_exists('pcntl_wait')) {
            throw new RuntimeException('pcntl is required when --workers is greater than zero.');
        }

        if (!$options->afl) {
            $logger->log(
                sprintf(
                    'spawning %d workers: ops=%s keys=%d mems=%d interval=%.1fs age_unit=%s keyspace=%s cmd_types=%s',
                    $options->workers,
                    StatusFormatter::formatOps($options->ops),
                    $options->keys,
                    $options->members,
                    $options->reportInterval,
                    $options->ageUnit->value,
                    $options->workerKeyspace ? 'worker-scoped' : 'shared',
                    $this->formatCommandTypes($options),
                ),
            );
        }

        $pids = [];
        $start = microtime(true);

        for ($workerIndex = 0; $workerIndex < $options->workers; $workerIndex++) {
            $pid = pcntl_fork();

            if ($pid < 0) {
                $logger->log('fork failed');

                return 1;
            }

            if ($pid === 0) {
                $summary = $this->workerRunner->run($options, $workerIndex, $logger);
                exit($summary->terminatedEarly ? 1 : 0);
            }

            $pids[] = $pid;
            if (!$options->afl) {
                $logger->log(sprintf('spawned worker pid=%d', $pid));
            }
        }

        $remaining = array_fill_keys($pids, true);
        $exitCode = 0;

        while ($remaining !== []) {
            $pid = pcntl_waitpid(-1, $status, WNOHANG);
            if ($pid === 0) {
                $this->renderStatusPage($logger, $options, $options->workers, $startedAt);
                usleep(100_000);

                continue;
            }

            if ($pid < 0) {
                continue;
            }

            $workerExitCode = pcntl_wifexited($status)
                ? pcntl_wexitstatus($status)
                : 1;

            unset($remaining[$pid]);

            if ($workerExitCode !== 0) {
                $exitCode = 1;
            }

            if (!$options->afl) {
                $logger->log(
                    sprintf(
                        'worker pid=%d exited status=%d (%d/%d complete)',
                        $pid,
                        $workerExitCode,
                        $options->workers - count($remaining),
                        $options->workers,
                    ),
                );
            }

            $this->renderStatusPage($logger, $options, $options->workers, $startedAt);
        }

        if (!$options->afl) {
            $logger->log(sprintf('all workers completed in %.3fs', microtime(true) - $start));
            $logger->log('final ' . $this->workerRunner->statsSummary());
        }
        $this->renderStatusPage($logger, $options, $options->workers, $startedAt, true);

        return $exitCode;
    }

    private function formatCommandTypes(WorkOptions $options): string
    {
        if ($options->commandTypes === []) {
            return 'all';
        }

        return implode(',', array_map(
            static fn ($type): string => $type->value,
            $options->commandTypes,
        ));
    }

    private function runStalenessMode(WorkOptions $options, WorkerLogger $logger, float $startedAt): int
    {
        if ($options->workers === 0) {
            $summary = $this->stalenessWorkerRunner->run($options, 0, $logger);
            $this->renderStatusPage($logger, $options, 1, $startedAt, true);

            return $summary->terminatedEarly ? 1 : 0;
        }

        if (!function_exists('pcntl_fork') || !function_exists('pcntl_wait')) {
            throw new RuntimeException('pcntl is required when --workers is greater than zero.');
        }

        if (!$options->afl) {
            $logger->log(
                sprintf(
                    'spawning %d staleness workers: ops=%s keys=%d delays=%s',
                    $options->workers,
                    StatusFormatter::formatOps($options->ops),
                    $options->keys,
                    implode(',', $options->stalenessThresholds->delayBucketsUs),
                ),
            );
        }

        $pids = [];

        for ($workerIndex = 0; $workerIndex < $options->workers; $workerIndex++) {
            $pid = pcntl_fork();

            if ($pid < 0) {
                $logger->log('fork failed');

                return 1;
            }

            if ($pid === 0) {
                $summary = $this->stalenessWorkerRunner->run($options, $workerIndex, $logger);
                exit($summary->terminatedEarly ? 1 : 0);
            }

            $pids[] = $pid;
            if (!$options->afl) {
                $logger->log(sprintf('spawned staleness worker pid=%d', $pid));
            }
        }

        $remaining = array_fill_keys($pids, true);
        $exitCode = 0;

        while ($remaining !== []) {
            $pid = pcntl_waitpid(-1, $status, WNOHANG);
            if ($pid === 0) {
                $this->renderStatusPage($logger, $options, $options->workers, $startedAt);
                usleep(100_000);

                continue;
            }

            if ($pid < 0) {
                continue;
            }

            $workerExitCode = pcntl_wifexited($status)
                ? pcntl_wexitstatus($status)
                : 1;

            unset($remaining[$pid]);
            if ($workerExitCode !== 0) {
                $exitCode = 1;
            }

            if (!$options->afl) {
                $logger->log(
                    sprintf(
                        'staleness worker pid=%d exited status=%d (%d/%d complete)',
                        $pid,
                        $workerExitCode,
                        $options->workers - count($remaining),
                        $options->workers,
                    ),
                );
            }

            $this->renderStatusPage($logger, $options, $options->workers, $startedAt);
        }

        $this->renderStatusPage($logger, $options, $options->workers, $startedAt, true);

        return $exitCode;
    }

    private function renderStatusPage(
        WorkerLogger $logger,
        WorkOptions $options,
        int $expectedWorkers,
        float $startedAt,
        bool $final = false,
    ): void {
        if (!$logger instanceof StatusPageWorkerLogger) {
            return;
        }

        if ($final) {
            $logger->finish($options, $expectedWorkers, $startedAt);

            return;
        }

        $logger->render($options, $expectedWorkers, $startedAt);
    }
}
