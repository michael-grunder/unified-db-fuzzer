<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Fuzz\Command;

use Mgrunder\Fuzz\Fuzz\CommandFlags;
use Mgrunder\Fuzz\Fuzz\FuzzContext;
use Mgrunder\Fuzz\Fuzz\RedisDataType;
use Mgrunder\Fuzz\Fuzz\RedisOperation;

abstract class AbstractMapWriteCommand extends RedisCommand
{
    public function flags(): int
    {
        return CommandFlags::WRITE;
    }

    public function createOperation(FuzzContext $context): RedisOperation
    {
        return new RedisOperation(
            $this->name(),
            [$context->randomKeyValueMap($this->type() ?? RedisDataType::String, $this->flags())],
        );
    }
}
