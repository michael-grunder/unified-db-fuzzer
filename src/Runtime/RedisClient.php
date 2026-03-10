<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime;

use Mgrunder\Fuzz\Fuzz\RedisOperation;

interface RedisClient
{
    public function execute(RedisOperation $operation): mixed;

    public function flushDatabase(): void;
}
