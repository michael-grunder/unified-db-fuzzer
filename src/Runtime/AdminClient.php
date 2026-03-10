<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime;

interface AdminClient
{
    public function currentClientId(): int;

    /**
     * @return list<int>
     */
    public function listClientIds(): array;

    public function killClientById(int $clientId): bool;
}
