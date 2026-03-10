<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime;

interface StatsProvider
{
    public function summary(): string;
}
