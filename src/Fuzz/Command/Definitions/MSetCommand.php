<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Fuzz\Command\Definitions;

use Mgrunder\Fuzz\Fuzz\Command\AbstractMapWriteCommand;
use Mgrunder\Fuzz\Fuzz\RedisDataType;

final class MSetCommand extends AbstractMapWriteCommand
{
    public function name(): string
    {
        return 'mset';
    }

    public function type(): RedisDataType
    {
        return RedisDataType::String;
    }
}
