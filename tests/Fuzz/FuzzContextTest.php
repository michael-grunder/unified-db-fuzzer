<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Tests\Fuzz;

use Mgrunder\Fuzz\Fuzz\FuzzContext;
use Mgrunder\Fuzz\Fuzz\RedisDataType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FuzzContextTest extends TestCase
{
    #[Test]
    public function it_generates_deterministic_key_and_field_sequences_for_the_same_seed(): void
    {
        $left = new FuzzContext(10, 5, 1234);
        $right = new FuzzContext(10, 5, 1234);

        self::assertSame($left->randomKey(RedisDataType::String), $right->randomKey(RedisDataType::String));
        self::assertSame($left->randomKeys(RedisDataType::Hash), $right->randomKeys(RedisDataType::Hash));
        self::assertSame($left->randomFields(), $right->randomFields());
        self::assertSame(array_keys($left->randomHash()), array_keys($right->randomHash()));
    }
}
