<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Fuzz;

use Random\Engine\Mt19937;
use Random\Randomizer;

final class FuzzContext
{
    private readonly Randomizer $randomizer;

    public function __construct(
        public readonly int $keys,
        public readonly int $members,
        public readonly int $seed,
    ) {
        $this->randomizer = new Randomizer(new Mt19937($seed));
    }

    public function randomKey(?RedisDataType $type): string
    {
        $type ??= $this->randomDataType();

        return sprintf('%s:%d', $type->value, $this->randomInt(0, $this->keys));
    }

    /**
     * @return list<string>
     */
    public function randomKeys(RedisDataType $type): array
    {
        $count = $this->randomInt(1, $this->keys);
        $keys = [];

        for ($i = 0; $i < $count; $i++) {
            $keys[] = sprintf('%s:%d', $type->value, $this->randomInt(0, $this->keys));
        }

        return $keys;
    }

    /**
     * @return array<string, string>
     */
    public function randomKeyValueMap(RedisDataType $type): array
    {
        $count = $this->randomInt(1, $this->keys);
        $values = [];

        for ($i = 0; $i < $count; $i++) {
            $values[sprintf('%s:%d', $type->value, $this->randomInt(0, $this->keys))] = $this->newPayload();
        }

        return $values;
    }

    public function randomField(): string
    {
        return sprintf('field:%d', $this->randomInt(0, $this->members));
    }

    /**
     * @return list<string>
     */
    public function randomFields(): array
    {
        $count = $this->randomInt(1, $this->members);
        $fields = [];

        for ($i = 0; $i < $count; $i++) {
            $fields[] = $this->randomField();
        }

        return $fields;
    }

    /**
     * @return array<string, string>
     */
    public function randomHash(): array
    {
        $count = $this->randomInt(1, $this->members);
        $hash = [];

        for ($i = 0; $i < $count; $i++) {
            $hash[sprintf('field:%d', $this->randomInt(0, $this->members))] = $this->newPayload();
        }

        return $hash;
    }

    /**
     * @return list<array{score: int, member: string}>
     */
    public function randomSortedSetEntries(): array
    {
        $count = $this->randomInt(1, $this->members);
        $entries = [];

        for ($i = 0; $i < $count; $i++) {
            $entries[] = [
                'score' => hrtime(true),
                'member' => sprintf('member:%d', $this->randomInt(0, $this->members)),
            ];
        }

        return $entries;
    }

    public function randomIndex(int $max): int
    {
        return $this->randomizer->getInt(0, $max);
    }

    public function newPayload(): string
    {
        return (string) hrtime(true);
    }

    private function randomDataType(): RedisDataType
    {
        $cases = RedisDataType::cases();

        return $cases[$this->randomIndex(count($cases) - 1)];
    }

    private function randomInt(int $min, int $max): int
    {
        return $this->randomizer->getInt($min, $max);
    }
}
