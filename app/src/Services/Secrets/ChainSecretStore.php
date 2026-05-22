<?php
declare(strict_types=1);

namespace App\Services\Secrets;

use BadMethodCallException;
use DateTimeImmutable;
use InvalidArgumentException;

class ChainSecretStore implements WritableSecretStoreInterface
{
    /**
     * @param array<string, \App\Services\Secrets\SecretStoreInterface> $stores
     */
    public function __construct(
        private readonly array $stores,
        private readonly ?string $writeTo = null,
    ) {
        if ($this->stores === []) {
            throw new InvalidArgumentException('ChainSecretStore requires at least one store.');
        }
        if ($writeTo !== null && !isset($this->stores[$writeTo])) {
            throw new InvalidArgumentException(sprintf('Unknown writable secret store "%s".', $writeTo));
        }
    }

    /**
     * @inheritDoc
     */
    public function get(string $name): ?SensitiveString
    {
        foreach ($this->stores as $store) {
            $value = $store->get($name);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function put(string $name, SensitiveString $value): void
    {
        $this->writableStore()->put($name, $value);
    }

    /**
     * @inheritDoc
     */
    public function delete(string $name): void
    {
        $this->writableStore()->delete($name);
    }

    /**
     * @inheritDoc
     */
    public function exists(string $name): bool
    {
        foreach ($this->stores as $store) {
            if ($store->exists($name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function list(string $prefix = ''): array
    {
        $names = [];
        foreach ($this->stores as $store) {
            foreach ($store->list($prefix) as $name) {
                $names[$name] = true;
            }
        }
        $names = array_keys($names);
        sort($names);

        return $names;
    }

    /**
     * @inheritDoc
     */
    public function rotatedAt(string $name): ?DateTimeImmutable
    {
        foreach ($this->stores as $store) {
            if ($store->exists($name)) {
                return $store->rotatedAt($name);
            }
        }

        return null;
    }

    /**
     * Resolve the explicitly configured writable store.
     *
     * @return \App\Services\Secrets\WritableSecretStoreInterface
     */
    private function writableStore(): WritableSecretStoreInterface
    {
        if ($this->writeTo === null) {
            throw new BadMethodCallException('ChainSecretStore has no write target configured.');
        }
        $store = $this->stores[$this->writeTo];
        if (!$store instanceof WritableSecretStoreInterface) {
            throw new BadMethodCallException(sprintf('Secret store "%s" is not writable.', $this->writeTo));
        }

        return $store;
    }
}
