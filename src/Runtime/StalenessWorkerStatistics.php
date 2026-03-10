<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime;

use Mgrunder\Fuzz\Fuzz\ObservedStaleness;

final class StalenessWorkerStatistics
{
    public readonly float $startedAt;
    public int $done = 0;
    public int $reads = 0;
    public int $writes = 0;
    public int $deletes = 0;
    public int $staleReads = 0;
    public int $regressions = 0;
    public int $deleteMismatches = 0;
    public int $missingAfterCreate = 0;
    public int $persistentStale = 0;
    public int $hardFailures = 0;
    public ?ObservedStaleness $worstObservation = null;

    /**
     * @var list<ObservedStaleness>
     */
    public array $topObservations = [];

    public function __construct(
        private readonly int $topN,
    ) {
        $this->startedAt = microtime(true);
    }

    public function recordDone(): void
    {
        $this->done++;
    }

    public function recordRead(): void
    {
        $this->reads++;
    }

    public function recordWrite(): void
    {
        $this->writes++;
    }

    public function recordDelete(): void
    {
        $this->deletes++;
    }

    public function observe(ObservedStaleness $observation, bool $hardFailure): void
    {
        if ($observation->stepsBehind !== null && $observation->stepsBehind > 0) {
            $this->staleReads++;
        }

        if ($observation->regression) {
            $this->regressions++;
        }

        if ($observation->classification === 'stale_exists_after_delete') {
            $this->deleteMismatches++;
        }

        if ($observation->classification === 'stale_missing_after_create') {
            $this->missingAfterCreate++;
        }

        if ($observation->classification === 'persistent_stale') {
            $this->persistentStale++;
        }

        if ($hardFailure) {
            $this->hardFailures++;
        }

        if ($this->worstObservation === null || self::compare($observation, $this->worstObservation) < 0) {
            $this->worstObservation = $observation;
        }

        $this->topObservations[] = $observation;
        usort($this->topObservations, self::compare(...));
        $this->topObservations = array_slice($this->topObservations, 0, $this->topN);
    }

    public function formatProgress(WorkOptions $options): string
    {
        $elapsed = microtime(true) - $this->startedAt;
        $rate = $elapsed > 0 ? ($this->done / $elapsed) : 0.0;

        return sprintf(
            'progress: %d/%s ops%s, %.0f ops/sec, reads=%d writes=%d deletes=%d stale=%d persistent=%d regressions=%d failures=%d worst=%s',
            $this->done,
            StatusFormatter::formatOps($options->ops),
            $options->ops > 0 ? sprintf(' (%.1f%%)', ($this->done / $options->ops) * 100.0) : '',
            $rate,
            $this->reads,
            $this->writes,
            $this->deletes,
            $this->staleReads,
            $this->persistentStale,
            $this->regressions,
            $this->hardFailures,
            $this->formatWorst(),
        );
    }

    public function formatFinished(WorkOptions $options, bool $terminatedEarly): string
    {
        $elapsed = microtime(true) - $this->startedAt;
        $rate = $elapsed > 0 ? ($this->done / $elapsed) : 0.0;

        return sprintf(
            'worker %s: %d/%s ops in %.3fs (%.0f ops/sec), reads=%d writes=%d deletes=%d stale=%d persistent=%d regressions=%d failures=%d worst=%s',
            $terminatedEarly ? 'exited early' : 'finished',
            $this->done,
            StatusFormatter::formatOps($options->ops),
            $elapsed,
            $rate,
            $this->reads,
            $this->writes,
            $this->deletes,
            $this->staleReads,
            $this->persistentStale,
            $this->regressions,
            $this->hardFailures,
            $this->formatWorst(),
        );
    }

    private function formatWorst(): string
    {
        if ($this->worstObservation === null) {
            return 'none';
        }

        return sprintf(
            '%s steps=%s class=%s',
            $this->worstObservation->key,
            $this->worstObservation->stepsBehind === null ? 'n/a' : (string) $this->worstObservation->stepsBehind,
            $this->worstObservation->classification,
        );
    }

    private static function compare(ObservedStaleness $left, ObservedStaleness $right): int
    {
        $leftRegression = $left->regression ? 1 : 0;
        $rightRegression = $right->regression ? 1 : 0;
        if ($leftRegression !== $rightRegression) {
            return $rightRegression <=> $leftRegression;
        }

        $leftSteps = $left->stepsBehind ?? -1;
        $rightSteps = $right->stepsBehind ?? -1;
        if ($leftSteps !== $rightSteps) {
            return $rightSteps <=> $leftSteps;
        }

        return ($right->ageNs ?? -1) <=> ($left->ageNs ?? -1);
    }

    public function snapshot(int $workerIndex, WorkOptions $options, string $state): WorkerStatusSnapshot
    {
        $elapsed = microtime(true) - $this->startedAt;
        $rate = $elapsed > 0 ? ($this->done / $elapsed) : 0.0;

        return new WorkerStatusSnapshot(
            workerIndex: $workerIndex,
            pid: getmypid() ?: 0,
            mode: 'staleness',
            state: $state,
            done: $this->done,
            targetOps: $options->ops > 0 ? $options->ops : null,
            startedAt: $this->startedAt,
            updatedAt: microtime(true),
            opsPerSecond: $rate,
            metrics: [
                'reads' => $this->reads,
                'writes' => $this->writes,
                'deletes' => $this->deletes,
                'stale_reads' => $this->staleReads,
                'regressions' => $this->regressions,
                'delete_mismatches' => $this->deleteMismatches,
                'missing_after_create' => $this->missingAfterCreate,
                'persistent_stale' => $this->persistentStale,
                'hard_failures' => $this->hardFailures,
            ],
            topKeys: array_map(
                fn (ObservedStaleness $observation): array => [
                    'key' => $observation->key,
                    'classification' => $observation->classification,
                    'steps_behind' => $observation->stepsBehind,
                    'age' => StatusFormatter::formatAgeValue($observation->ageNs, $options->ageUnit),
                    'regression' => $observation->regression,
                ],
                $this->topObservations,
            ),
        );
    }
}
