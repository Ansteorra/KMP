<?php
declare(strict_types=1);

namespace App\Services\Secrets;

use DateTimeImmutable;

interface SecretStoreInterface
{
    /**
     * Fetch a secret value.
     *
     * @param string $name Secret name
     * @return \App\Services\Secrets\SensitiveString|null
     */
    public function get(string $name): ?SensitiveString;

    /**
     * Return true if a secret name exists without revealing or decrypting its value.
     */
    public function exists(string $name): bool;

    /**
     * @return list<string> Secret names only; never values.
     */
    public function list(string $prefix = ''): array;

    /**
     * Return when a secret was last rotated, if known, without revealing the value.
     *
     * @param string $name Secret name
     * @return \DateTimeImmutable|null
     */
    public function rotatedAt(string $name): ?DateTimeImmutable;
}
