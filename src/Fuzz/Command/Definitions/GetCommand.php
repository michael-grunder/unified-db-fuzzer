<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Fuzz\Command\Definitions;

use Mgrunder\Fuzz\Fuzz\Command\AbstractSingleKeyCommand;
use Mgrunder\Fuzz\Fuzz\CommandFlags;
use Mgrunder\Fuzz\Fuzz\FreshnessEnvelope;
use Mgrunder\Fuzz\Fuzz\ObservedAge;
use Mgrunder\Fuzz\Fuzz\ObservedStaleness;
use Mgrunder\Fuzz\Fuzz\RedisDataType;
use Mgrunder\Fuzz\Fuzz\RedisOperation;

final class GetCommand extends AbstractSingleKeyCommand
{
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

    public function supportsStalenessRead(): bool
    {
        return true;
    }

    public function observeAge(RedisOperation $operation, mixed $result): ?ObservedAge
    {
        if ($operation->primaryKey === null) {
            return null;
        }

        $age = self::timestampAgeNs($result, hrtime(true));

        return $age === null ? null : new ObservedAge($operation->primaryKey, $age);
    }

    public function observeStaleness(
        RedisOperation $operation,
        mixed $cachedResult,
        mixed $truthResult,
        int $nowNs,
    ): ?ObservedStaleness {
        if ($operation->primaryKey === null) {
            return null;
        }

        $cachedEnvelope = FreshnessEnvelope::decode($cachedResult);
        $truthEnvelope = FreshnessEnvelope::decode($truthResult);
        $cachedExists = $cachedResult !== false && $cachedResult !== null;
        $truthExists = $truthResult !== false && $truthResult !== null;

        if (!$cachedExists && !$truthExists) {
            return null;
        }

        if (($cachedExists && $cachedEnvelope === null) || ($truthExists && $truthEnvelope === null)) {
            return new ObservedStaleness(
                key: $operation->primaryKey,
                command: strtoupper($this->name()),
                cachedExists: $cachedExists,
                truthExists: $truthExists,
                cachedVersion: $cachedEnvelope?->version,
                truthVersion: $truthEnvelope?->version,
                cachedWrittenNs: $cachedEnvelope?->writtenNs,
                truthWrittenNs: $truthEnvelope?->writtenNs,
                stepsBehind: null,
                ageNs: null,
                regression: false,
                suspicious: true,
                classification: 'impossible_order',
                debug: [
                    'cached_decode_ok' => $cachedEnvelope !== null,
                    'truth_decode_ok' => $truthEnvelope !== null,
                    'now_ns' => $nowNs,
                ],
            );
        }

        if ($truthEnvelope === null) {
            return new ObservedStaleness(
                key: $operation->primaryKey,
                command: strtoupper($this->name()),
                cachedExists: true,
                truthExists: false,
                cachedVersion: $cachedEnvelope->version,
                truthVersion: null,
                cachedWrittenNs: $cachedEnvelope->writtenNs,
                truthWrittenNs: null,
                stepsBehind: null,
                ageNs: null,
                regression: false,
                suspicious: true,
                classification: 'stale_exists_after_delete',
            );
        }

        if ($cachedEnvelope === null) {
            return new ObservedStaleness(
                key: $operation->primaryKey,
                command: strtoupper($this->name()),
                cachedExists: false,
                truthExists: true,
                cachedVersion: null,
                truthVersion: $truthEnvelope->version,
                cachedWrittenNs: null,
                truthWrittenNs: $truthEnvelope->writtenNs,
                stepsBehind: null,
                ageNs: null,
                regression: false,
                suspicious: true,
                classification: 'stale_missing_after_create',
            );
        }

        $stepsBehind = $truthEnvelope->version - $cachedEnvelope->version;
        $ageNs = $truthEnvelope->writtenNs - $cachedEnvelope->writtenNs;
        $classification = $stepsBehind === 0 ? 'fresh' : 'transient_stale';
        $suspicious = $stepsBehind > 0;

        if ($stepsBehind < 0 || $ageNs < 0 || $cachedEnvelope->key !== $truthEnvelope->key) {
            $classification = 'impossible_order';
            $suspicious = true;
        }

        return new ObservedStaleness(
            key: $operation->primaryKey,
            command: strtoupper($this->name()),
            cachedExists: true,
            truthExists: true,
            cachedVersion: $cachedEnvelope->version,
            truthVersion: $truthEnvelope->version,
            cachedWrittenNs: $cachedEnvelope->writtenNs,
            truthWrittenNs: $truthEnvelope->writtenNs,
            stepsBehind: $stepsBehind,
            ageNs: $ageNs,
            regression: false,
            suspicious: $suspicious,
            classification: $classification,
            debug: [
                'cached_writer_pid' => $cachedEnvelope->writerPid,
                'cached_writer_seq' => $cachedEnvelope->writerSeq,
                'truth_writer_pid' => $truthEnvelope->writerPid,
                'truth_writer_seq' => $truthEnvelope->writerSeq,
            ],
        );
    }
}
