<?php

declare(strict_types=1);

namespace Officers\Services\Api;

use App\KMP\KmpIdentityInterface;

interface ReadOnlyOfficeServiceInterface
{
    /**
     * @return array{data: array<array<string,mixed>>, meta: array<string,mixed>}
     */
    public function list(KmpIdentityInterface $identity, array $filters, int $page, int $limit): array;

    /**
     * @return array<string,mixed>|null
     */
    public function getById(KmpIdentityInterface $identity, int $id): ?array;
}

