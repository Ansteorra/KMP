<?php
declare(strict_types=1);

namespace App\Services\Platform;

/**
 * Immutable tenant provisioning inputs shared by CLI and platform jobs.
 */
final class TenantProvisioningRequest
{
    public const STATUS_PROVISIONING = 'provisioning';
    public const STATUS_ACTIVE = 'active';

    /**
     * Constructor.
     *
     * @param array<string, mixed> $tenantConfig Safe tenant config
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $displayName,
        public readonly string $host,
        public readonly ?string $dbServer = null,
        public readonly ?string $dbName = null,
        public readonly ?string $dbRole = null,
        public readonly ?string $blobContainer = null,
        public readonly string $region = 'us',
        public readonly array $tenantConfig = [],
        public readonly string $finalStatus = self::STATUS_ACTIVE,
        public readonly bool $createDatabase = false,
        public readonly bool $skipCreateDatabase = false,
        public readonly bool $runMigrations = true,
        public readonly string $smokeTable = 'members',
        public readonly bool $rotatePassword = false,
        public readonly ?string $initialSuperUserEmail = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data Request data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            slug: (string)($data['slug'] ?? ''),
            displayName: (string)($data['displayName'] ?? $data['display_name'] ?? ''),
            host: (string)($data['host'] ?? $data['primary_host'] ?? ''),
            dbServer: self::nullableString($data['dbServer'] ?? $data['db_server'] ?? null),
            dbName: self::nullableString($data['dbName'] ?? $data['db_name'] ?? null),
            dbRole: self::nullableString($data['dbRole'] ?? $data['db_role'] ?? null),
            blobContainer: self::nullableString($data['blobContainer'] ?? $data['blob_container'] ?? null),
            region: (string)($data['region'] ?? 'us'),
            tenantConfig: is_array($data['tenantConfig'] ?? null) ? $data['tenantConfig'] : [],
            finalStatus: (string)($data['finalStatus'] ?? $data['status'] ?? self::STATUS_ACTIVE),
            createDatabase: self::boolValue($data['createDatabase'] ?? $data['create_database'] ?? false),
            skipCreateDatabase: self::boolValue($data['skipCreateDatabase'] ?? $data['skip_create_database'] ?? false),
            runMigrations: self::boolValue($data['runMigrations'] ?? $data['run_migrations'] ?? true),
            smokeTable: (string)($data['smokeTable'] ?? $data['smoke_table'] ?? 'members'),
            rotatePassword: self::boolValue($data['rotatePassword'] ?? $data['rotate_password'] ?? false),
            initialSuperUserEmail: self::nullableString(
                $data['initialSuperUserEmail'] ?? $data['initial_super_user_email'] ?? null,
            ),
        );
    }

    /**
     * Return a copy with normalized, defaulted values.
     */
    public function normalized(string $defaultDbServer): self
    {
        $slug = strtolower(trim($this->slug));
        $dbName = trim((string)$this->dbName) ?: 'kmp_tenant_' . str_replace('-', '_', $slug);
        $dbRole = trim((string)$this->dbRole) ?: $dbName . '_role';

        return new self(
            slug: $slug,
            displayName: trim($this->displayName) !== '' ? trim($this->displayName) : $slug,
            host: strtolower(trim($this->host)),
            dbServer: trim((string)$this->dbServer) !== '' ? trim((string)$this->dbServer) : $defaultDbServer,
            dbName: $dbName,
            dbRole: $dbRole,
            blobContainer: trim((string)$this->blobContainer) !== ''
                ? trim((string)$this->blobContainer)
                : 'tenant-' . $slug,
            region: strtolower(trim($this->region) !== '' ? trim($this->region) : 'us'),
            tenantConfig: $this->tenantConfig,
            finalStatus: trim($this->finalStatus) !== '' ? trim($this->finalStatus) : self::STATUS_ACTIVE,
            createDatabase: $this->createDatabase,
            skipCreateDatabase: $this->skipCreateDatabase,
            runMigrations: $this->runMigrations,
            smokeTable: trim($this->smokeTable) !== '' ? trim($this->smokeTable) : 'members',
            rotatePassword: $this->rotatePassword,
            initialSuperUserEmail: $this->initialSuperUserEmail !== null
                ? strtolower(trim($this->initialSuperUserEmail))
                : null,
        );
    }

    /**
     * Normalize nullable string input.
     */
    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string)$value);

        return $value === '' ? null : $value;
    }

    /**
     * Normalize booleans from form, CLI, and JSON payload values.
     */
    private static function boolValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true);
    }
}
