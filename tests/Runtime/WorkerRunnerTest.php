<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Tests\Runtime;

use Mgrunder\Fuzz\Fuzz\AgeUnit;
use Mgrunder\Fuzz\Fuzz\Command\CommandRegistry;
use Mgrunder\Fuzz\Fuzz\Command\RedisCommand;
use Mgrunder\Fuzz\Fuzz\CommandFlags;
use Mgrunder\Fuzz\Fuzz\FuzzContext;
use Mgrunder\Fuzz\Fuzz\RedisDataType;
use Mgrunder\Fuzz\Fuzz\RedisOperation;
use Mgrunder\Fuzz\Runtime\ClientFactory;
use Mgrunder\Fuzz\Runtime\RedisClient;
use Mgrunder\Fuzz\Runtime\RunSummary;
use Mgrunder\Fuzz\Runtime\StatsProvider;
use Mgrunder\Fuzz\Runtime\WorkOptions;
use Mgrunder\Fuzz\Runtime\WorkerLogger;
use Mgrunder\Fuzz\Runtime\WorkerRunner;
use Closure;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class WorkerRunnerTest extends TestCase
{
    #[Test]
    public function it_records_reconnect_failures_and_stops_early(): void
    {
        $runner = new WorkerRunner(
            new class() implements ClientFactory {
                private int $attempts = 0;

                public function connect(string $host, int $port, ?float $timeout = null, ?float $readTimeout = null): RedisClient
                {
                    $this->attempts++;

                    if ($this->attempts > 1) {
                        throw new RuntimeException('connect failed');
                    }

                    return new class() implements RedisClient {
                        public function execute(RedisOperation $operation): mixed
                        {
                            throw new RuntimeException('boom');
                        }

                        public function flushDatabase(): void
                        {
                        }
                    };
                }
            },
            new CommandRegistry([new class() extends RedisCommand {
                public function name(): string
                {
                    return 'get';
                }

                public function type(): RedisDataType
                {
                    return RedisDataType::String;
                }

                public function flags(): int
                {
                    return CommandFlags::READ;
                }

                public function createOperation(FuzzContext $context): RedisOperation
                {
                    return new RedisOperation('get', ['string:1'], 'string:1');
                }
            }]),
            new class() implements StatsProvider {
                public function summary(): string
                {
                    return 'cache=unknown';
                }
            },
        );

        $summary = $runner->run(
            new WorkOptions(
                host: 'localhost',
                port: 6379,
                timeout: null,
                readTimeout: null,
                keys: 10,
                members: 5,
                workers: 0,
                ops: 1,
                reportInterval: 10.0,
                ageUnit: AgeUnit::Microseconds,
                seed: 7,
            ),
            0,
            new class() implements WorkerLogger {
                public function log(string $message): void
                {
                }
            },
        );

        self::assertInstanceOf(RunSummary::class, $summary);
        self::assertTrue($summary->terminatedEarly);
        self::assertSame(2, $summary->statistics->exceptions);
        self::assertSame(1, $summary->statistics->reconnectFailures);
        self::assertSame('reconnect failed: RuntimeException: connect failed', $summary->statistics->lastException);
    }

    #[Test]
    public function it_passes_timeout_settings_to_the_client_factory(): void
    {
        $captured = [];
        $capture = static function (array $connection) use (&$captured): void {
            $captured = $connection;
        };

        $runner = new WorkerRunner(
            new class($capture) implements ClientFactory {
                /**
                 * @param Closure(array<string, mixed>): void $capture
                 */
                public function __construct(
                    private Closure $capture,
                ) {
                }

                public function connect(string $host, int $port, ?float $timeout = null, ?float $readTimeout = null): RedisClient
                {
                    ($this->capture)([
                        'host' => $host,
                        'port' => $port,
                        'timeout' => $timeout,
                        'readTimeout' => $readTimeout,
                    ]);

                    return new class() implements RedisClient {
                        public function execute(RedisOperation $operation): mixed
                        {
                            return null;
                        }

                        public function flushDatabase(): void
                        {
                        }
                    };
                }
            },
            new CommandRegistry([]),
            new class() implements StatsProvider {
                public function summary(): string
                {
                    return 'cache=unknown';
                }
            },
        );

        $runner->connect(new WorkOptions(
            host: 'redis.internal',
            port: 6380,
            timeout: 0.75,
            readTimeout: 2.5,
            keys: 10,
            members: 5,
            workers: 0,
            ops: 1,
            reportInterval: 10.0,
            ageUnit: AgeUnit::Microseconds,
            seed: 7,
        ));

        self::assertSame([
            'host' => 'redis.internal',
            'port' => 6380,
            'timeout' => 0.75,
            'readTimeout' => 2.5,
        ], $captured);
    }
}
