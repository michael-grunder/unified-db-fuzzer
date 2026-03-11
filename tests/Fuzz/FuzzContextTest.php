<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Tests\Fuzz;

use Mgrunder\Fuzz\Fuzz\CommandFlags;
use Mgrunder\Fuzz\Fuzz\FuzzContext;
use Mgrunder\Fuzz\Fuzz\RedisDataType;
use Mgrunder\Fuzz\Fuzz\WorkerKeyspace;
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

    #[Test]
    public function it_scopes_write_keys_to_the_current_worker_when_worker_keyspace_is_enabled(): void
    {
        $context = new FuzzContext(10, 5, 1234, WorkerKeyspace::forWorker(1, 3));

        self::assertStringStartsWith('worker-1:string:', $context->randomKey(RedisDataType::String, CommandFlags::WRITE));

        foreach (array_keys($context->randomKeyValueMap(RedisDataType::Hash, CommandFlags::WRITE)) as $key) {
            self::assertStringStartsWith('worker-1:hash:', $key);
        }
    }

    #[Test]
    public function it_allows_reads_to_target_any_worker_namespace_when_worker_keyspace_is_enabled(): void
    {
        $context = new FuzzContext(10, 5, 1234, WorkerKeyspace::forWorker(1, 3));

        self::assertSame('worker-0:string:0', $context->randomKey(RedisDataType::String, CommandFlags::READ));
        self::assertSame(
            [
                'worker-2:hash:3',
                'worker-1:hash:9',
                'worker-0:hash:6',
                'worker-0:hash:0',
                'worker-1:hash:10',
                'worker-1:hash:2',
                'worker-0:hash:9',
            ],
            $context->randomKeys(RedisDataType::Hash, CommandFlags::READ),
        );
    }
}
