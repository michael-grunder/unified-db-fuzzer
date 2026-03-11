<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime;

use Mgrunder\Fuzz\Fuzz\Command\Definitions\GetCommand;
use Mgrunder\Fuzz\Fuzz\Command\Definitions\SetCommand;
use Mgrunder\Fuzz\Fuzz\FreshnessEnvelope;
use Mgrunder\Fuzz\Fuzz\FuzzContext;
use Mgrunder\Fuzz\Fuzz\ObservedStaleness;
use Mgrunder\Fuzz\Fuzz\RedisOperation;
use Mgrunder\Fuzz\Fuzz\RedisDataType;
use Throwable;

final class StalenessWorkerRunner
{
    private const GLOBAL_VERSION_KEY = 'fuzz:global_version';
    private const STALE_REPROBE_INTERVAL = 8;

    private readonly GetCommand $getCommand;
    private readonly SetCommand $setCommand;

    /**
     * @var array<string, StalenessKeyState>
     */
    private array $keyStates = [];

    private int $writerSeq = 0;
    private int $staleProbeCursor = 0;

    public function __construct(
        private readonly ClientFactory $cacheClientFactory,
        private readonly ClientFactory $truthClientFactory,
    ) {
        $this->getCommand = new GetCommand();
        $this->setCommand = new SetCommand();
    }

    public function run(WorkOptions $options, int $workerIndex, WorkerLogger $logger): StalenessRunSummary
    {
        $seed = $options->seed ?? random_int(1, PHP_INT_MAX);
        $context = new FuzzContext($options->keys, $options->members, $seed + $workerIndex);
        $statistics = new StalenessWorkerStatistics($options->stalenessThresholds->topN);
        $cacheClient = $this->connect($this->cacheClientFactory, $options);
        $truthClient = $this->connect($this->truthClientFactory, $options);
        $lastReport = microtime(true);

        $logger->log(
            sprintf(
                'staleness worker started: worker=%d seed=%d ops=%s keys=%d hot_keys=%d persistent_checks=%d hard_steps=%d',
                $workerIndex,
                $context->seed,
                StatusFormatter::formatOps($options->ops),
                $options->keys,
                $this->hotKeyCount($options->keys),
                $options->stalenessThresholds->persistentChecks,
                $options->stalenessThresholds->hardFailureSteps,
            ),
        );
        $logger->updateWorkerStatus($statistics->snapshot($workerIndex, $options, 'running'));

        $terminatedEarly = false;

        for ($i = 0; $options->ops < 0 || $i < $options->ops; $i++) {
            try {
                $this->executeOperation($context, $options, $cacheClient, $truthClient, $statistics);
                $this->maybeReprobeCurrentStaleKey($options, $cacheClient, $truthClient, $statistics);
            } catch (Throwable $throwable) {
                $logger->log(sprintf('staleness worker exception after %d ops: %s', $statistics->done, WorkerStatistics::summarizeThrowable($throwable)));
                $terminatedEarly = true;
                break;
            }

            $now = microtime(true);
            if (($now - $lastReport) >= $options->reportInterval) {
                $logger->log($statistics->formatProgress($options));
                $logger->updateWorkerStatus($statistics->snapshot($workerIndex, $options, 'running'));
                $lastReport = $now;
            }

            if ($statistics->hardFailures > 0) {
                $terminatedEarly = true;
                break;
            }
        }

        $logger->log($statistics->formatFinished($options, $terminatedEarly));
        $logger->updateWorkerStatus($statistics->snapshot($workerIndex, $options, 'finished'));

        return new StalenessRunSummary($terminatedEarly, $statistics);
    }

