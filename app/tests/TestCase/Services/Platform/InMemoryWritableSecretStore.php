<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Platform;

use App\Services\Secrets\SensitiveString;
use App\Services\Secrets\WritableSecretStoreInterface;
use DateTimeImmutable;

class InMemoryWritableSecretStore implements WritableSecretStoreInterface
{
    /**
     * @var array<string, string>
     */
    private array $values = [];

    /**
     * @inheritDoc
     */
    public function get(string $name): ?SensitiveString
    {
        return array_key_exists($name, $this->values) ? new SensitiveString($this->values[$name]) : null;
    }

    /**
     * @inheritDoc
     */
    public function exists(string $name): bool
    {
        return array_key_exists($name, $this->values);
    }

    /**
     * @return list<string>
     */
    public function list(string $prefix = ''): array
    {
        return array_values(array_filter(
            array_keys($this->values),
            static fn(string $name): bool => $prefix === '' || str_starts_with($name, $prefix),
        ));
    }

    /**
     * @inheritDoc
     */
    public function rotatedAt(string $name): ?DateTimeImmutable
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function put(string $name, SensitiveString $value): void
    {
        $this->values[$name] = $value->reveal();
    }

    /**
     * @inheritDoc
     */
    public function delete(string $name): void
    {
        unset($this->values[$name]);
    }
}
