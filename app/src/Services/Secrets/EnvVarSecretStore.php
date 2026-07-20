<?php
declare(strict_types=1);

namespace App\Services\Secrets;

use BadMethodCallException;
use DateTimeImmutable;

class EnvVarSecretStore implements SecretStoreInterface
{
    /**
     * Constructor.
     *
     * @param string $prefix Environment variable prefix
     */
    public function __construct(private readonly string $prefix = 'KMP_SECRET_')
    {
    }

    /**
     * @inheritDoc
     */
    public function get(string $name): ?SensitiveString
    {
        $value = getenv($this->envName($name));
        if ($value === false) {
            return null;
        }

        return new SensitiveString($value);
    }

    /**
     * Write operations are unsupported for environment variables.
     *
     * @param string $name Secret name
     * @param \App\Services\Secrets\SensitiveString $value Secret value
     * @return void
     */
    public function put(string $name, SensitiveString $value): void
    {
        throw new BadMethodCallException('EnvVarSecretStore is read-only.');
    }

    /**
     * Delete operations are unsupported for environment variables.
     *
     * @param string $name Secret name
     * @return void
     */
    public function delete(string $name): void
    {
        throw new BadMethodCallException('EnvVarSecretStore is read-only.');
    }

    /**
     * @inheritDoc
     */
    public function exists(string $name): bool
    {
        return getenv($this->envName($name)) !== false;
    }

    /**
     * @inheritDoc
     */
    public function list(string $prefix = ''): array
    {
        $environment = getenv();
        if (!is_array($environment)) {
            return [];
        }

        $names = [];
        foreach (array_keys($environment) as $envName) {
            if (!str_starts_with((string)$envName, $this->prefix) || str_ends_with((string)$envName, '_ROTATED_AT')) {
                continue;
            }
            $name = strtolower(str_replace('_', '.', substr((string)$envName, strlen($this->prefix))));
            if ($prefix === '' || str_starts_with($name, $prefix)) {
                $names[] = $name;
            }
        }
        sort($names);

        return $names;
    }

    /**
     * @inheritDoc
     */
    public function rotatedAt(string $name): ?DateTimeImmutable
    {
        $value = getenv($this->envName($name) . '_ROTATED_AT');
        if ($value === false || $value === '') {
            return null;
        }

        return new DateTimeImmutable($value);
    }

    /**
     * Convert a logical secret name to its environment variable name.
     *
     * @param string $name Secret name
     * @return string
     */
    private function envName(string $name): string
    {
        return $this->prefix . strtoupper((string)preg_replace('/[^A-Za-z0-9]/', '_', $name));
    }
}
