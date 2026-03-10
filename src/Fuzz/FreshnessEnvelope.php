<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Fuzz;

use JsonException;

final readonly class FreshnessEnvelope
{
    public function __construct(
        public string $key,
        public int $version,
        public int $writtenNs,
        public int $writerPid,
        public string $writerId,
        public int $writerSeq,
        public string $payload,
        public string $raw,
    ) {
    }

    public static function encode(
        string $key,
        int $version,
        int $writtenNs,
        int $writerPid,
        string $writerId,
        int $writerSeq,
        string $payload,
    ): string {
        try {
            return json_encode([
                'key' => $key,
                'version' => $version,
                'written_ns' => $writtenNs,
                'writer_pid' => $writerPid,
                'writer_id' => $writerId,
                'writer_seq' => $writerSeq,
                'payload' => $payload,
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \RuntimeException('Failed to encode freshness envelope.', 0, $exception);
        }
    }

    public static function decode(mixed $value): ?self
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!is_array($decoded)) {
            return null;
        }

        $key = self::stringField($decoded, 'key');
        $writerId = self::stringField($decoded, 'writer_id');
        $payload = self::stringField($decoded, 'payload');
        $version = self::intField($decoded, 'version');
        $writtenNs = self::intField($decoded, 'written_ns');
        $writerPid = self::intField($decoded, 'writer_pid');
        $writerSeq = self::intField($decoded, 'writer_seq');

        if (
            $key === null
            || $writerId === null
            || $payload === null
            || $version === null
            || $writtenNs === null
            || $writerPid === null
            || $writerSeq === null
        ) {
            return null;
        }

        return new self($key, $version, $writtenNs, $writerPid, $writerId, $writerSeq, $payload, $value);
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private static function stringField(array $decoded, string $field): ?string
    {
        $value = $decoded[$field] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private static function intField(array $decoded, string $field): ?int
    {
        $value = $decoded[$field] ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }
}
