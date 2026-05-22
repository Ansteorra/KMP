<?php
declare(strict_types=1);

namespace App\Services\Storage;

use App\KMP\TenantContext;
use App\KMP\TenantMetadata;
use Cake\Core\Configure;
use InvalidArgumentException;

/**
 * Resolves document blob storage settings, including tenant-specific containers.
 */
class TenantDocumentStorageConfigResolver
{
    /**
     * Resolve the active Azure document storage configuration.
     *
     * @return array<string, mixed>
     */
    public function resolveAzureConfig(?TenantMetadata $tenant = null): array
    {
        $config = (array)Configure::read('Documents.storage.azure', []);
        $tenant ??= TenantContext::tryCurrent();
        $container = $this->resolveContainerName($config, $tenant);
        $config['container'] = $container;
        $prefix = $this->tenantConfiguredPrefix($tenant);
        if ($prefix !== null) {
            $config['prefix'] = $prefix;
        }

        return $config;
    }

    /**
     * Resolve the blob container name for a tenant.
     *
     * @param array<string, mixed> $azureConfig Azure storage config
     * @return string
     */
    public function resolveContainerName(array $azureConfig, ?TenantMetadata $tenant = null): string
    {
        $configured = $this->tenantConfiguredContainer($tenant);
        if ($configured !== null) {
            return $this->assertValidContainerName($configured);
        }

        if ($tenant !== null) {
            $prefix = trim((string)($azureConfig['containerPrefix'] ?? 'documents'), '-');

            return $this->assertValidContainerName($prefix . '-' . $tenant->slug);
        }

        return $this->assertValidContainerName((string)($azureConfig['container'] ?? 'documents'));
    }

    /**
     * Read tenant-configured document container name.
     *
     * @param \App\KMP\TenantMetadata|null $tenant Tenant metadata
     * @return string|null
     */
    private function tenantConfiguredContainer(?TenantMetadata $tenant): ?string
    {
        $documents = $tenant?->tenantConfig['documents'] ?? null;
        if (!is_array($documents)) {
            return null;
        }

        $container = $documents['blob_container'] ?? $documents['container'] ?? null;
        if (!is_string($container) || trim($container) === '') {
            return null;
        }

        return trim($container);
    }

    /**
     * Read tenant-configured document object prefix.
     *
     * @param \App\KMP\TenantMetadata|null $tenant Tenant metadata
     * @return string|null
     */
    private function tenantConfiguredPrefix(?TenantMetadata $tenant): ?string
    {
        $documents = $tenant?->tenantConfig['documents'] ?? null;
        if (!is_array($documents)) {
            return null;
        }

        $prefix = $documents['blob_prefix'] ?? $documents['prefix'] ?? null;
        if (!is_string($prefix) || trim($prefix) === '') {
            return null;
        }

        return trim($prefix, '/');
    }

    /**
     * Validate and normalize an Azure blob container name.
     *
     * @param string $container Container name
     * @return string
     */
    private function assertValidContainerName(string $container): string
    {
        $container = strtolower($container);
        if (
            !preg_match('/^[a-z0-9](?:[a-z0-9-]{1,61}[a-z0-9])$/', $container) ||
            str_contains($container, '--')
        ) {
            throw new InvalidArgumentException(sprintf('Invalid Azure blob container name "%s".', $container));
        }

        return $container;
    }
}
