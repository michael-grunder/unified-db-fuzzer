<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime;

use RuntimeException;

use function explode;
use function is_array;
use function is_numeric;
use function is_string;
use function preg_split;
use function trim;

final class RelayAdminClient implements AdminClient
{
    public function __construct(
        private readonly object $relay,
    ) {
    }

    public function currentClientId(): int
    {
        $clientId = $this->relay->rawCommand('CLIENT', 'ID');

        if (!is_numeric($clientId)) {
            throw new RuntimeException('CLIENT ID did not return a numeric client id.');
        }

        return (int) $clientId;
    }

    public function listClientIds(): array
    {
        $clients = $this->relay->client('list');

        if (is_array($clients)) {
            $clientIds = [];

            foreach ($clients as $client) {
                if (is_array($client) && isset($client['id']) && is_numeric($client['id'])) {
                    $clientIds[] = (int) $client['id'];
                }
            }

            return $clientIds;
        }

        if (!is_string($clients) || trim($clients) === '') {
            return [];
        }

        $clientIds = [];
        $lines = preg_split('/\r?\n/', trim($clients));
        if ($lines === false) {
            return [];
        }

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            foreach (preg_split('/\s+/', trim($line)) ?: [] as $pair) {
                [$key, $value] = explode('=', $pair, 2) + ['', ''];
                if ($key === 'id' && is_numeric($value)) {
                    $clientIds[] = (int) $value;
                    break;
                }
            }
        }

        return $clientIds;
    }

    public function killClientById(int $clientId): bool
    {
        $result = $this->relay->rawCommand('CLIENT', 'KILL', 'ID', (string) $clientId);

        if (is_numeric($result)) {
            return (int) $result > 0;
        }

        return $result === true;
    }
}
