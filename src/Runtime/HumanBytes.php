<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime;

final class HumanBytes
{
    /**
     * @param array<int, string> $units
     */
    public static function format(int $bytes, array $units = ['b', 'k', 'm', 'g', 't']): string
    {
        $value = (float) $bytes;
        $unit = 0;

        while ($value >= 1024.0 && $unit < count($units) - 1) {
            $value /= 1024.0;
            $unit++;
        }

        if ($unit === 0) {
            return sprintf('%db', (int) $value);
        }

        if ($value >= 10.0) {
            return sprintf('%.0f%s', $value, $units[$unit]);
        }

        return sprintf('%.1f%s', $value, $units[$unit]);
    }
}
