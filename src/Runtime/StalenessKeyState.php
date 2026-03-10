<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime;

final class StalenessKeyState
{
    public ?int $maxCachedVersionSeen = null;
    public ?int $maxTruthVersionSeen = null;
    public ?int $lastStaleCachedVersion = null;
    public int $sameStaleVersionCount = 0;
}
