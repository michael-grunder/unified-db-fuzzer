<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Fuzz\Command;

use Mgrunder\Fuzz\Fuzz\CommandFlags;
use Mgrunder\Fuzz\Fuzz\FuzzContext;
use Mgrunder\Fuzz\Fuzz\ObservedAge;
use Mgrunder\Fuzz\Fuzz\RedisDataType;
use Mgrunder\Fuzz\Fuzz\RedisOperation;

abstract class AbstractMultiKeyCommand extends RedisCommand
{
    public function flags(): int
    {
        return CommandFlags::READ;
    }

    public function createOperation(FuzzContext $context): RedisOperation
    {
        $keys = $context->randomKeys($this->type() ?? RedisDataType::String);

        return new RedisOperation(
            $this->name(),
            [$keys],
            readKeys: $keys,
        );
    }

    public function observeAge(RedisOperation $operation, mixed $result): ?ObservedAge
    {
        if ($operation->readKeys === null || !is_array($result)) {
            return null;
        }

        $nowNs = hrtime(true);
        $oldest = null;

        foreach ($operation->readKeys as $index => $key) {
            $age = self::timestampAgeNs($result[$index] ?? null, $nowNs);
            if ($age === null || ($oldest !== null && $age <= $oldest->ageNs)) {
                continue;
            }

            $oldest = new ObservedAge($key, $age);
        }

        return $oldest;
    }
}
