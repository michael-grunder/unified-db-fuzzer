<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Fuzz\Command;

use Mgrunder\Fuzz\Fuzz\FuzzContext;
use Mgrunder\Fuzz\Fuzz\ObservedAge;
use Mgrunder\Fuzz\Fuzz\ObservedStaleness;
use Mgrunder\Fuzz\Fuzz\RedisDataType;
use Mgrunder\Fuzz\Fuzz\RedisOperation;

abstract class RedisCommand
{
    abstract public function name(): string;

    abstract public function type(): ?RedisDataType;

    abstract public function flags(): int;

    abstract public function createOperation(FuzzContext $context): RedisOperation;

    public function observeAge(RedisOperation $operation, mixed $result): ?ObservedAge
    {
        return null;
    }

    public function supportsStalenessRead(): bool
    {
        return false;
    }

    public function supportsStalenessWrite(): bool
    {
        return false;
    }

    public function supportsAuthoritativeCompare(): bool
    {
        return $this->supportsStalenessRead();
    }

    public function observeStaleness(
        RedisOperation $operation,
        mixed $cachedResult,
        mixed $truthResult,
        int $nowNs,
    ): ?ObservedStaleness {
        return null;
    }

    /**
     * @param array<mixed> $values
     */
    protected static function oldestArrayAge(array $values, int $nowNs): ?int
    {
        $oldestAge = null;

        foreach ($values as $value) {
            $age = self::timestampAgeNs($value, $nowNs);
            if ($age === null) {
                continue;
            }

            $oldestAge = $oldestAge === null ? $age : max($oldestAge, $age);
        }

        return $oldestAge;
    }

    protected static function timestampAgeNs(mixed $value, int $nowNs): ?int
    {
        if (is_int($value)) {
            $timestamp = $value;
        } elseif (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            $timestamp = (int) $value;
        } elseif (is_float($value) && is_finite($value) && $value >= 0) {
            $timestamp = (int) round($value);
        } else {
            return null;
        }

        $age = $nowNs - $timestamp;

        return $age >= 0 ? $age : null;
    }
}
