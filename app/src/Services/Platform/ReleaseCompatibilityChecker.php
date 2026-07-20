<?php
declare(strict_types=1);

namespace App\Services\Platform;

use RuntimeException;

/**
 * Enforces release manifest tenant schema compatibility before deploy or migrate.
 */
final class ReleaseCompatibilityChecker
{
    /**
     * Fail closed when a tenant schema is outside the release contract.
     */
    public function assertTenantCompatible(
        ?string $schemaVersion,
        ReleaseManifest $manifest,
        ?string $tenantSlug = null,
    ): void {
        $label = $tenantSlug === null ? 'Tenant' : sprintf('Tenant "%s"', $tenantSlug);
        if ($schemaVersion === null || trim($schemaVersion) === '') {
            throw new RuntimeException(sprintf(
                '%s has no recorded schema version; expected %s through %s or compatible previous schema %s.',
                $label,
                $manifest->minTenantSchema,
                $manifest->maxTenantSchema,
                $this->previousSchemaLabel($manifest),
            ));
        }

        if (!preg_match('/^\d{14}$/', $schemaVersion)) {
            throw new RuntimeException(sprintf('%s schema version "%s" is invalid.', $label, $schemaVersion));
        }

        if (in_array($schemaVersion, $manifest->compatiblePreviousSchemas, true)) {
            return;
        }

        if (strcmp($schemaVersion, $manifest->minTenantSchema) < 0) {
            throw new RuntimeException(sprintf(
                '%s schema %s is below release minimum %s; compatible previous schemas: %s.',
                $label,
                $schemaVersion,
                $manifest->minTenantSchema,
                $this->previousSchemaLabel($manifest),
            ));
        }

        if (strcmp($schemaVersion, $manifest->maxTenantSchema) > 0) {
            throw new RuntimeException(sprintf(
                '%s schema %s is above release maximum %s.',
                $label,
                $schemaVersion,
                $manifest->maxTenantSchema,
            ));
        }
    }

    /**
     * @param iterable<array{slug?: string|null, schema_version?: string|null}> $tenantRows
     * @return list<string>
     */
    public function incompatibleTenants(iterable $tenantRows, ReleaseManifest $manifest): array
    {
        $errors = [];
        foreach ($tenantRows as $row) {
            try {
                $this->assertTenantCompatible(
                    isset($row['schema_version']) ? (string)$row['schema_version'] : null,
                    $manifest,
                    isset($row['slug']) ? (string)$row['slug'] : null,
                );
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }

        return $errors;
    }

    /**
     * Format compatible previous schemas for operator-facing errors.
     */
    private function previousSchemaLabel(ReleaseManifest $manifest): string
    {
        return $manifest->compatiblePreviousSchemas === []
            ? 'none'
            : implode(', ', $manifest->compatiblePreviousSchemas);
    }
}
