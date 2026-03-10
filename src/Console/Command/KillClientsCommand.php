<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Console\Command;

use InvalidArgumentException;
use Mgrunder\Fuzz\Runtime\ClientKillApplication;
use Mgrunder\Fuzz\Runtime\ClientKillConsoleLogger;
use Mgrunder\Fuzz\Runtime\ClientKillOptions;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function count;
use function explode;
use function is_numeric;
use function is_scalar;
use function round;
use function trim;

final class KillClientsCommand extends Command
{
    public const NAME = 'kill-clients';

    public function __construct(
        private readonly ClientKillApplication $application,
    ) {
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Continuously kill one or more Redis client connections in a separate session.')
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'Redis host.', 'localhost')
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'Redis port.', '6379')
            ->addOption('timeout', null, InputOption::VALUE_REQUIRED, 'Redis connection timeout in seconds.')
            ->addOption('read-timeout', null, InputOption::VALUE_REQUIRED, 'Redis read timeout in seconds.')
            ->addOption(
                'sleep',
                null,
                InputOption::VALUE_REQUIRED,
                'Fixed sleep in seconds or a min-max range, for example 0.01-0.9.',
                '1.0',
            )
            ->addOption(
                'kills',
                null,
                InputOption::VALUE_REQUIRED,
                'How many client ids to kill per iteration. Accepts a fixed count or min-max range.',
                '1',
            )
            ->addOption('seed', null, InputOption::VALUE_REQUIRED, 'Base RNG seed for reproducible sleep and kill selection.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            [$minSleepMicros, $maxSleepMicros] = $this->parseSleepRange((string) $input->getOption('sleep'));
            [$minKills, $maxKills] = $this->parseIntegerRange((string) $input->getOption('kills'), '--kills');

            $options = new ClientKillOptions(
                host: (string) $input->getOption('host'),
                port: $this->toInt($input->getOption('port'), '--port'),
                timeout: $this->parseOptionalFloat($input->getOption('timeout'), '--timeout'),
                readTimeout: $this->parseOptionalFloat($input->getOption('read-timeout'), '--read-timeout'),
                minSleepMicros: $minSleepMicros,
                maxSleepMicros: $maxSleepMicros,
                minKillsPerIteration: $minKills,
                maxKillsPerIteration: $maxKills,
                seed: $this->parseSeed($input->getOption('seed')),
            );
        } catch (InvalidArgumentException $exception) {
            $errorOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            $errorOutput->writeln($exception->getMessage());

            return Command::FAILURE;
        }

        return $this->application->run(
            $options,
            new ClientKillConsoleLogger($output),
        );
    }

    private function toInt(mixed $value, string $optionName): int
    {
        if (!is_scalar($value) || !is_numeric((string) $value)) {
            throw new InvalidArgumentException(sprintf('Invalid %s value.', $optionName));
        }

        return (int) $value;
    }

    private function toFloat(mixed $value, string $optionName): float
    {
        if (!is_scalar($value) || !is_numeric((string) $value)) {
            throw new InvalidArgumentException(sprintf('Invalid %s value.', $optionName));
        }

        return (float) $value;
    }

    private function parseSeed(mixed $rawSeed): ?int
    {
        if ($rawSeed === null) {
            return null;
        }

        return $this->toInt($rawSeed, '--seed');
    }

    private function parseOptionalFloat(mixed $value, string $optionName): ?float
    {
        if ($value === null) {
            return null;
        }

        return $this->toFloat($value, $optionName);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function parseSleepRange(string $spec): array
    {
        $parts = explode('-', $spec, 2);

        if (count($parts) === 1) {
            $micros = $this->secondsToMicros($parts[0]);

            return [$micros, $micros];
        }

        $min = $this->secondsToMicros($parts[0]);
        $max = $this->secondsToMicros($parts[1]);

        if ($max < $min) {
            throw new InvalidArgumentException(sprintf('Invalid --sleep value "%s": max must be >= min.', $spec));
        }

        return [$min, $max];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function parseIntegerRange(string $spec, string $optionName): array
    {
        $parts = explode('-', $spec, 2);

        if (count($parts) === 1) {
            $value = $this->parseNonNegativeInt($parts[0], $optionName);

            return [$value, $value];
        }

        $min = $this->parseNonNegativeInt($parts[0], $optionName);
        $max = $this->parseNonNegativeInt($parts[1], $optionName);

        if ($max < $min) {
            throw new InvalidArgumentException(sprintf('Invalid %s value "%s": max must be >= min.', $optionName, $spec));
        }

        return [$min, $max];
    }

    private function parseNonNegativeInt(string $value, string $optionName): int
    {
        $value = trim($value);

        if ($value === '' || !is_numeric($value)) {
            throw new InvalidArgumentException(sprintf('Invalid %s value "%s".', $optionName, $value));
        }

        $parsed = (int) $value;

        if ((string) $parsed !== $value || $parsed < 0) {
            throw new InvalidArgumentException(sprintf('Invalid %s value "%s".', $optionName, $value));
        }

        return $parsed;
    }

    private function secondsToMicros(string $value): int
    {
        $value = trim($value);

        if ($value === '' || !is_numeric($value)) {
            throw new InvalidArgumentException(sprintf('Invalid --sleep value "%s".', $value));
        }

        $seconds = (float) $value;

        if ($seconds < 0) {
            throw new InvalidArgumentException(sprintf('Invalid --sleep value "%s": must be >= 0.', $value));
        }

        return (int) round($seconds * 1_000_000);
    }
}