    private function executeOperation(
        FuzzContext $context,
        WorkOptions $options,
        RedisClient $cacheClient,
        RedisClient $truthClient,
        StalenessWorkerStatistics $statistics,
    ): void {
        $statistics->recordDone();
        $roll = $context->randomIndex(99);
        $key = $this->pickKey($context);

        if ($roll < 30) {
            $this->writeKey($key, $context, $truthClient, $statistics);

            return;
        }

        if ($roll < 60) {
            $statistics->recordRead();
            $this->compareKey($key, $options, $cacheClient, $truthClient, $statistics, 'read_compare');

            return;
        }

        if ($roll < 70) {
            $statistics->recordDelete();
            $this->deleteKey($key, $cacheClient, $truthClient, $statistics);

            return;
        }

        if ($roll < 80) {
            $statistics->recordRead();
            $delayUs = $options->stalenessThresholds->delayBucketsUs[$context->randomIndex(count($options->stalenessThresholds->delayBucketsUs) - 1)];
            if ($delayUs > 0) {
                usleep($delayUs);
            }

            $this->compareKey($key, $options, $cacheClient, $truthClient, $statistics, 'read_after_delay', $delayUs);

            return;
        }

        if ($roll < 90) {
            $statistics->recordDelete();
            $this->deleteKey($key, $cacheClient, $truthClient, $statistics);
            $this->writeKey($key, $context, $truthClient, $statistics);

            return;
        }

        $burst = 2 + $context->randomIndex(3);
        for ($iteration = 0; $iteration < $burst; $iteration++) {
            $this->writeKey($key, $context, $truthClient, $statistics);
        }
    }

    private function writeKey(string $key, FuzzContext $context, RedisClient $truthClient, StalenessWorkerStatistics $statistics): void
    {
        $statistics->recordWrite();
        $this->clearKeyState($key);
        $statistics->clearCurrentObservation($key);
        $version = $truthClient->execute(new RedisOperation('incr', [self::GLOBAL_VERSION_KEY]));
        $writtenNs = hrtime(true);
        $writerSeq = ++$this->writerSeq;
        $payload = $context->newPayload();
        $raw = FreshnessEnvelope::encode(
            $key,
            (int) $version,
            $writtenNs,
            getmypid() ?: 0,
            sprintf('worker-%d', getmypid() ?: 0),
            $writerSeq,
            $payload,
        );

        $truthClient->execute(new RedisOperation($this->setCommand->name(), [$key, $raw], $key));
    }

    private function deleteKey(
        string $key,
        RedisClient $cacheClient,
        RedisClient $truthClient,
        StalenessWorkerStatistics $statistics,
    ): void {
        $this->clearKeyState($key);
        $statistics->clearCurrentObservation($key);
        $operation = new RedisOperation('del', [$key], $key);
        $cacheClient->execute($operation);
        $truthClient->execute($operation);
    }

