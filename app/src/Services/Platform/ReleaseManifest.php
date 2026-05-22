<?php
declare(strict_types=1);

namespace App\Services\Platform;

use RuntimeException;

/**
 * Immutable deploy manifest describing app image and tenant schema bounds.
 */
final class ReleaseManifest
{
    /**
     * Constructor.
     *
     * @param list<string> $compatiblePreviousSchemas
     * @param array<string, mixed> $migrationPolicy
     */
    private function __construct(
        public readonly string $appVersion,
        public readonly string $image,
        public readonly string $imageDigest,
        public readonly string $minTenantSchema,
        public readonly string $maxTenantSchema,
        public readonly array $compatiblePreviousSchemas,
        public readonly array $migrationPolicy,
        public readonly string $rollbackNotes,
    ) {
    }

    /**
     * Load and validate a release manifest JSON file.
     */
    public static function fromFile(string $path): self
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException(sprintf('Release manifest "%s" is not readable.', $path));
        }

        $payload = file_get_contents($path);
        if ($payload === false) {
            throw new RuntimeException(sprintf('Release manifest "%s" could not be read.', $path));
        }

        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) {
            throw new RuntimeException(sprintf(
                'Release manifest "%s" is invalid JSON: %s.',
                $path,
                json_last_error_msg(),
            ));
        }

        return self::fromArray($decoded);
    }

    /**
     * Build a manifest from decoded JSON data.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (($data['format_version'] ?? null) !== 1) {
            throw new RuntimeException('Release manifest format_version must be 1.');
        }

        $app = self::arrayValue($data, 'app');
        $tenantSchema = self::arrayValue($data, 'tenant_schema');
        $migrationPolicy = self::arrayValue($data, 'migration_policy');

        $appVersion = self::stringValue($app, 'version');
        $image = self::stringValue($app, 'image');
        $imageDigest = self::stringValue($app, 'digest');
        if (!preg_match('/^sha256:[a-fA-F0-9]{64}$/', $imageDigest)) {
            throw new RuntimeException('Release manifest app.digest must be a sha256 digest.');
        }

        $minTenantSchema = self::schemaValue($tenantSchema, 'min');
        $maxTenantSchema = self::schemaValue($tenantSchema, 'max');
        if (strcmp($minTenantSchema, $maxTenantSchema) > 0) {
            throw new RuntimeException('Release manifest tenant_schema.min cannot be greater than max.');
        }

        $compatiblePreviousSchemas = self::schemaListValue($tenantSchema, 'compatible_previous');
        $rollbackNotes = self::stringValue($data, 'rollback_notes');

        return new self(
            $appVersion,
            $image,
            strtolower($imageDigest),
            $minTenantSchema,
            $maxTenantSchema,
            $compatiblePreviousSchemas,
            $migrationPolicy,
            $rollbackNotes,
        );
    }

    /**
     * Return safe metadata for platform job parameters.
     *
     * @return array<string, mixed>
     */
    public function toMetadata(): array
    {
        return [
            'app_version' => $this->appVersion,
            'image' => $this->image,
            'image_digest' => $this->imageDigest,
            'min_tenant_schema' => $this->minTenantSchema,
            'max_tenant_schema' => $this->maxTenantSchema,
            'compatible_previous_schemas' => $this->compatiblePreviousSchemas,
            'migration_policy' => $this->migrationPolicy,
            'rollback_notes' => $this->rollbackNotes,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function arrayValue(array $data, string $key): array
    {
        if (!isset($data[$key]) || !is_array($data[$key])) {
            throw new RuntimeException(sprintf('Release manifest field "%s" must be an object.', $key));
        }

        return $data[$key];
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function stringValue(array $data, string $key): string
    {
        if (!isset($data[$key]) || !is_string($data[$key]) || trim($data[$key]) === '') {
            throw new RuntimeException(sprintf('Release manifest field "%s" must be a non-empty string.', $key));
        }

        return trim($data[$key]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function schemaValue(array $data, string $key): string
    {
        $schema = self::stringValue($data, $key);
        if (!preg_match('/^\d{14}$/', $schema)) {
            throw new RuntimeException(sprintf('Release manifest schema field "%s" must be a 14 digit version.', $key));
        }

        return $schema;
    }

    /**
     * @param array<string, mixed> $data
     * @return list<string>
     */
    private static function schemaListValue(array $data, string $key): array
    {
        if (!isset($data[$key]) || !is_array($data[$key])) {
            throw new RuntimeException(sprintf('Release manifest field "%s" must be an array.', $key));
        }

        $schemas = [];
        foreach ($data[$key] as $schema) {
            if (!is_string($schema) || !preg_match('/^\d{14}$/', $schema)) {
                throw new RuntimeException(sprintf(
                    'Release manifest field "%s" must only contain 14 digit schema versions.',
                    $key,
                ));
            }
            $schemas[] = $schema;
        }

        return $schemas;
    }
}
