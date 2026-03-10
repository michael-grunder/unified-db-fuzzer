<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime;

use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;

use function bin2hex;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function is_array;
use function is_dir;
use function json_decode;
use function json_encode;
use function mkdir;
use function random_bytes;
use function rename;
use function rmdir;
use function sprintf;
use function sys_get_temp_dir;
use function unlink;

final class StatusPageWorkerLogger implements WorkerLogger
{
    private readonly string $directory;
    private readonly StatusPageRenderer $renderer;
    private bool $cursorHidden = false;

    public function __construct(
        private readonly OutputInterface $output,
        ?StatusPageRenderer $renderer = null,
    ) {
        $this->renderer = $renderer ?? new StatusPageRenderer();
        $this->directory = sys_get_temp_dir() . '/fuzz-status-' . bin2hex(random_bytes(8));

        if (!mkdir($concurrentDirectory = $this->directory, 0777, true) && !is_dir($concurrentDirectory)) {
            throw new RuntimeException(sprintf('Unable to create status directory "%s".', $this->directory));
        }
    }

    public function log(string $message): void
    {
    }

    public function updateWorkerStatus(WorkerStatusSnapshot $snapshot): void
    {
        $path = $this->snapshotPath($snapshot->workerIndex);
        $tmpPath = $path . '.tmp';
        $json = json_encode($snapshot->toArray(), JSON_THROW_ON_ERROR);
        file_put_contents($tmpPath, $json);
        rename($tmpPath, $path);
    }

    public function render(WorkOptions $options, int $expectedWorkers, float $startedAt): void
    {
        if (!$this->cursorHidden) {
            $this->output->write("\033[?25l");
            $this->cursorHidden = true;
        }

        $screen = $this->renderer->render($options, $this->snapshots(), $expectedWorkers, $startedAt);
        $this->output->write("\033[H\033[2J" . $screen);
    }

    public function finish(WorkOptions $options, int $expectedWorkers, float $startedAt): void
    {
        $this->render($options, $expectedWorkers, $startedAt);

        if ($this->cursorHidden) {
            $this->output->write("\n\033[?25h");
            $this->cursorHidden = false;
        }

        foreach (glob($this->directory . '/*.json') ?: [] as $file) {
            unlink($file);
        }

        @rmdir($this->directory);
    }

    /**
     * @return list<WorkerStatusSnapshot>
     */
    private function snapshots(): array
    {
        $snapshots = [];

        foreach (glob($this->directory . '/*.json') ?: [] as $file) {
            $raw = file_get_contents($file);
            if ($raw === false) {
                continue;
            }

            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                continue;
            }

            $snapshots[] = WorkerStatusSnapshot::fromArray($decoded);
        }

        return $snapshots;
    }

    private function snapshotPath(int $workerIndex): string
    {
        return sprintf('%s/worker-%02d.json', $this->directory, $workerIndex);
    }
}
