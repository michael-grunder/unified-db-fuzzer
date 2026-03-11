<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Fuzz\Command;

use Mgrunder\Fuzz\Fuzz\CommandFlags;
use Mgrunder\Fuzz\Fuzz\FuzzContext;
use Mgrunder\Fuzz\Fuzz\RedisDataType;
use Mgrunder\Fuzz\Fuzz\RedisOperation;

abstract class AbstractSortedSetWriteCommand extends RedisCommand
{
    public function type(): ?RedisDataType
    {
        return RedisDataType::ZSet;
    }

    public function flags(): int
    {
        return CommandFlags::WRITE;
    }

    public function createOperation(FuzzContext $context): RedisOperation
    {
        $key = $context->randomKey($this->type(), $this->flags());
        $arguments = [$key];

        foreach ($context->randomSortedSetEntries() as $entry) {
            $arguments[] = $entry['score'];
            $arguments[] = $entry['member'];
        }

        return new RedisOperation(
            $this->name(),
            $arguments,
            $key,
        );
    }
}
