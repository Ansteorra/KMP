<?php
declare(strict_types=1);

namespace App\Services\Platform;

/**
 * Safe tenant provisioning result data.
 */
final class TenantProvisioningResult
{
    /**
     * @param array<string, mixed> $tenant Tenant platform row
     */
    public function __construct(
        public readonly array $tenant,
        public readonly string $secretName,
        public readonly ?string $schemaVersion,
        public readonly string $finalStatus,
    ) {
    }

    /**
     * Return the provisioned tenant id.
     */
    public function tenantId(): string
    {
        return (string)$this->tenant['id'];
    }

    /**
     * Return the provisioned tenant slug.
     */
    public function slug(): string
    {
        return (string)$this->tenant['slug'];
    }
}
