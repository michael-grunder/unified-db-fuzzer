<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Tests\Runtime;

use Mgrunder\Fuzz\Fuzz\AgeUnit;
use Mgrunder\Fuzz\Fuzz\FreshnessEnvelope;
use Mgrunder\Fuzz\Fuzz\RedisOperation;
use Mgrunder\Fuzz\Runtime\ClientFactory;
use Mgrunder\Fuzz\Runtime\RedisClient;
use Mgrunder\Fuzz\Runtime\StalenessWorkerStatistics;
use Mgrunder\Fuzz\Runtime\StalenessWorkerRunner;
use Mgrunder\Fuzz\Runtime\StalenessThresholds;
use Mgrunder\Fuzz\Runtime\WorkOptions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class StalenessWorkerRunnerTest extends TestCase
{
    #[Test]
    public function it_flags_cached_version_regression_as_a_hard_failure(): void
    {
        $cacheResponses = [
            FreshnessEnvelope::encode('fuzz:string:0', 5, 100, 1, 'worker-1', 1, 'first'),
            FreshnessEnvelope::encode('fuzz:string:0', 4, 110, 1, 'worker-1', 2, 'regressed'),
        ];

        $truthResponses = [
            FreshnessEnvelope::encode('fuzz:string:0', 5, 100, 2, 'worker-2', 1, 'first'),
            FreshnessEnvelope::encode('fuzz:string:0', 6, 120, 2, 'worker-2', 2, 'truth'),
        ];

        $runner = new StalenessWorkerRunner(
            new class($cacheResponses) implements ClientFactory {
                /**
                 * @param list<string> $responses
                 */
                public function __construct(
                    private readonly array $responses,
                ) {
                }

                public function connect(string $host, int $port, ?float $timeout = null, ?float $readTimeout = null): RedisClient
                {
                    return new class($this->responses) implements RedisClient {
                        private int $index = 0;

                        /**
                         * @param list<string> $responses
                         */
                        public function __construct(
                            private readonly array $responses,
                        ) {
                        }

                        public function execute(RedisOperation $operation): mixed
                        {
                            if ($operation->name === 'get') {
                                $response = $this->responses[min($this->index, count($this->responses) - 1)];
                                $this->index++;

                                return $response;
                            }

                            return 1;
                        }

                        public function flushDatabase(): void
                        {
                        }
                    };
                }
            },
            new class($truthResponses) implements ClientFactory {
                /**
                 * @param list<string> $responses
                 */
                public function __construct(
                    private readonly array $responses,
                ) {
                }

                public function connect(string $host, int $port, ?float $timeout = null, ?float $readTimeout = null): RedisClient
                {
                    return new class($this->responses) implements RedisClient {
                        private int $index = 0;

                        /**
                         * @param list<string> $responses
                         */
                        public function __construct(
                            private readonly array $responses,
                        ) {
                        }

                        public function execute(RedisOperation $operation): mixed
                        {
                            if ($operation->name === 'get') {
                                $response = $this->responses[min($this->index, count($this->responses) - 1)];
                                $this->index++;

                                return $response;
                            }

                            return 1;
                        }

                        public function flushDatabase(): void
                        {
                        }
                    };
                }
            },
        );

        $options = new WorkOptions(
            host: 'localhost',
            port: 6379,
            timeout: null,
            readTimeout: null,
            keys: 1,
            members: 1,
            workers: 0,
            ops: 2,
            reportInterval: 10.0,
            ageUnit: AgeUnit::Microseconds,
            seed: 2,
            staleness: true,
        );
        $statistics = new StalenessWorkerStatistics($options->stalenessThresholds->topN);

        $cacheClient = (new class($cacheResponses) implements ClientFactory {
            /**
             * @param list<string> $responses
             */
            public function __construct(
                private readonly array $responses,
            ) {
            }

            public function connect(string $host, int $port, ?float $timeout = null, ?float $readTimeout = null): RedisClient
            {
                return new class($this->responses) implements RedisClient {
                    private int $index = 0;

                    /**
                     * @param list<string> $responses
                     */
                    public function __construct(
                        private readonly array $responses,
                    ) {
                    }

                    public function execute(RedisOperation $operation): mixed
                    {
                        $response = $this->responses[min($this->index, count($this->responses) - 1)];
                        $this->index++;

                        return $response;
                    }

                    public function flushDatabase(): void
                    {
                    }
                };
            }
        })->connect('localhost', 6379);

        $truthClient = (new class($truthResponses) implements ClientFactory {
            /**
             * @param list<string> $responses
             */
            public function __construct(
                private readonly array $responses,
            ) {
            }

            public function connect(string $host, int $port, ?float $timeout = null, ?float $readTimeout = null): RedisClient
            {
                return new class($this->responses) implements RedisClient {
                    private int $index = 0;

                    /**
                     * @param list<string> $responses
                     */
                    public function __construct(
                        private readonly array $responses,
                    ) {
                    }

                    public function execute(RedisOperation $operation): mixed
                    {
                        $response = $this->responses[min($this->index, count($this->responses) - 1)];
                        $this->index++;

                        return $response;
                    }

                    public function flushDatabase(): void
                    {
                    }
                };
            }
        })->connect('localhost', 6379);

        $statistics->recordRead();
        $runner->compareKey('fuzz:string:0', $options, $cacheClient, $truthClient, $statistics, 'read_compare', 0);
        $statistics->recordRead();
        $runner->compareKey('fuzz:string:0', $options, $cacheClient, $truthClient, $statistics, 'read_compare', 0);

        self::assertSame(1, $statistics->regressions);
        self::assertGreaterThanOrEqual(1, $statistics->hardFailures);
        self::assertSame('stale_regression', $statistics->worstObservation?->classification);
    }

    #[Test]
    public function it_reconnects_after_retryable_connection_errors_without_exiting_early(): void
    {
        $connections = 0;
        $store = [];

        $factory = new class($connections, $store) implements ClientFactory {
            /**
             * @param array<string, string> $store
             */
            public function __construct(
                private int &$connections,
                private array &$store,
            ) {
            }

            public function connect(string $host, int $port, ?float $timeout = null, ?float $readTimeout = null): RedisClient
            {
                $this->connections++;
                $failFirstExecute = $this->connections === 1;

                return new class($this->store, $failFirstExecute) implements RedisClient {
                    /**
                     * @param array<string, string> $store
                     */
                    public function __construct(
                        private array &$store,
                        private bool $failFirstExecute,
                    ) {
                    }

                    public function execute(RedisOperation $operation): mixed
                    {
                        if ($this->failFirstExecute) {
                            $this->failFirstExecute = false;

                            throw new RuntimeException('read error on connection to localhost:6379');
                        }

                        return match ($operation->name) {
                            'get' => $this->store[$operation->arguments[0]] ?? false,
                            'set' => $this->store[$operation->arguments[0]] = $operation->arguments[1],
                            'del' => $this->delete($operation->arguments[0]),
                            'incr' => $this->increment($operation->arguments[0]),
                            default => null,
                        };
                    }

                    public function flushDatabase(): void
                    {
                        $this->store = [];
                    }

                    private function delete(string $key): int
                    {
                        if (!array_key_exists($key, $this->store)) {
                            return 0;
                        }

                        unset($this->store[$key]);

                        return 1;
                    }

                    private function increment(string $key): int
                    {
                        $next = ((int) ($this->store[$key] ?? 0)) + 1;
                        $this->store[$key] = (string) $next;

                        return $next;
                    }
                };
            }
        };

        $runner = new StalenessWorkerRunner($factory, $factory);

        $summary = $runner->run(
            new WorkOptions(
                host: 'localhost',
                port: 6379,
                timeout: null,
                readTimeout: null,
                keys: 1,
                members: 1,
                workers: 0,
                ops: 1,
                reportInterval: 10.0,
                ageUnit: AgeUnit::Microseconds,
                seed: 2,
                staleness: true,
            ),
            0,
            new class() implements \Mgrunder\Fuzz\Runtime\WorkerLogger {
                public function log(string $message): void
                {
                }

                public function updateWorkerStatus(\Mgrunder\Fuzz\Runtime\WorkerStatusSnapshot $snapshot): void
                {
                }
            },
        );

        self::assertFalse($summary->terminatedEarly);
        self::assertSame(1, $summary->statistics->done);
        self::assertSame(3, $connections);
    }

    #[Test]
    public function it_separates_currently_stale_keys_from_historical_worst_keys_in_snapshots(): void
    {
        $runner = new StalenessWorkerRunner($this->responseFactory([
            FreshnessEnvelope::encode('fuzz:string:0', 1, 100, 1, 'worker-1', 1, 'stale'),
            FreshnessEnvelope::encode('fuzz:string:0', 2, 200, 1, 'worker-1', 2, 'fresh'),
        ]), $this->responseFactory([
            FreshnessEnvelope::encode('fuzz:string:0', 2, 200, 2, 'worker-2', 1, 'truth'),
            FreshnessEnvelope::encode('fuzz:string:0', 2, 200, 2, 'worker-2', 2, 'truth'),
        ]));

        $options = new WorkOptions(
            host: 'localhost',
            port: 6379,
            timeout: null,
            readTimeout: null,
            keys: 1,
            members: 1,
            workers: 0,
            ops: 2,
            reportInterval: 10.0,
            ageUnit: AgeUnit::Microseconds,
            seed: 2,
            staleness: true,
            stalenessThresholds: new StalenessThresholds(delayBucketsUs: [0]),
        );
        $statistics = new StalenessWorkerStatistics($options->stalenessThresholds->topN);
        $cacheClient = $this->responseFactory([
            FreshnessEnvelope::encode('fuzz:string:0', 1, 100, 1, 'worker-1', 1, 'stale'),
            FreshnessEnvelope::encode('fuzz:string:0', 2, 200, 1, 'worker-1', 2, 'fresh'),
        ])->connect('localhost', 6379);
        $truthClient = $this->responseFactory([
            FreshnessEnvelope::encode('fuzz:string:0', 2, 200, 2, 'worker-2', 1, 'truth'),
            FreshnessEnvelope::encode('fuzz:string:0', 2, 200, 2, 'worker-2', 2, 'truth'),
        ])->connect('localhost', 6379);

        $statistics->recordRead();
        $runner->compareKey('fuzz:string:0', $options, $cacheClient, $truthClient, $statistics, 'read_compare');
        $statistics->recordRead();
        $runner->compareKey('fuzz:string:0', $options, $cacheClient, $truthClient, $statistics, 'read_compare');

        $snapshot = $statistics->snapshot(0, $options, 'running');

        self::assertCount(1, $snapshot->topKeys);
        self::assertSame('fuzz:string:0', $snapshot->topKeys[0]['key']);
        self::assertSame(1, $snapshot->topKeys[0]['consecutive_stale']);
        self::assertSame([], $snapshot->currentTopKeys);
    }

    #[Test]
    public function it_tracks_consecutive_stale_reads_in_a_row(): void
    {
        $runner = new StalenessWorkerRunner($this->responseFactory([
            FreshnessEnvelope::encode('fuzz:string:0', 1, 100, 1, 'worker-1', 1, 'stale-1'),
            FreshnessEnvelope::encode('fuzz:string:0', 1, 100, 1, 'worker-1', 1, 'stale-1'),
        ]), $this->responseFactory([
            FreshnessEnvelope::encode('fuzz:string:0', 2, 200, 2, 'worker-2', 1, 'truth'),
            FreshnessEnvelope::encode('fuzz:string:0', 2, 200, 2, 'worker-2', 1, 'truth'),
        ]));

        $options = new WorkOptions(
            host: 'localhost',
            port: 6379,
            timeout: null,
            readTimeout: null,
            keys: 1,
            members: 1,
            workers: 0,
            ops: 2,
            reportInterval: 10.0,
            ageUnit: AgeUnit::Microseconds,
            seed: 2,
            staleness: true,
            stalenessThresholds: new StalenessThresholds(delayBucketsUs: [0]),
        );
        $statistics = new StalenessWorkerStatistics($options->stalenessThresholds->topN);
        $cacheClient = $this->responseFactory([
            FreshnessEnvelope::encode('fuzz:string:0', 1, 100, 1, 'worker-1', 1, 'stale-1'),
            FreshnessEnvelope::encode('fuzz:string:0', 1, 100, 1, 'worker-1', 1, 'stale-1'),
        ])->connect('localhost', 6379);
        $truthClient = $this->responseFactory([
            FreshnessEnvelope::encode('fuzz:string:0', 2, 200, 2, 'worker-2', 1, 'truth'),
            FreshnessEnvelope::encode('fuzz:string:0', 2, 200, 2, 'worker-2', 1, 'truth'),
        ])->connect('localhost', 6379);

        $statistics->recordRead();
        $runner->compareKey('fuzz:string:0', $options, $cacheClient, $truthClient, $statistics, 'read_compare');
        $statistics->recordRead();
        $runner->compareKey('fuzz:string:0', $options, $cacheClient, $truthClient, $statistics, 'read_compare');

        $snapshot = $statistics->snapshot(0, $options, 'running');

        self::assertCount(1, $snapshot->currentTopKeys);
        self::assertSame(2, $snapshot->currentTopKeys[0]['consecutive_stale']);
        self::assertSame(2, $snapshot->topKeys[0]['consecutive_stale']);
    }

    /**
     * @param list<string> $responses
     */
    private function responseFactory(array $responses): ClientFactory
    {
        return new class($responses) implements ClientFactory {
            /**
             * @param list<string> $responses
             */
            public function __construct(
                private readonly array $responses,
            ) {
            }

            public function connect(string $host, int $port, ?float $timeout = null, ?float $readTimeout = null): RedisClient
            {
                return new class($this->responses) implements RedisClient {
                    private int $index = 0;

                    /**
                     * @param list<string> $responses
                     */
                    public function __construct(
                        private readonly array $responses,
                    ) {
                    }

                    public function execute(RedisOperation $operation): mixed
                    {
                        $response = $this->responses[min($this->index, count($this->responses) - 1)];
                        $this->index++;

                        return $response;
                    }

                    public function flushDatabase(): void
                    {
                    }
                };
            }
        };
    }
}
