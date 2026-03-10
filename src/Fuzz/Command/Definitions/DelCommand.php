<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Fuzz\Command\Definitions;

use Mgrunder\Fuzz\Fuzz\Command\AbstractSingleKeyCommand;
use Mgrunder\Fuzz\Fuzz\CommandFlags;
use Mgrunder\Fuzz\Fuzz\RedisDataType;

final class DelCommand extends AbstractSingleKeyCommand
{
    public function name(): string
    {
        return 'del';
    }

    public function type(): ?RedisDataType
    {
        return null;
    }

    public function flags(): int
    {
        return CommandFlags::WRITE;
    }
}
