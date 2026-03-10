<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime;

interface ClientKillApplication
{
    public function run(ClientKillOptions $options, ClientKillLogger $logger): int;
}
