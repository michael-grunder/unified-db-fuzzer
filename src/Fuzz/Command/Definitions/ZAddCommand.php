<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Fuzz\Command\Definitions;

use Mgrunder\Fuzz\Fuzz\Command\AbstractSortedSetWriteCommand;

final class ZAddCommand extends AbstractSortedSetWriteCommand
{
    public function name(): string
    {
        return 'zadd';
    }
}
