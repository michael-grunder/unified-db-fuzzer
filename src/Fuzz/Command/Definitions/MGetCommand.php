<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Fuzz\Command\Definitions;

use Mgrunder\Fuzz\Fuzz\Command\AbstractMultiKeyCommand;
use Mgrunder\Fuzz\Fuzz\RedisDataType;

final class MGetCommand extends AbstractMultiKeyCommand
{
    public function name(): string
    {
        return 'mget';
    }

    public function type(): RedisDataType
    {
        return RedisDataType::String;
    }
}
