<?php
declare(strict_types=1);

namespace App\KMP;

/**
 * Immutable platform metadata required to bind a tenant-scoped request or job.
 */
final class TenantMetadata
{
    /**
     * Constructor.
     *
     * @param string $id Tenant UUID
     * @param string $slug Tenant slug
     * @param string $displayName Human-readable tenant name
     * @param string $status Tenant lifecycle status
     * @param string $dbServer Tenant database host
     * @param string $dbName Tenant database name
     * @param string $dbRole Tenant database role/user
     * @param string|null $schemaVersion Last applied tenant schema version
     * @param array<string, mixed> $tenantConfig Tenant-specific platform configuration
     */
    public function __construct(
        public readonly string $id,
        public readonly string $slug,
        public readonly string $displayName,
        public readonly string $status,
        public readonly string $dbServer,
        public readonly string $dbName,
        public readonly string $dbRole,
        public readonly ?string $schemaVersion = null,
        public readonly array $tenantConfig = [],
    ) {
    }

    /**
     * @param array<string, mixed> $row Platform tenant row
     * @return self
     */
    public static function fromPlatformRow(array $row): self
    {
        $tenantConfig = [];
        if (isset($row['tenant_config']) && is_string($row['tenant_config']) && $row['tenant_config'] !== '') {
            $decodedConfig = json_decode($row['tenant_config'], true);
            if (is_array($decodedConfig)) {
                $tenantConfig = $decodedConfig;
            }
        }

        return new self(
            (string)$row['id'],
            (string)$row['slug'],
            (string)$row['display_name'],
            (string)$row['status'],
            (string)$row['db_server'],
            (string)$row['db_name'],
            (string)$row['db_role'],
            isset($row['schema_version']) ? (string)$row['schema_version'] : null,
            $tenantConfig,
        );
    }
}
