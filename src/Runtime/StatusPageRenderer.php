<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime;

use Symfony\Component\Console\Terminal;

use function array_filter;
use function array_map;
use function array_merge;
use function array_slice;
use function array_sum;
use function count;
use function implode;
use function is_int;
use function is_string;
use function max;
use function sprintf;
use function strcmp;
use function str_pad;
use function strlen;
use function substr;
use function usort;

final class StatusPageRenderer
{
    /**
     * @param list<WorkerStatusSnapshot> $snapshots
     */
    public function render(WorkOptions $options, array $snapshots, int $expectedWorkers, float $startedAt): string
    {
        $mode = $options->staleness ? 'staleness' : 'standard';
        $elapsed = microtime(true) - $startedAt;
        $running = count(array_filter($snapshots, static fn (WorkerStatusSnapshot $snapshot): bool => $snapshot->state === 'running'));
        $finished = count(array_filter($snapshots, static fn (WorkerStatusSnapshot $snapshot): bool => $snapshot->state === 'finished'));
        $totalDone = array_sum(array_map(static fn (WorkerStatusSnapshot $snapshot): int => $snapshot->done, $snapshots));
        $totalRate = array_sum(array_map(static fn (WorkerStatusSnapshot $snapshot): float => $snapshot->opsPerSecond, $snapshots));

        $lines = [
            'fuzz status',
            sprintf(
                'mode=%s workers=%d seen=%d running=%d finished=%d elapsed=%s ops=%d/%s rate=%.0f/s',
                $mode,
                $expectedWorkers,
                count($snapshots),
                $running,
                $finished,
                StatusFormatter::formatElapsed($elapsed),
                $totalDone,
                StatusFormatter::formatOps($options->ops > 0 ? $options->ops * max(1, $expectedWorkers) : $options->ops),
                $totalRate,
            ),
            sprintf(
                'target=%s:%d keys=%d mems=%d interval=%.1fs seed=%s',
                $options->host,
                $options->port,
                $options->keys,
                $options->members,
                $options->reportInterval,
                $options->seed === null ? 'random' : (string) $options->seed,
            ),
            '',
            'workers:',
        ];

        foreach ($this->workerLines($snapshots, $options) as $line) {
            $lines[] = $line;
        }

        if ($options->staleness) {
            $lines[] = '';
            $lines[] = 'top stale keys (still stale):';
            foreach ($this->topKeyLines($snapshots, current: true) as $line) {
                $lines[] = $line;
            }
            $lines[] = '';
            $lines[] = 'worst stale keys seen:';
            foreach ($this->topKeyLines($snapshots, current: false) as $line) {
                $lines[] = $line;
            }
        }

        $terminal = new Terminal();
        $height = max(24, $terminal->getHeight());
        if (count($lines) > $height - 1) {
            $lines = array_slice($lines, 0, $height - 1);
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<WorkerStatusSnapshot> $snapshots
     * @return list<string>
     */
    private function workerLines(array $snapshots, WorkOptions $options): array
    {
        usort($snapshots, static fn (WorkerStatusSnapshot $left, WorkerStatusSnapshot $right): int => $left->workerIndex <=> $right->workerIndex);

        if ($snapshots === []) {
            return ['  waiting for worker snapshots...'];
        }

        return array_map(
            function (WorkerStatusSnapshot $snapshot) use ($options): string {
                $target = StatusFormatter::formatOps($snapshot->targetOps ?? -1);
                $stale = microtime(true) - $snapshot->updatedAt;
                $label = sprintf(
                    '  w%02d %-8s pid=%d done=%d/%s rate=%4.0f/s stale=%s',
                    $snapshot->workerIndex,
                    $snapshot->state,
                    $snapshot->pid,
                    $snapshot->done,
                    $target,
                    $snapshot->opsPerSecond,
                    StatusFormatter::formatElapsed($stale),
                );

                if ($options->staleness) {
                    $worst = $snapshot->topKeys[0] ?? null;

                    return sprintf(
                        '%s reads=%d writes=%d deletes=%d stale_reads=%d persistent=%d regressions=%d failures=%d worst=%s',
                        $label,
                        $this->metricInt($snapshot, 'reads'),
                        $this->metricInt($snapshot, 'writes'),
                        $this->metricInt($snapshot, 'deletes'),
                        $this->metricInt($snapshot, 'stale_reads'),
                        $this->metricInt($snapshot, 'persistent_stale'),
                        $this->metricInt($snapshot, 'regressions'),
                        $this->metricInt($snapshot, 'hard_failures'),
                        $worst === null
                            ? 'none'
                            : sprintf('%s %s %s', $this->clip((string) $worst['key'], 18), $worst['steps_behind'] ?? 'n/a', $worst['classification']),
                    );
                }

                return sprintf(
                    '%s exceptions=%d reconnects=%d oldest=%s cache=%s',
                    $label,
                    $this->metricInt($snapshot, 'exceptions'),
                    $this->metricInt($snapshot, 'reconnect_failures'),
                    $this->metricString($snapshot, 'oldest'),
                    $this->metricString($snapshot, 'stats_summary'),
                );
            },
            $snapshots,
        );
    }

    /**
     * @param list<WorkerStatusSnapshot> $snapshots
     * @return list<string>
     */
    private function topKeyLines(array $snapshots, bool $current): array
    {
        $now = microtime(true);
        $entries = [];

        foreach ($snapshots as $snapshot) {
            $source = $current ? $snapshot->currentTopKeys : $snapshot->topKeys;
            foreach ($source as $entry) {
                $entries[] = array_merge($entry, ['worker_index' => $snapshot->workerIndex]);
            }
        }

        usort($entries, static function (array $left, array $right): int {
            $leftRegression = $left['regression'] ? 1 : 0;
            $rightRegression = $right['regression'] ? 1 : 0;
            if ($leftRegression !== $rightRegression) {
                return $rightRegression <=> $leftRegression;
            }

            $leftSteps = is_int($left['steps_behind'] ?? null) ? $left['steps_behind'] : -1;
            $rightSteps = is_int($right['steps_behind'] ?? null) ? $right['steps_behind'] : -1;
            if ($leftSteps !== $rightSteps) {
                return $rightSteps <=> $leftSteps;
            }

            $leftStreak = $left['consecutive_stale'];
            $rightStreak = $right['consecutive_stale'];
            if ($leftStreak !== $rightStreak) {
                return $rightStreak <=> $leftStreak;
            }

            return strcmp($left['key'], $right['key']);
        });

        if ($entries === []) {
            return ['  none yet'];
        }

        $lines = [];
        foreach (array_slice($entries, 0, 8) as $entry) {
            $lastSeenAt = $entry['last_seen_at'] ?? null;
            $lastSeen = $lastSeenAt !== null
                ? StatusFormatter::formatElapsed(max(0.0, $now - (float) $lastSeenAt))
                : 'n/a';

            $lines[] = sprintf(
                '  w%02d %-20s class=%-26s steps=%-4s streak=%-3d last=%-8s age=%s',
                (int) $entry['worker_index'],
                $this->clip($entry['key'], 20),
                $this->clip($entry['classification'], 26),
                is_int($entry['steps_behind'] ?? null) ? (string) $entry['steps_behind'] : 'n/a',
                (string) $entry['consecutive_stale'],
                $lastSeen,
                $entry['age'],
            );
        }

        return $lines;
    }

    private function metricInt(WorkerStatusSnapshot $snapshot, string $key): int
    {
        return (int) ($snapshot->metrics[$key] ?? 0);
    }

    private function metricString(WorkerStatusSnapshot $snapshot, string $key): string
    {
        $value = $snapshot->metrics[$key] ?? 'n/a';

        return is_string($value) ? $value : (string) $value;
    }

    private function clip(string $value, int $width): string
    {
        if (strlen($value) <= $width) {
            return str_pad($value, $width);
        }

        return substr($value, 0, max(0, $width - 3)) . '...';
    }
}
