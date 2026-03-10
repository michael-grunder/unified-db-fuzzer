<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Console\Command;

use InvalidArgumentException;
use Mgrunder\Fuzz\Fuzz\AgeUnit;
use Mgrunder\Fuzz\Fuzz\RedisDataType;
use Mgrunder\Fuzz\Runtime\StalenessThresholds;
use Mgrunder\Fuzz\Runtime\WorkApplication;
use Mgrunder\Fuzz\Runtime\WorkOptions;
use Mgrunder\Fuzz\Runtime\WorkerLoggerFactory;
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
        private readonly WorkerLoggerFactory $workerLoggerFactory = new WorkerLoggerFactory(),
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
            ->addOption('afl', null, InputOption::VALUE_NONE, 'Show an AFL-like full-screen status page instead of dense logs.')
            ->addOption('log-file', null, InputOption::VALUE_REQUIRED, 'Write worker logs to this file instead of stderr.')
            ->addOption('flush', null, InputOption::VALUE_NONE, 'Flush the database before starting workers.')
            ->addOption('staleness', null, InputOption::VALUE_NONE, 'Run the shared-cache staleness regression fuzzer.')
            ->addOption('stale-persistent-checks', null, InputOption::VALUE_REQUIRED, 'Consecutive stale rechecks before classifying as persistent.', '3')
            ->addOption('stale-severe-steps', null, InputOption::VALUE_REQUIRED, 'Steps-behind threshold for suspicious stale observations.', '3')
            ->addOption('stale-hard-steps', null, InputOption::VALUE_REQUIRED, 'Steps-behind threshold for hard failure.', '8')
            ->addOption('stale-stuck-repeats', null, InputOption::VALUE_REQUIRED, 'Same stale version repeat threshold for hard failure.', '5')
            ->addOption('stale-top', null, InputOption::VALUE_REQUIRED, 'Top-N suspicious stale events to retain per worker.', '10')
            ->addOption('stale-delays', null, InputOption::VALUE_REQUIRED, 'Comma-separated recheck delay buckets in microseconds.', '0,100,500,1000,5000,20000');
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
                afl: (bool) $input->getOption('afl'),
                staleness: (bool) $input->getOption('staleness'),
                stalenessThresholds: new StalenessThresholds(
                    persistentChecks: $this->toInt($input->getOption('stale-persistent-checks'), '--stale-persistent-checks'),
                    severeSteps: $this->toInt($input->getOption('stale-severe-steps'), '--stale-severe-steps'),
                    hardFailureSteps: $this->toInt($input->getOption('stale-hard-steps'), '--stale-hard-steps'),
                    stuckRepeats: $this->toInt($input->getOption('stale-stuck-repeats'), '--stale-stuck-repeats'),
                    topN: $this->toInt($input->getOption('stale-top'), '--stale-top'),
                    delayBucketsUs: $this->parseDelayBuckets($input->getOption('stale-delays')),
                ),
            );
        } catch (InvalidArgumentException|ValueError $exception) {
            $errorOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            $errorOutput->writeln($exception->getMessage());

            return Command::FAILURE;
        }

        $errorOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        return $this->application->run(
            $options,
            $options->afl
                ? $this->workerLoggerFactory->createStatusPage($errorOutput)
                : $this->workerLoggerFactory->create(
                    $errorOutput,
                    $this->parseOptionalString($input->getOption('log-file')),
                ),
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

    private function parseOptionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            throw new InvalidArgumentException('Invalid string option value.');
        }

        return (string) $value;
    }

    /**
     * @return list<int>
     */
    private function parseDelayBuckets(mixed $value): array
    {
        if (!is_scalar($value)) {
            throw new InvalidArgumentException('Invalid --stale-delays value.');
        }

        $delays = [];
        foreach (explode(',', (string) $value) as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }

            $delays[] = $this->toInt($candidate, '--stale-delays');
        }

        return $delays;
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
