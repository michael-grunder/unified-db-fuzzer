<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime;

use RuntimeException;

use function explode;
use function is_array;
use function is_numeric;
use function is_string;
use function preg_match;
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

    public function listClients(): array
    {
        $clients = $this->relay->client('list');

        if (is_array($clients)) {
            $parsedClients = [];

            foreach ($clients as $client) {
                if (!is_array($client) || !isset($client['id']) || !is_numeric($client['id'])) {
                    continue;
                }

                $parsedClients[] = new RedisClientConnection(
                    id: (int) $client['id'],
                    name: isset($client['name']) && is_string($client['name']) && $client['name'] !== '' ? $client['name'] : null,
                    libraryName: isset($client['lib-name']) && is_string($client['lib-name']) && $client['lib-name'] !== ''
                        ? $client['lib-name']
                        : null,
                );
            }

            return $parsedClients;
        }

        if (!is_string($clients) || trim($clients) === '') {
            return [];
        }

        $parsedClients = [];
        $lines = preg_split('/\r?\n/', trim($clients));
        if ($lines === false) {
            return [];
        }

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            $id = null;
            $name = null;
            $libraryName = null;

            foreach (preg_split('/\s+/', trim($line)) ?: [] as $pair) {
                [$key, $value] = explode('=', $pair, 2) + ['', ''];

                if ($key === 'id' && is_numeric($value)) {
                    $id = (int) $value;
                    continue;
                }

                if ($key === 'name' && $value !== '') {
                    $name = $value;
                    continue;
                }

                if ($key === 'lib-name' && $value !== '') {
                    $libraryName = $value;
                }
            }

            if ($id === null && preg_match('/(?:^|\s)id=(\d+)(?:\s|$)/', $line, $matches) === 1) {
                $id = (int) $matches[1];
            }

            if ($id === null) {
                continue;
            }

            $parsedClients[] = new RedisClientConnection($id, $name, $libraryName);
        }

        return $parsedClients;
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
