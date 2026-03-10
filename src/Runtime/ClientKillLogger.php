<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime;

interface ClientKillLogger
{
    public function logProgress(ClientKillProgress $progress): void;
}
