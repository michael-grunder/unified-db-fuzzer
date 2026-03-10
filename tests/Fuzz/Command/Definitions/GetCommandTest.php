<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Tests\Fuzz\Command\Definitions;

use Mgrunder\Fuzz\Fuzz\Command\Definitions\GetCommand;
use Mgrunder\Fuzz\Fuzz\FreshnessEnvelope;
use Mgrunder\Fuzz\Fuzz\RedisOperation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetCommandTest extends TestCase
{
    #[Test]
    public function it_reports_transient_staleness_for_older_cached_values(): void
    {
        $command = new GetCommand();
        $operation = new RedisOperation('get', ['fuzz:string:1'], 'fuzz:string:1');

        $cached = FreshnessEnvelope::encode('fuzz:string:1', 10, 100, 1, 'worker-1', 1, 'cached');
        $truth = FreshnessEnvelope::encode('fuzz:string:1', 12, 150, 2, 'worker-2', 2, 'truth');

        $observation = $command->observeStaleness($operation, $cached, $truth, 200);

        self::assertNotNull($observation);
        self::assertSame('transient_stale', $observation->classification);
        self::assertSame(2, $observation->stepsBehind);
        self::assertSame(50, $observation->ageNs);
    }

    #[Test]
    public function it_reports_delete_mismatches_when_cache_still_has_a_value(): void
    {
        $command = new GetCommand();
        $operation = new RedisOperation('get', ['fuzz:string:2'], 'fuzz:string:2');
        $cached = FreshnessEnvelope::encode('fuzz:string:2', 9, 100, 1, 'worker-1', 1, 'cached');

        $observation = $command->observeStaleness($operation, $cached, false, 200);

        self::assertNotNull($observation);
        self::assertSame('stale_exists_after_delete', $observation->classification);
        self::assertTrue($observation->cachedExists);
        self::assertFalse($observation->truthExists);
    }
}
