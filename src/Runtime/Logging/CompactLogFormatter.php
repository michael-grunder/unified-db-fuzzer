<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime\Logging;

use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;

use function array_map;
use function implode;
use function microtime;
use function sprintf;

final class CompactLogFormatter implements FormatterInterface
{
    public function format(LogRecord $record): string
    {
        return sprintf(
            '[%.6f %s] %s' . PHP_EOL,
            microtime(true),
            $record->level->getName(),
            $record->message,
        );
    }

    /**
     * @param array<LogRecord> $records
     */
    public function formatBatch(array $records): string
    {
        return implode('', array_map(
            $this->format(...),
            $records,
        ));
    }
}
