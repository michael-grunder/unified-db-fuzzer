<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime;

use InvalidArgumentException;
use Mgrunder\Fuzz\Runtime\Logging\CompactLogFormatter;
use Mgrunder\Fuzz\Runtime\Logging\OutputInterfaceHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Console\Output\OutputInterface;

use function dirname;
use function fclose;
use function fopen;
use function is_dir;
use function is_string;
use function sprintf;
use function trim;

final class WorkerLoggerFactory
{
    public function create(OutputInterface $output, ?string $logFile = null): WorkerLogger
    {
        $logger = new Logger('fuzz');
        $handler = $logFile === null
            ? new OutputInterfaceHandler($output)
            : new StreamHandler($this->validateLogFile($logFile));
        $handler->setFormatter(new CompactLogFormatter());
        $logger->pushHandler($handler);

        return new MonologWorkerLogger($logger);
    }

    private function validateLogFile(string $logFile): string
    {
        $logFile = trim($logFile);

        if ($logFile === '') {
            throw new InvalidArgumentException('Invalid --log-file value.');
        }

        $directory = dirname($logFile);
        if (!is_dir($directory)) {
            throw new InvalidArgumentException(sprintf('Invalid --log-file value "%s": directory does not exist.', $logFile));
        }

        $handle = @fopen($logFile, 'ab');
        if (!is_resource($handle)) {
            throw new InvalidArgumentException(sprintf('Invalid --log-file value "%s": file is not writable.', $logFile));
        }

        fclose($handle);

        return $logFile;
    }
}