    public function compareKey(
        string $key,
        WorkOptions $options,
        RedisClient $cacheClient,
        RedisClient $truthClient,
        StalenessWorkerStatistics $statistics,
        string $phase,
        int $initialDelayUs = 0,
    ): void {
        $operation = new RedisOperation($this->getCommand->name(), [$key], $key);
        $cached = $cacheClient->execute($operation);
        $truth = $truthClient->execute($operation);
        $observation = $this->getCommand->observeStaleness($operation, $cached, $truth, hrtime(true));

        if ($observation === null) {
            $this->clearKeyState($key);
            $statistics->clearCurrentObservation($key);

            return;
        }

        $observation = $this->applyKeyState($observation);
        if ($observation->classification === 'fresh') {
            $this->clearKeyState($key);
            $statistics->clearCurrentObservation($key);

            return;
        }

        $rechecks = 1;

        if ($observation->classification === 'transient_stale' || str_starts_with($observation->classification, 'stale_')) {
            foreach ($options->stalenessThresholds->delayBucketsUs as $delayUs) {
                if ($delayUs === 0 && $initialDelayUs === 0) {
                    continue;
                }

                usleep($delayUs);
                $rechecks++;
                $cached = $cacheClient->execute($operation);
                $truth = $truthClient->execute($operation);
                $recheck = $this->getCommand->observeStaleness($operation, $cached, $truth, hrtime(true));
                if ($recheck === null) {
                    $this->clearKeyState($key);
                    $statistics->clearCurrentObservation($key);
                    $observation = $observation->with(
                        classification: 'transient_stale',
                        debug: $observation->debug + [
                            'phase' => $phase,
                            'rechecks' => $rechecks,
                            'initial_delay_us' => $initialDelayUs,
                        ],
                    );
                    break;
                }

                $observation = $this->applyKeyState($recheck);

                if ($observation->classification === 'fresh') {
                    $this->clearKeyState($key);
                    $statistics->clearCurrentObservation($key);
                    $observation = $recheck->with(
                        classification: 'transient_stale',
                        debug: $recheck->debug + [
                            'phase' => $phase,
                            'rechecks' => $rechecks,
                            'initial_delay_us' => $initialDelayUs,
                        ],
                    );
                    break;
                }

                if ($rechecks >= $options->stalenessThresholds->persistentChecks) {
                    $observation = $this->classifyPersistentObservation($observation, $phase, $rechecks, $initialDelayUs);
                    break;
                }
            }
        }

        if (($observation->stepsBehind ?? 0) >= $options->stalenessThresholds->severeSteps) {
            $observation = $observation->with(suspicious: true);
        }

        $observation = $this->applyFollowUpState($observation, $phase, $options->stalenessThresholds);
        $hardFailure = $this->isHardFailure($observation, $options->stalenessThresholds);
        $statistics->observe($observation, $hardFailure);
    }

    private function maybeReprobeCurrentStaleKey(
        WorkOptions $options,
        RedisClient $cacheClient,
        RedisClient $truthClient,
        StalenessWorkerStatistics $statistics,
    ): void {
        if ($statistics->done % self::STALE_REPROBE_INTERVAL !== 0) {
            return;
        }

        $keys = $statistics->currentObservationKeys();
        if ($keys === []) {
            return;
        }

        $key = $keys[$this->staleProbeCursor % count($keys)];
        $this->staleProbeCursor++;

        $statistics->recordRead();
        $this->compareKey($key, $options, $cacheClient, $truthClient, $statistics, 'stale_follow_up');
    }

    private function applyKeyState(ObservedStaleness $observation): ObservedStaleness
    {
        $state = $this->keyStates[$observation->key] ??= new StalenessKeyState();
        $regression = false;

        if ($observation->cachedVersion !== null) {
            if ($state->maxCachedVersionSeen !== null && $observation->cachedVersion < $state->maxCachedVersionSeen) {
                $regression = true;
            }

            $state->maxCachedVersionSeen = max($state->maxCachedVersionSeen ?? $observation->cachedVersion, $observation->cachedVersion);
        }

        if ($observation->truthVersion !== null) {
            $state->maxTruthVersionSeen = max($state->maxTruthVersionSeen ?? $observation->truthVersion, $observation->truthVersion);
        }

        if (($observation->stepsBehind ?? 0) > 0 && $observation->cachedVersion !== null) {
            $state->staleStreak++;

            if ($state->lastStaleCachedVersion === $observation->cachedVersion) {
                $state->sameStaleVersionCount++;
            } else {
                $state->sameStaleVersionCount = 1;
                $state->lastStaleCachedVersion = $observation->cachedVersion;
            }
        } else {
            $state->staleStreak = 0;
            $state->sameStaleVersionCount = 0;
            $state->lastStaleCachedVersion = null;
        }

        $classification = $observation->classification;
        if ($regression) {
            $classification = 'stale_regression';
        } elseif ($state->sameStaleVersionCount > 1 && ($observation->stepsBehind ?? 0) > 0) {
            $classification = 'stale_same_version_repeated';
        }

        return $observation->with(
            regression: $regression,
            suspicious: $observation->suspicious || $regression,
            classification: $classification,
            debug: $observation->debug + [
                'stale_streak' => $state->staleStreak,
                'same_stale_version_count' => $state->sameStaleVersionCount,
                'max_cached_version_seen' => $state->maxCachedVersionSeen,
                'max_truth_version_seen' => $state->maxTruthVersionSeen,
            ],
        );
    }

