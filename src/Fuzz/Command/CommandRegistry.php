<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Fuzz\Command;

use InvalidArgumentException;
use Mgrunder\Fuzz\Fuzz\Command\Definitions\DelCommand;
use Mgrunder\Fuzz\Fuzz\Command\Definitions\GetCommand;
use Mgrunder\Fuzz\Fuzz\Command\Definitions\HGetAllCommand;
use Mgrunder\Fuzz\Fuzz\Command\Definitions\HmGetCommand;
use Mgrunder\Fuzz\Fuzz\Command\Definitions\HmSetCommand;
use Mgrunder\Fuzz\Fuzz\Command\Definitions\MGetCommand;
use Mgrunder\Fuzz\Fuzz\Command\Definitions\MSetCommand;
use Mgrunder\Fuzz\Fuzz\Command\Definitions\SetCommand;
use Mgrunder\Fuzz\Fuzz\Command\Definitions\ZAddCommand;
use Mgrunder\Fuzz\Fuzz\Command\Definitions\ZRangeCommand;
use Mgrunder\Fuzz\Fuzz\RedisDataType;

final class CommandRegistry
{
    /**
     * @param list<RedisCommand> $commands
     */
    public function __construct(
        private readonly array $commands,
    ) {
    }

    public static function default(): self
    {
        return new self([
            new DelCommand(),
            new GetCommand(),
            new HGetAllCommand(),
            new HmGetCommand(),
            new HmSetCommand(),
            new MGetCommand(),
            new MSetCommand(),
            new SetCommand(),
            new ZAddCommand(),
            new ZRangeCommand(),
        ]);
    }

    /**
     * @param list<RedisDataType> $types
     * @return list<RedisCommand>
     */
    public function filterByTypes(array $types): array
    {
        if ($types === []) {
            return $this->commands;
        }

        $allowed = [];
        foreach ($types as $type) {
            $allowed[$type->value] = true;
        }

        $filtered = array_values(
            array_filter(
                $this->commands,
                static fn (RedisCommand $command): bool => $command->type() !== null
                    && isset($allowed[$command->type()->value]),
            ),
        );

        if ($filtered === []) {
            throw new InvalidArgumentException('No commands available for selected --cmd-types filter.');
        }

        return $filtered;
    }
}
