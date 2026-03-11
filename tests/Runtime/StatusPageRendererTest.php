<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Tests\Runtime;

use Mgrunder\Fuzz\Fuzz\AgeUnit;
use Mgrunder\Fuzz\Runtime\StatusPageRenderer;
use Mgrunder\Fuzz\Runtime\WorkerStatusSnapshot;
use Mgrunder\Fuzz\Runtime\WorkOptions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StatusPageRendererTest extends TestCase
{
    #[Test]
    public function it_renders_workers_and_the_global_staleness_leaderboard(): void
    {
        $renderer = new StatusPageRenderer();
        $options = new WorkOptions(
            host: 'localhost',
            port: 6379,
            timeout: null,
            readTimeout: null,
            keys: 16,
            members: 4,
            workers: 2,
            ops: 100,
            reportInterval: 1.0,
            ageUnit: AgeUnit::Milliseconds,
            seed: 1234,
            afl: true,
            staleness: true,
        );

        $screen = $renderer->render($options, [
            new WorkerStatusSnapshot(
                workerIndex: 0,
                pid: 1001,
                mode: 'staleness',
                state: 'running',
                done: 25,
                targetOps: 100,
                startedAt: microtime(true) - 3.0,
                updatedAt: microtime(true) - 0.1,
                opsPerSecond: 120.0,
                metrics: [
                    'reads' => 8,
                    'writes' => 10,
                    'deletes' => 2,
                    'stale_reads' => 3,
                    'persistent_stale' => 1,
                    'regressions' => 1,
                    'hard_failures' => 1,
                ],
                topKeys: [[
                    'key' => 'fuzz:string:3',
                    'classification' => 'stale_regression',
                    'steps_behind' => 5,
                    'age' => '1.250ms',
                    'regression' => true,
                    'consecutive_stale' => 4,
                ]],
                currentTopKeys: [[
                    'key' => 'fuzz:string:3',
                    'classification' => 'stale_regression',
                    'steps_behind' => 5,
                    'age' => '1.250ms',
                    'regression' => true,
                    'consecutive_stale' => 4,
                ]],
            ),
            new WorkerStatusSnapshot(
                workerIndex: 1,
                pid: 1002,
                mode: 'staleness',
                state: 'finished',
                done: 100,
                targetOps: 100,
                startedAt: microtime(true) - 3.0,
                updatedAt: microtime(true) - 0.2,
                opsPerSecond: 90.0,
                metrics: [
                    'reads' => 40,
                    'writes' => 35,
                    'deletes' => 5,
                    'stale_reads' => 2,
                    'persistent_stale' => 0,
                    'regressions' => 0,
                    'hard_failures' => 0,
                ],
                topKeys: [[
                    'key' => 'fuzz:string:9',
                    'classification' => 'persistent_stale',
                    'steps_behind' => 3,
                    'age' => '0.750ms',
                    'regression' => false,
                    'consecutive_stale' => 2,
                ]],
                currentTopKeys: [],
            ),
        ], 2, microtime(true) - 3.0);

        self::assertStringContainsString('mode=staleness', $screen);
        self::assertStringContainsString('workers:', $screen);
        self::assertStringContainsString('w00 running', $screen);
        self::assertStringContainsString('w01 finished', $screen);
        self::assertStringContainsString('top stale keys (still stale):', $screen);
        self::assertStringContainsString('worst stale keys seen:', $screen);
        self::assertStringContainsString('fuzz:string:3', $screen);
        self::assertStringContainsString('stale_regression', $screen);
        self::assertStringContainsString('seen=4', $screen);
    }
}