    private function applyFollowUpState(
        ObservedStaleness $observation,
        string $phase,
        StalenessThresholds $thresholds,
    ): ObservedStaleness {
        $state = $this->keyStates[$observation->key] ??= new StalenessKeyState();

        if ($observation->classification !== 'stale_missing_after_create') {
            $state->missingAfterCreateFollowUpCount = 0;

            return $observation->with(
                debug: $observation->debug + ['missing_after_create_follow_up_count' => 0],
            );
        }

        if ($phase === 'stale_follow_up') {
            $state->missingAfterCreateFollowUpCount++;
        } else {
            $state->missingAfterCreateFollowUpCount = 0;
        }

        $classification = $observation->classification;
        if ($state->missingAfterCreateFollowUpCount >= $thresholds->persistentChecks) {
            $classification = 'persistent_stale';
        }

        return $observation->with(
            suspicious: true,
            classification: $classification,
            debug: $observation->debug + [
                'missing_after_create_follow_up_count' => $state->missingAfterCreateFollowUpCount,
            ],
        );
    }

    private function clearKeyState(string $key): void
    {
        $state = $this->keyStates[$key] ?? null;
        if ($state === null) {
            return;
        }

        $state->staleStreak = 0;
        $state->sameStaleVersionCount = 0;
        $state->lastStaleCachedVersion = null;
        $state->missingAfterCreateFollowUpCount = 0;
    }

    private function classifyPersistentObservation(
        ObservedStaleness $observation,
        string $phase,
        int $rechecks,
        int $initialDelayUs,
    ): ObservedStaleness {
        $classification = $observation->classification;

        if ($classification === 'stale_same_version_repeated') {
            $classification = 'persistent_stale';
        } elseif ($classification === 'transient_stale') {
            $classification = 'persistent_stale';
        }

        return $observation->with(
            suspicious: true,
            classification: $classification,
            debug: $observation->debug + [
                'phase' => $phase,
                'rechecks' => $rechecks,
                'initial_delay_us' => $initialDelayUs,
            ],
        );
    }

    private function isHardFailure(ObservedStaleness $observation, StalenessThresholds $thresholds): bool
    {
        if ($observation->regression || $observation->classification === 'impossible_order') {
            return true;
        }

        if (in_array($observation->classification, ['stale_exists_after_delete', 'persistent_stale'], true)) {
            return true;
        }

        if (($observation->stepsBehind ?? 0) >= $thresholds->hardFailureSteps) {
            return true;
        }

        return ($observation->debug['same_stale_version_count'] ?? 0) >= $thresholds->stuckRepeats;
    }

    private function pickKey(FuzzContext $context): string
    {
        $roll = $context->randomIndex(99);
        $hotLimit = $this->hotKeyCount($context->keys);
        $warmLimit = max($hotLimit, $context->keys);

        if ($roll < 70) {
            $index = $context->randomIndex($hotLimit - 1);
        } elseif ($roll < 95) {
            $index = $context->randomIndex($warmLimit - 1);
        } else {
            $index = $warmLimit + $context->randomIndex(max(1, $context->keys) - 1) + 1;
        }

        return sprintf('fuzz:%s:%d', RedisDataType::String->value, $index);
    }

    private function hotKeyCount(int $keys): int
    {
        return max(1, min($keys, 8));
    }

    private function connect(ClientFactory $clientFactory, WorkOptions $options): RedisClient
    {
        return new ResilientRedisClient(
            $clientFactory,
            $options->host,
            $options->port,
            $options->timeout,
            $options->readTimeout,
            $clientFactory->connect(
                $options->host,
                $options->port,
                $options->timeout,
                $options->readTimeout,
            ),
        );
    }
}
