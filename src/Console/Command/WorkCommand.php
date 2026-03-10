<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Console\Command;

use InvalidArgumentException;
use Mgrunder\Fuzz\Fuzz\AgeUnit;
use Mgrunder\Fuzz\Fuzz\RedisDataType;
use Mgrunder\Fuzz\Runtime\ConsoleLogger;
use Mgrunder\Fuzz\Runtime\WorkApplication;
use Mgrunder\Fuzz\Runtime\WorkOptions;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ValueError;

final class WorkCommand extends Command
{
    public const NAME = 'work';

    public function __construct(
        private readonly WorkApplication $application,
    ) {
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Run the Redis Relay fuzz worker harness.')
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'Redis host.', 'localhost')
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'Redis port.', '6379')
            ->addOption('timeout', null, InputOption::VALUE_REQUIRED, 'Redis connection timeout in seconds.')
            ->addOption('read-timeout', null, InputOption::VALUE_REQUIRED, 'Redis read timeout in seconds.')
            ->addOption('keys', null, InputOption::VALUE_REQUIRED, 'How many distinct keys to target.', '100')
            ->addOption('mems', null, InputOption::VALUE_REQUIRED, 'How many hash fields or zset members to target.', '10')
            ->addOption('workers', null, InputOption::VALUE_REQUIRED, 'Worker count. Use 0 to disable forking.', '4')
            ->addOption('ops', null, InputOption::VALUE_REQUIRED, 'Operations per worker. Use -1 to run forever.', '1000')
            ->addOption('interval', null, InputOption::VALUE_REQUIRED, 'Progress report interval in seconds.', '1.0')
            ->addOption(
                'age-unit',
                null,
                InputOption::VALUE_REQUIRED,
                'Age unit for stale-value reporting.',
                AgeUnit::Microseconds->value,
            )
            ->addOption(
                'cmd-types',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Comma-separated Redis data type filters.',
            )
            ->addOption('seed', null, InputOption::VALUE_REQUIRED, 'Base RNG seed. Defaults to a random seed.')
            ->addOption('flush', null, InputOption::VALUE_NONE, 'Flush the database before starting workers.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $options = new WorkOptions(
                host: (string) $input->getOption('host'),
                port: $this->toInt($input->getOption('port'), '--port'),
                timeout: $this->parseOptionalFloat($input->getOption('timeout'), '--timeout'),
                readTimeout: $this->parseOptionalFloat($input->getOption('read-timeout'), '--read-timeout'),
                keys: $this->toInt($input->getOption('keys'), '--keys'),
                members: $this->toInt($input->getOption('mems'), '--mems'),
                workers: $this->toInt($input->getOption('workers'), '--workers'),
                ops: $this->toInt($input->getOption('ops'), '--ops'),
                reportInterval: $this->toFloat($input->getOption('interval'), '--interval'),
                ageUnit: AgeUnit::from((string) $input->getOption('age-unit')),
                commandTypes: $this->parseCommandTypes($input->getOption('cmd-types')),
                flush: (bool) $input->getOption('flush'),
                seed: $this->parseSeed($input->getOption('seed')),
            );
        } catch (InvalidArgumentException|ValueError $exception) {
            $errorOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            $errorOutput->writeln($exception->getMessage());

            return Command::FAILURE;
        }

        $errorOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        return $this->application->run(
            $options,
            new ConsoleLogger($errorOutput),
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
     * @param mixed $rawTypes
     * @return list<RedisDataType>
     */
    private function parseCommandTypes(mixed $rawTypes): array
    {
        if (!is_array($rawTypes)) {
            return [];
        }

        $values = [];

        foreach ($rawTypes as $part) {
            if (!is_string($part)) {
                continue;
            }

            foreach (explode(',', $part) as $candidate) {
                $candidate = trim($candidate);
                if ($candidate === '') {
                    continue;
                }

                $values[$candidate] = RedisDataType::from($candidate);
            }
        }

        ksort($values);

        return array_values($values);
    }
}
