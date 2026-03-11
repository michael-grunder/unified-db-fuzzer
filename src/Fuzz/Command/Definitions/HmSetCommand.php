<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Fuzz\Command\Definitions;

use Mgrunder\Fuzz\Fuzz\Command\RedisCommand;
use Mgrunder\Fuzz\Fuzz\CommandFlags;
use Mgrunder\Fuzz\Fuzz\FuzzContext;
use Mgrunder\Fuzz\Fuzz\RedisDataType;
use Mgrunder\Fuzz\Fuzz\RedisOperation;

final class HmSetCommand extends RedisCommand
{
    public function name(): string
    {
        return 'hmset';
    }

    public function type(): RedisDataType
    {
        return RedisDataType::Hash;
    }

    public function flags(): int
    {
        return CommandFlags::WRITE;
    }

    public function createOperation(FuzzContext $context): RedisOperation
    {
        $key = $context->randomKey($this->type(), $this->flags());

        return new RedisOperation(
            $this->name(),
            [$key, $context->randomHash()],
            $key,
        );
    }
}
