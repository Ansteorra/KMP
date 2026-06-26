<?php
declare(strict_types=1);

namespace App\Services\Backup;

/**
 * Upgrades decoded backup payloads before they are restored to the current schema.
 */
interface BackupPayloadMigratorInterface
{
    public function name(): string;

    /**
     * @param array<string, mixed> $payload Decoded backup payload.
     */
    public function shouldRun(array $payload): bool;

    /**
     * @param array<string, mixed> $payload Decoded backup payload.
     * @return array{payload: array<string, mixed>, stats: array<string, int>}
     */
    public function migrate(array $payload): array;
}
