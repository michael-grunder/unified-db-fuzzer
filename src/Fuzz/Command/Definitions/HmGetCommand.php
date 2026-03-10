<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Fuzz\Command\Definitions;

use Mgrunder\Fuzz\Fuzz\Command\AbstractHashReadCommand;
use Mgrunder\Fuzz\Fuzz\ObservedAge;
use Mgrunder\Fuzz\Fuzz\RedisOperation;

final class HmGetCommand extends AbstractHashReadCommand
{
    public function name(): string
    {
        return 'hmget';
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
