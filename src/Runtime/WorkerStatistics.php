<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime;

use Mgrunder\Fuzz\Fuzz\ObservedAge;
use Throwable;

final class WorkerStatistics
{
    public readonly float $startedAt;
    public int $done = 0;
    public int $exceptions = 0;
    public int $reconnectFailures = 0;
    public ?string $lastException = null;
    public ?string $oldestKey = null;
    public ?int $oldestAgeNs = null;

    public function __construct()
    {
        $this->startedAt = microtime(true);
    }

    public function recordCompleted(): void
    {
        $this->done++;
    }

    public function recordException(Throwable $throwable): void
    {
        $this->exceptions++;
        $this->lastException = self::summarizeThrowable($throwable);
    }

    public function recordReconnectFailure(): void
    {
        $this->reconnectFailures++;
        if ($this->lastException !== null) {
            $this->lastException = 'reconnect failed: ' . $this->lastException;
        }
    }

    public function observeAge(ObservedAge $observedAge): void
    {
        if ($this->oldestAgeNs !== null && $observedAge->ageNs <= $this->oldestAgeNs) {
            return;
        }

        $this->oldestAgeNs = $observedAge->ageNs;
        $this->oldestKey = $observedAge->key;
    }

    public function formatProgress(WorkOptions $options, string $statsSummary): string
    {
        $elapsed = microtime(true) - $this->startedAt;
        $rate = $elapsed > 0 ? ($this->done / $elapsed) : 0.0;

        return sprintf(
            'progress: %d/%s ops%s, %.0f ops/sec, exceptions=%d reconnect_failures=%d%s, %s, %s',
            $this->done,
            StatusFormatter::formatOps($options->ops),
            $options->ops > 0 ? sprintf(' (%.1f%%)', ($this->done / $options->ops) * 100.0) : '',
            $rate,
            $this->exceptions,
            $this->reconnectFailures,
            $this->lastException !== null ? sprintf(' last_exception="%s"', $this->lastException) : '',
            StatusFormatter::formatAge($this, $options->ageUnit),
            $statsSummary,
        );
    }

    public function formatFinished(WorkOptions $options, bool $terminatedEarly, string $statsSummary): string
    {
        $elapsed = microtime(true) - $this->startedAt;
        $rate = $elapsed > 0 ? ($this->done / $elapsed) : 0.0;

        return sprintf(
            'worker %s: %d/%s ops in %.3fs (%.0f ops/sec), exceptions=%d reconnect_failures=%d%s, %s, %s',
            $terminatedEarly ? 'exited early' : 'finished',
            $this->done,
            StatusFormatter::formatOps($options->ops),
            $elapsed,
            $rate,
            $this->exceptions,
            $this->reconnectFailures,
            $this->lastException !== null ? sprintf(' last_exception="%s"', $this->lastException) : '',
            StatusFormatter::formatAge($this, $options->ageUnit),
            $statsSummary,
        );
    }

    public static function summarizeThrowable(Throwable $throwable): string
    {
        return sprintf('%s: %s', $throwable::class, $throwable->getMessage());
    }
}
