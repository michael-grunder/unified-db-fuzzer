<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Fuzz\Command;

use Mgrunder\Fuzz\Fuzz\FuzzContext;
use Mgrunder\Fuzz\Fuzz\RedisOperation;

abstract class AbstractSingleKeyCommand extends RedisCommand
{
    public function createOperation(FuzzContext $context): RedisOperation
    {
        $key = $context->randomKey($this->type(), $this->flags());

        return new RedisOperation(
            $this->name(),
            [$key],
            $key,
        );
    }
}
