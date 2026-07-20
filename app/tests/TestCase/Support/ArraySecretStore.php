<?php
declare(strict_types=1);

namespace App\Test\TestCase\Support;

use App\Services\Secrets\SecretStoreInterface;
use App\Services\Secrets\SensitiveString;
use DateTimeImmutable;

class ArraySecretStore implements SecretStoreInterface
{
    /**
     * @param array<string, string> $values
     */
    public function __construct(private readonly array $values)
    {
    }

    public function get(string $name): ?SensitiveString
    {
        return array_key_exists($name, $this->values) ? new SensitiveString($this->values[$name]) : null;
    }

    public function exists(string $name): bool
    {
        return array_key_exists($name, $this->values);
    }

    public function list(string $prefix = ''): array
    {
        return array_values(array_filter(
            array_keys($this->values),
            static fn(string $name): bool => $prefix === '' || str_starts_with($name, $prefix),
        ));
    }

    public function rotatedAt(string $name): ?DateTimeImmutable
    {
        return null;
    }
}
