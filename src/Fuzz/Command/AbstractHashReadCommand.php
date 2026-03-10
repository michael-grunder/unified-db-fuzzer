<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Fuzz\Command;

use Mgrunder\Fuzz\Fuzz\CommandFlags;
use Mgrunder\Fuzz\Fuzz\FuzzContext;
use Mgrunder\Fuzz\Fuzz\RedisDataType;
use Mgrunder\Fuzz\Fuzz\RedisOperation;

abstract class AbstractHashReadCommand extends RedisCommand
{
    public function type(): ?RedisDataType
    {
        return RedisDataType::Hash;
    }

    public function flags(): int
    {
        return CommandFlags::READ;
    }

    public function createOperation(FuzzContext $context): RedisOperation
    {
        $key = $context->randomKey($this->type());

        return new RedisOperation(
            $this->name(),
            [$key, $context->randomFields()],
            $key,
        );
    }
}
