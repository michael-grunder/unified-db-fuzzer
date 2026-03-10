<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Console\Input;

use Symfony\Component\Console\Input\InputDefinition;

final class NegativeNumberOptionNormalizer
{
    /**
     * @param list<string> $argv
     * @return list<string>
     */
    public function normalize(array $argv, InputDefinition $definition): array
    {
        $normalized = [];
        $parseOptions = true;
        $argc = count($argv);

        for ($i = 0; $i < $argc; $i++) {
            $token = $argv[$i];

            if (!$parseOptions) {
                $normalized[] = $token;

                continue;
            }

            if ($token === '--') {
                $parseOptions = false;
                $normalized[] = $token;

                continue;
            }

            if (!str_starts_with($token, '--') || str_contains($token, '=')) {
                $normalized[] = $token;

                continue;
            }

            $name = substr($token, 2);
            if (!$definition->hasOption($name)) {
                $normalized[] = $token;

                continue;
            }

            $option = $definition->getOption($name);
            $next = $argv[$i + 1] ?? null;

            if (
                $option->isValueRequired()
                && is_string($next)
                && self::isNegativeNumber($next)
            ) {
                $normalized[] = sprintf('%s=%s', $token, $next);
                $i++;

                continue;
            }

            $normalized[] = $token;
        }

        return $normalized;
    }

    private static function isNegativeNumber(string $value): bool
    {
        return preg_match('/^-\d+(?:\.\d+)?$/', $value) === 1;
    }
}
