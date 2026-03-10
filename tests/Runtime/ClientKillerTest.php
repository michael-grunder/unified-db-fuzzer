<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Tests\Runtime;

use Mgrunder\Fuzz\Runtime\AdminClient;
use Mgrunder\Fuzz\Runtime\AdminClientFactory;
use Mgrunder\Fuzz\Runtime\ClientKillLogger;
use Mgrunder\Fuzz\Runtime\ClientKillOptions;
use Mgrunder\Fuzz\Runtime\ClientKillProgress;
use Mgrunder\Fuzz\Runtime\ClientKiller;
use Mgrunder\Fuzz\Runtime\RedisClientConnection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ClientKillerTest extends TestCase
{
    #[Test]
    public function it_selects_clients_deterministically_and_excludes_its_own_connection(): void
    {
        $firstRun = $this->runKillerForThreeIterations();
        $secondRun = $this->runKillerForThreeIterations();

        self::assertSame($firstRun, $secondRun);
        self::assertNotContains(7, $firstRun);
    }

    /**
     * @return list<int>
     */
    private function runKillerForThreeIterations(): array
    {
        $client = new class() implements AdminClient {
            /** @var list<int> */
            public array $killedClientIds = [];

            public function currentClientId(): int
            {
                return 7;
            }

            public function listClients(): array
            {
                return [
                    new RedisClientConnection(7, 'relay@default:1', 'relay'),
                    new RedisClientConnection(10, 'relay@default:2', 'relay'),
                    new RedisClientConnection(11, 'relay@default:3', 'relay'),
                    new RedisClientConnection(12, null, 'phpredis'),
                    new RedisClientConnection(13, null, null),
                ];
            }

            public function killClientById(int $clientId): bool
            {
                $this->killedClientIds[] = $clientId;

                return true;
            }
        };

        $sleepCalls = 0;
        $killer = new ClientKiller(
            new class($client) implements AdminClientFactory {
                public function __construct(
                    private readonly AdminClient $client,
                ) {
                }

                public function connect(string $host, int $port, ?float $timeout = null, ?float $readTimeout = null): AdminClient
                {
                    return $this->client;
                }
            },
            static fn (): float => 0.0,
            static function (int $micros) use (&$sleepCalls): void {
                $sleepCalls++;

                if ($sleepCalls >= 3) {
                    throw new RuntimeException('stop');
                }
            },
        );

        try {
            $killer->run(
                new ClientKillOptions(
                    host: 'localhost',
                    port: 6379,
                    timeout: null,
                    readTimeout: null,
                    minSleepMicros: 1,
                    maxSleepMicros: 1,
                    minKillsPerIteration: 2,
                    maxKillsPerIteration: 3,
                    relayOnly: true,
                    seed: 1234,
                ),
                new class() implements ClientKillLogger {
                    public function logProgress(ClientKillProgress $progress): void
                    {
                    }
                },
            );
        } catch (RuntimeException $exception) {
            self::assertSame('stop', $exception->getMessage());
        }

        return $client->killedClientIds;
    }

    #[Test]
    public function it_can_target_non_relay_clients_when_requested(): void
    {
        $client = new class() implements AdminClient {
            /** @var list<int> */
            public array $killedClientIds = [];

            public function currentClientId(): int
            {
                return 7;
            }

            public function listClients(): array
            {
                return [
                    new RedisClientConnection(7, 'relay@default:1', 'relay'),
                    new RedisClientConnection(10, null, 'phpredis'),
                    new RedisClientConnection(11, null, null),
                ];
            }

            public function killClientById(int $clientId): bool
            {
                $this->killedClientIds[] = $clientId;

                throw new RuntimeException('stop');
            }
        };

        $killer = new ClientKiller(
            new class($client) implements AdminClientFactory {
                public function __construct(
                    private readonly AdminClient $client,
                ) {
                }

                public function connect(string $host, int $port, ?float $timeout = null, ?float $readTimeout = null): AdminClient
                {
                    return $this->client;
                }
            },
            static fn (): float => 0.0,
            static function (int $micros): void {
            },
        );

        try {
            $killer->run(
                new ClientKillOptions(
                    host: 'localhost',
                    port: 6379,
                    timeout: null,
                    readTimeout: null,
                    minSleepMicros: 0,
                    maxSleepMicros: 0,
                    minKillsPerIteration: 1,
                    maxKillsPerIteration: 1,
                    relayOnly: false,
                    seed: 1,
                ),
                new class() implements ClientKillLogger {
                    public function logProgress(ClientKillProgress $progress): void
                    {
                    }
                },
            );
        } catch (RuntimeException $exception) {
            self::assertSame('stop', $exception->getMessage());
        }

        self::assertContains($client->killedClientIds[0], [10, 11]);
    }
}
