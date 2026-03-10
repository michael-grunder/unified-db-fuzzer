<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Fuzz;

final readonly class ObservedStaleness
{
    /**
     * @param array<string, bool|float|int|string|null> $debug
     */
    public function __construct(
        public string $key,
        public string $command,
        public bool $cachedExists,
        public bool $truthExists,
        public ?int $cachedVersion,
        public ?int $truthVersion,
        public ?int $cachedWrittenNs,
        public ?int $truthWrittenNs,
        public ?int $stepsBehind,
        public ?int $ageNs,
        public bool $regression,
        public bool $suspicious,
        public string $classification,
        public array $debug = [],
    ) {
    }

    /**
     * @param array<string, bool|float|int|string|null> $debug
     */
    public function with(
        ?bool $regression = null,
        ?bool $suspicious = null,
        ?string $classification = null,
        ?array $debug = null,
    ): self {
        return new self(
            $this->key,
            $this->command,
            $this->cachedExists,
            $this->truthExists,
            $this->cachedVersion,
            $this->truthVersion,
            $this->cachedWrittenNs,
            $this->truthWrittenNs,
            $this->stepsBehind,
            $this->ageNs,
            $regression ?? $this->regression,
            $suspicious ?? $this->suspicious,
            $classification ?? $this->classification,
            $debug ?? $this->debug,
        );
    }
}
