<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Fuzz;

use InvalidArgumentException;

final readonly class WorkerKeyspace
{
    /**
     * @param list<string> $allWorkerIds
     */
    public function __construct(
        public string $currentWorkerId,
        public array $allWorkerIds,
    ) {
        if (count($this->allWorkerIds) === 0) {
            throw new InvalidArgumentException('Worker keyspace requires at least one worker ID.');
        }

        if (!in_array($this->currentWorkerId, $this->allWorkerIds, true)) {
            throw new InvalidArgumentException('Current worker ID must be present in the worker keyspace.');
        }
    }

    public static function forWorker(int $workerIndex, int $workerCount): self
    {
        if ($workerCount <= 0) {
            throw new InvalidArgumentException('Worker count must be greater than zero.');
        }

        if ($workerIndex < 0 || $workerIndex >= $workerCount) {
            throw new InvalidArgumentException('Worker index must be within the configured worker count.');
        }

        $workerIds = [];

        for ($index = 0; $index < $workerCount; $index++) {
            $workerIds[] = self::idForIndex($index);
        }

        return new self(self::idForIndex($workerIndex), $workerIds);
    }

    private static function idForIndex(int $workerIndex): string
    {
        return sprintf('worker-%d', $workerIndex);
    }
}
