<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime;

final readonly class WorkerStatusSnapshot
{
    /**
     * @param array<string, int|float|string|null> $metrics
     * @param list<array{key: string, classification: string, steps_behind: int|null, age: string, regression: bool, consecutive_stale: int}> $topKeys
     * @param list<array{key: string, classification: string, steps_behind: int|null, age: string, regression: bool, consecutive_stale: int}> $currentTopKeys
     */
    public function __construct(
        public int $workerIndex,
        public int $pid,
        public string $mode,
        public string $state,
        public int $done,
        public ?int $targetOps,
        public float $startedAt,
        public float $updatedAt,
        public float $opsPerSecond,
        public ?string $lastException = null,
        public array $metrics = [],
        public array $topKeys = [],
        public array $currentTopKeys = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'worker_index' => $this->workerIndex,
            'pid' => $this->pid,
            'mode' => $this->mode,
            'state' => $this->state,
            'done' => $this->done,
            'target_ops' => $this->targetOps,
            'started_at' => $this->startedAt,
            'updated_at' => $this->updatedAt,
            'ops_per_second' => $this->opsPerSecond,
            'last_exception' => $this->lastException,
            'metrics' => $this->metrics,
            'top_keys' => $this->topKeys,
            'current_top_keys' => $this->currentTopKeys,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            workerIndex: (int) ($data['worker_index'] ?? 0),
            pid: (int) ($data['pid'] ?? 0),
            mode: (string) ($data['mode'] ?? 'standard'),
            state: (string) ($data['state'] ?? 'running'),
            done: (int) ($data['done'] ?? 0),
            targetOps: isset($data['target_ops']) ? (int) $data['target_ops'] : null,
            startedAt: (float) ($data['started_at'] ?? 0.0),
            updatedAt: (float) ($data['updated_at'] ?? 0.0),
            opsPerSecond: (float) ($data['ops_per_second'] ?? 0.0),
            lastException: isset($data['last_exception']) ? (string) $data['last_exception'] : null,
            metrics: is_array($data['metrics'] ?? null) ? $data['metrics'] : [],
            topKeys: is_array($data['top_keys'] ?? null) ? array_values($data['top_keys']) : [],
            currentTopKeys: is_array($data['current_top_keys'] ?? null) ? array_values($data['current_top_keys']) : [],
        );
    }
}
