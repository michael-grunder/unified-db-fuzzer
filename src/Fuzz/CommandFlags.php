<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Fuzz;

final class CommandFlags
{
    public const READ = 1;
    public const WRITE = 2;
    public const FLUSH = 4;

    private function __construct()
    {
    }
}
