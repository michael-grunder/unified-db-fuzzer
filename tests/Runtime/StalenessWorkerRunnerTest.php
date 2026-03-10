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
use Mgrunder\Fuzz\Runtime\WorkOptions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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
}
