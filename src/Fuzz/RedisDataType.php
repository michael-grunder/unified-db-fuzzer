<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Fuzz;

enum RedisDataType: string
{
    case String = 'string';
    case Hash = 'hash';
    case List = 'list';
    case ZSet = 'zset';
}
