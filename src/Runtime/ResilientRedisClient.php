<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime;

use Closure;
use Mgrunder\Fuzz\Fuzz\RedisOperation;
use Throwable;

final class ResilientRedisClient implements RedisClient
{
    public function __construct(
        private readonly ClientFactory $clientFactory,
        private readonly string $host,
        private readonly int $port,
        private readonly ?float $timeout,
        private readonly ?float $readTimeout,
        private RedisClient $client,
        private readonly int $maxReconnectAttempts = 1,
    ) {
    }

    public function execute(RedisOperation $operation): mixed
    {
        return $this->runWithReconnect(
            static fn (RedisClient $client): mixed => $client->execute($operation),
        );
    }

    public function flushDatabase(): void
    {
        $this->runWithReconnect(static function (RedisClient $client): null {
            $client->flushDatabase();

            return null;
        });
    }

    /**
     * @template TResult
     *
     * @param Closure(RedisClient): TResult $callback
     *
     * @return TResult
     */
    private function runWithReconnect(Closure $callback): mixed
    {
        $attempts = 0;

        while (true) {
            try {
                return $callback($this->client);
            } catch (Throwable $throwable) {
                if (
                    $attempts >= $this->maxReconnectAttempts
                    || !RedisConnectionFailureDetector::isRetryable($throwable)
                ) {
                    throw $throwable;
                }

                $attempts++;
                $this->client = $this->clientFactory->connect(
                    $this->host,
                    $this->port,
                    $this->timeout,
                    $this->readTimeout,
                );
            }
        }
    }
}
