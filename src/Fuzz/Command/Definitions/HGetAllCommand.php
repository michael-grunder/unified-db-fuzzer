<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Fuzz\Command\Definitions;

use Mgrunder\Fuzz\Fuzz\Command\AbstractSingleKeyCommand;
use Mgrunder\Fuzz\Fuzz\CommandFlags;
use Mgrunder\Fuzz\Fuzz\ObservedAge;
use Mgrunder\Fuzz\Fuzz\RedisDataType;
use Mgrunder\Fuzz\Fuzz\RedisOperation;

final class HGetAllCommand extends AbstractSingleKeyCommand
{
    public function name(): string
    {
        return 'hgetall';
    }

    public function type(): RedisDataType
    {
        return RedisDataType::Hash;
    }

    public function flags(): int
    {
        return CommandFlags::READ;
    }

    public function observeAge(RedisOperation $operation, mixed $result): ?ObservedAge
    {
        if ($operation->primaryKey === null || !is_array($result)) {
            return null;
        }

        $age = self::oldestArrayAge($result, hrtime(true));

        return $age === null ? null : new ObservedAge($operation->primaryKey, $age);
    }
}
