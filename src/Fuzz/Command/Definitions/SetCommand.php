<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Fuzz\Command\Definitions;

use Mgrunder\Fuzz\Fuzz\Command\AbstractSingleKeyValueCommand;
use Mgrunder\Fuzz\Fuzz\RedisDataType;

final class SetCommand extends AbstractSingleKeyValueCommand
{
    public function supportsStalenessWrite(): bool
    {
        return true;
    }

    public function name(): string
    {
        return 'set';
    }

    public function type(): RedisDataType
    {
        return RedisDataType::String;
    }
}
