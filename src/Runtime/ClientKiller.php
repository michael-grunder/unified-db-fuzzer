<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime;

use Random\Engine\Mt19937;
use Random\Randomizer;

use function array_splice;
use function count;
use function microtime;
use function random_int;
use function usleep;

final class ClientKiller implements ClientKillApplication
{
    private const float REPORT_INTERVAL_SECONDS = 1.0;

    /**
     * @var \Closure(): float
     */
    private readonly \Closure $clock;

    /**
     * @var \Closure(int): void
     */
    private readonly \Closure $sleeper;

    public function __construct(
        private readonly AdminClientFactory $clientFactory,
        ?\Closure $clock = null,
        ?\Closure $sleeper = null,
    ) {
        $this->clock = $clock ?? static fn (): float => microtime(true);
        $this->sleeper = $sleeper ?? static function (int $micros): void {
            usleep($micros);
        };
    }

    public function run(ClientKillOptions $options, ClientKillLogger $logger): int
    {
        $client = $this->clientFactory->connect(
            $options->host,
            $options->port,
            $options->timeout,
            $options->readTimeout,
        );

        $selfClientId = $client->currentClientId();
        $randomizer = new Randomizer(new Mt19937($options->seed ?? random_int(PHP_INT_MIN, PHP_INT_MAX)));

        $iteration = 0;
        $totalKilledClients = 0;
        $lastReportAt = ($this->clock)();

        for (;;) {
            $iteration++;
            $killedThisIteration = 0;
            $clientIds = $this->targetClientIds($client->listClients(), $selfClientId, $options->relayOnly);
            $targetCount = $this->randomInRange(
                $randomizer,
                $options->minKillsPerIteration,
                $options->maxKillsPerIteration,
            );

            foreach ($this->pickClientIds($clientIds, $targetCount, $randomizer) as $clientId) {
                if ($client->killClientById($clientId)) {
                    $killedThisIteration++;
                }
            }

            $totalKilledClients += $killedThisIteration;

            $now = ($this->clock)();
            if (($now - $lastReportAt) >= self::REPORT_INTERVAL_SECONDS) {
                $logger->logProgress(new ClientKillProgress(
                    $iteration,
                    $totalKilledClients,
                    $killedThisIteration,
                ));
                $lastReportAt = $now;
            }

            $sleepMicros = $this->randomInRange(
                $randomizer,
                $options->minSleepMicros,
                $options->maxSleepMicros,
            );

            if ($sleepMicros > 0) {
                ($this->sleeper)($sleepMicros);
            }
        }
    }

    /**
     * @param list<RedisClientConnection> $clients
     * @return list<int>
     */
    private function targetClientIds(array $clients, int $selfClientId, bool $relayOnly): array
    {
        $filtered = [];

        foreach ($clients as $client) {
            if ($client->id === $selfClientId) {
                continue;
            }

            if ($relayOnly && !$client->isRelayConnection()) {
                continue;
            }

            $filtered[] = $client->id;
        }

        return $filtered;
    }

    /**
     * @param list<int> $clientIds
     * @return list<int>
     */
    private function pickClientIds(array $clientIds, int $requestedCount, Randomizer $randomizer): array
    {
        $available = $clientIds;
        $count = count($available);

        if ($count === 0 || $requestedCount <= 0) {
            return [];
        }

        $selected = [];
        $targetCount = min($requestedCount, $count);

        for ($i = 0; $i < $targetCount; $i++) {
            $index = $randomizer->getInt(0, count($available) - 1);
            $selected[] = $available[$index];
            array_splice($available, $index, 1);
        }

        return $selected;
    }

    private function randomInRange(Randomizer $randomizer, int $min, int $max): int
    {
        if ($min === $max) {
            return $min;
        }

        return $randomizer->getInt($min, $max);
    }
}
