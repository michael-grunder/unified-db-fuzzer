<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Fuzz\Command\Definitions;

use Mgrunder\Fuzz\Fuzz\Command\AbstractSingleKeyCommand;
use Mgrunder\Fuzz\Fuzz\CommandFlags;
use Mgrunder\Fuzz\Fuzz\ObservedAge;
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

    public function observeAge(RedisOperation $operation, mixed $result): ?ObservedAge
    {
        if ($operation->primaryKey === null) {
            return null;
        }

        $age = self::timestampAgeNs($result, hrtime(true));

        return $age === null ? null : new ObservedAge($operation->primaryKey, $age);
    }
}
