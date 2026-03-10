<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Tests\Fuzz;

use Mgrunder\Fuzz\Fuzz\FreshnessEnvelope;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FreshnessEnvelopeTest extends TestCase
{
    #[Test]
    public function it_round_trips_a_freshness_envelope(): void
    {
        $raw = FreshnessEnvelope::encode(
            key: 'fuzz:string:1',
            version: 42,
            writtenNs: 123_456,
            writerPid: 999,
            writerId: 'worker-999',
            writerSeq: 7,
            payload: 'payload',
        );

        $envelope = FreshnessEnvelope::decode($raw);

        self::assertNotNull($envelope);
        self::assertSame('fuzz:string:1', $envelope->key);
        self::assertSame(42, $envelope->version);
        self::assertSame(123_456, $envelope->writtenNs);
        self::assertSame(999, $envelope->writerPid);
        self::assertSame('worker-999', $envelope->writerId);
        self::assertSame(7, $envelope->writerSeq);
        self::assertSame('payload', $envelope->payload);
    }

    #[Test]
    public function it_rejects_malformed_payloads(): void
    {
        self::assertNull(FreshnessEnvelope::decode('not-json'));
        self::assertNull(FreshnessEnvelope::decode('{"version":1}'));
        self::assertNull(FreshnessEnvelope::decode(false));
    }
}
