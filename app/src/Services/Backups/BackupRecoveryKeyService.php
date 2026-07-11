<?php
declare(strict_types=1);

namespace App\Services\Backups;

use App\KMP\TenantMetadata;
use App\Services\Secrets\SecretStoreInterface;
use App\Services\Secrets\SensitiveString;
use JsonException;
use RuntimeException;

/**
 * Creates and consumes portable, backup-scoped recovery-key packages.
 */
final class BackupRecoveryKeyService
{
    public const FORMAT = 'kmp-managed-backup-recovery-key';
    public const VERSION = 1;
    public const FILE_EXTENSION = '.kmpbackup-key.json';
    public const MAX_KEY_FILE_BYTES = 65536;

    private const SCOPE_TENANT = 'tenant';
    private const SCOPE_PLATFORM = 'platform';

    /**
     * Constructor.
     */
    public function __construct(
        private readonly ?TenantBackupEncryptor $tenantEncryptor = null,
        private readonly ?PlatformDatabaseBackupEncryptor $platformEncryptor = null,
        private readonly ?BackupStreamCipher $streamCipher = null,
    ) {
    }

    /**
     * Create a tenant backup recovery-key download.
     *
     * @param array<string, mixed> $backup Backup metadata row
     * @return array{filename: string, content: string}
     */
    public function exportTenant(
        array $backup,
        TenantMetadata $tenant,
        SecretStoreInterface $secretStore,
    ): array {
        $this->assertCommonBackupMetadata($backup, TenantBackupEncryptor::DATA_ALGORITHM);
        if ((string)($backup['tenant_id'] ?? '') !== $tenant->id) {
            throw new RuntimeException('Backup tenant metadata does not match the selected tenant.');
        }
        if ((string)($backup['backup_type'] ?? '') !== TenantBackupService::BACKUP_TYPE) {
            throw new RuntimeException('Recovery keys are available only for managed JSON tenant backups.');
        }
        $expectedKekName = sprintf('tenant.%s.kek', $tenant->slug);
        if ((string)($backup['wrapped_dek_key_name'] ?? '') !== $expectedKekName) {
            throw new RuntimeException('Backup key metadata does not match the selected tenant.');
        }

        $dek = ($this->tenantEncryptor ?? new TenantBackupEncryptor())->exportDataEncryptionKey(
            (string)$backup['wrapped_dek'],
            $this->decodeWrappingMetadata($backup),
            $this->requireKek($secretStore, $expectedKekName),
            $tenant,
            (string)$backup['id'],
        )->reveal();
        try {
            return $this->buildExport(
                $backup,
                self::SCOPE_TENANT,
                ['id' => $tenant->id, 'slug' => $tenant->slug],
                $dek,
                sprintf('%s-%s%s', $tenant->slug, $backup['id'], self::FILE_EXTENSION),
            );
        } finally {
            sodium_memzero($dek);
        }
    }

    /**
     * Create a platform database backup recovery-key download.
     *
     * @param array<string, mixed> $backup Backup metadata row
     * @return array{filename: string, content: string}
     */
    public function exportPlatform(array $backup, SecretStoreInterface $secretStore): array
    {
        $this->assertCommonBackupMetadata($backup, PlatformDatabaseBackupEncryptor::DATA_ALGORITHM);
        if ((string)($backup['backup_type'] ?? '') !== PlatformDatabaseBackupService::BACKUP_TYPE) {
            throw new RuntimeException('Recovery keys are available only for managed platform database backups.');
        }
        if ((string)($backup['wrapped_dek_key_name'] ?? '') !== PlatformDatabaseBackupService::KEK_SECRET_NAME) {
            throw new RuntimeException('Platform backup key metadata is invalid.');
        }

        $dek = ($this->platformEncryptor ?? new PlatformDatabaseBackupEncryptor())->exportDataEncryptionKey(
            (string)$backup['wrapped_dek'],
            $this->decodeWrappingMetadata($backup),
            $this->requireKek($secretStore, PlatformDatabaseBackupService::KEK_SECRET_NAME),
            (string)$backup['id'],
        )->reveal();
        try {
            return $this->buildExport(
                $backup,
                self::SCOPE_PLATFORM,
                null,
                $dek,
                sprintf('platform-%s%s', $backup['id'], self::FILE_EXTENSION),
            );
        } finally {
            sodium_memzero($dek);
        }
    }

    /**
     * Decrypt a tenant archive after validating its recovery-key identity and checksum.
     */
    public function decryptTenantArchiveFile(
        string $archivePath,
        string $recoveryKeyJson,
        string $expectedTenantSlug,
    ): string {
        if (!is_file($archivePath) || !is_readable($archivePath)) {
            throw new RuntimeException('The managed backup archive could not be read.');
        }
        $package = $this->parsePackage($recoveryKeyJson);
        if ($package['scope'] !== self::SCOPE_TENANT) {
            throw new RuntimeException('The recovery key is not for a tenant backup.');
        }
        $tenant = $package['tenant'];
        if (
            !is_array($tenant)
            || (string)($tenant['slug'] ?? '') !== $expectedTenantSlug
            || (string)($tenant['id'] ?? '') === ''
        ) {
            throw new RuntimeException('The recovery key belongs to a different tenant.');
        }
        if ($package['backup_type'] !== TenantBackupService::BACKUP_TYPE) {
            throw new RuntimeException('The recovery key is not for a managed JSON tenant backup.');
        }
        if ($package['encryption_algorithm'] !== TenantBackupEncryptor::DATA_ALGORITHM) {
            throw new RuntimeException('The recovery key uses an unsupported archive encryption algorithm.');
        }

        $this->assertArchiveMatches($archivePath, $package['archive']);
        $dek = $this->decodeDataEncryptionKey($package);
        $temporaryPath = tempnam(TMP, 'tenant-recovery-');
        if ($temporaryPath === false) {
            sodium_memzero($dek);
            throw new RuntimeException('Could not allocate temporary backup recovery storage.');
        }

        $aad = sprintf(
            '%s|%s|%s',
            (string)$tenant['id'],
            (string)$tenant['slug'],
            $package['backup_id'],
        );
        try {
            $streamed = ($this->streamCipher ?? new BackupStreamCipher())->decryptFile(
                $archivePath,
                $temporaryPath,
                $dek,
                $aad,
                [
                    'scope' => self::SCOPE_TENANT,
                    'tenant_id' => (string)$tenant['id'],
                    'backup_id' => $package['backup_id'],
                ],
            );
            if (!$streamed) {
                throw new RuntimeException('The selected archive is not a supported managed tenant backup.');
            }
            $archiveData = file_get_contents($temporaryPath);
            if ($archiveData === false) {
                throw new RuntimeException('The decrypted backup archive could not be read.');
            }

            return $archiveData;
        } finally {
            sodium_memzero($dek);
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    /**
     * Decrypt an in-memory tenant archive with a matching recovery-key package.
     */
    public function decryptTenantArchive(
        string $archiveData,
        string $recoveryKeyJson,
        string $expectedTenantSlug,
    ): string {
        if ($archiveData === '') {
            throw new RuntimeException('The managed backup archive is empty.');
        }
        $temporaryPath = tempnam(TMP, 'tenant-managed-archive-');
        if ($temporaryPath === false) {
            throw new RuntimeException('Could not allocate temporary managed backup storage.');
        }
        try {
            if (file_put_contents($temporaryPath, $archiveData, LOCK_EX) !== strlen($archiveData)) {
                throw new RuntimeException('Could not stage the managed backup archive.');
            }
            chmod($temporaryPath, 0600);

            return $this->decryptTenantArchiveFile(
                $temporaryPath,
                $recoveryKeyJson,
                $expectedTenantSlug,
            );
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    /**
     * Decrypt a platform database archive for an external disaster-recovery restore.
     */
    public function decryptPlatformArchiveFile(
        string $archivePath,
        string $recoveryKeyJson,
        string $outputPath,
    ): void {
        if (!is_file($archivePath) || !is_readable($archivePath)) {
            throw new RuntimeException('The managed platform backup archive could not be read.');
        }
        if (file_exists($outputPath)) {
            throw new RuntimeException('The platform backup output path already exists.');
        }
        $outputDirectory = dirname($outputPath);
        if (!is_dir($outputDirectory) || !is_writable($outputDirectory)) {
            throw new RuntimeException('The platform backup output directory is not writable.');
        }

        $package = $this->parsePackage($recoveryKeyJson);
        if ($package['scope'] !== self::SCOPE_PLATFORM || $package['tenant'] !== null) {
            throw new RuntimeException('The recovery key is not for a platform database backup.');
        }
        if ($package['backup_type'] !== PlatformDatabaseBackupService::BACKUP_TYPE) {
            throw new RuntimeException('The recovery key is not for a managed platform database backup.');
        }
        if ($package['encryption_algorithm'] !== PlatformDatabaseBackupEncryptor::DATA_ALGORITHM) {
            throw new RuntimeException('The recovery key uses an unsupported archive encryption algorithm.');
        }
        $this->assertArchiveMatches($archivePath, $package['archive']);
        $dek = $this->decodeDataEncryptionKey($package);
        $previousUmask = umask(0077);
        try {
            $streamed = ($this->streamCipher ?? new BackupStreamCipher())->decryptFile(
                $archivePath,
                $outputPath,
                $dek,
                sprintf('platform|metadata|%s', $package['backup_id']),
                [
                    'scope' => self::SCOPE_PLATFORM,
                    'backup_id' => $package['backup_id'],
                ],
            );
            if (!$streamed) {
                throw new RuntimeException('The selected archive is not a supported managed platform backup.');
            }
        } finally {
            umask($previousUmask);
            sodium_memzero($dek);
            if (isset($streamed) && !$streamed && is_file($outputPath)) {
                unlink($outputPath);
            }
        }
    }

    /**
     * @param array{sha256: string, size_bytes: int} $expectedArchive Expected archive identity
     */
    private function assertArchiveMatches(string $archivePath, array $expectedArchive): void
    {
        $actualSize = filesize($archivePath);
        if ($actualSize === false || $actualSize !== $expectedArchive['size_bytes']) {
            throw new RuntimeException('The recovery key does not match the selected backup archive size.');
        }
        $actualSha256 = hash_file('sha256', $archivePath);
        if ($actualSha256 === false || !hash_equals($expectedArchive['sha256'], $actualSha256)) {
            throw new RuntimeException('The recovery key does not match the selected backup archive.');
        }
    }

    /**
     * @param array{data_encryption_key: array{encoding: string, value: string}} $package Recovery-key package
     */
    private function decodeDataEncryptionKey(array $package): string
    {
        $dek = base64_decode($package['data_encryption_key']['value'], true);
        if ($dek === false || strlen($dek) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
            throw new RuntimeException('The backup recovery key is invalid.');
        }

        return $dek;
    }

    /**
     * @param array<string, mixed> $backup Backup metadata row
     */
    private function assertCommonBackupMetadata(array $backup, string $expectedAlgorithm): void
    {
        if ((string)($backup['status'] ?? '') !== 'completed') {
            throw new RuntimeException('Recovery keys are available only for completed backups.');
        }
        if (
            !preg_match(
                '/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i',
                (string)($backup['id'] ?? ''),
            )
        ) {
            throw new RuntimeException('Backup identity metadata is invalid.');
        }
        if ((string)($backup['encryption_algorithm'] ?? '') !== $expectedAlgorithm) {
            throw new RuntimeException('This backup does not support portable recovery-key export.');
        }
        if (!preg_match('/\A[0-9a-f]{64}\z/', (string)($backup['object_sha256'] ?? ''))) {
            throw new RuntimeException('Backup integrity metadata is invalid.');
        }
        if (!is_numeric($backup['object_size_bytes'] ?? null) || (int)$backup['object_size_bytes'] <= 0) {
            throw new RuntimeException('Backup size metadata is invalid.');
        }
        if ((string)($backup['wrapped_dek'] ?? '') === '') {
            throw new RuntimeException('Backup key metadata is missing.');
        }
    }

    /**
     * @param array<string, mixed> $backup Backup metadata row
     * @return array<string, mixed>
     */
    private function decodeWrappingMetadata(array $backup): array
    {
        $rawMetadata = $backup['wrapped_dek_metadata'] ?? null;
        if (!is_string($rawMetadata) || $rawMetadata === '') {
            throw new RuntimeException('Backup key wrapping metadata is missing.');
        }
        try {
            $metadata = json_decode($rawMetadata, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Backup key wrapping metadata is invalid.', previous: $e);
        }
        if (!is_array($metadata)) {
            throw new RuntimeException('Backup key wrapping metadata is invalid.');
        }

        return $metadata;
    }

    /**
     * Fetch a required key-encryption key without exposing it in error output.
     */
    private function requireKek(
        SecretStoreInterface $secretStore,
        string $keyName,
    ): SensitiveString {
        $kek = $secretStore->get($keyName);
        if ($kek === null || $kek->isEmpty()) {
            throw new RuntimeException(sprintf('The key-encryption key required for "%s" is unavailable.', $keyName));
        }

        return $kek;
    }

    /**
     * @param array<string, mixed> $backup Backup metadata row
     * @param array{id: string, slug: string}|null $tenant Tenant identity
     * @return array{filename: string, content: string}
     */
    private function buildExport(
        array $backup,
        string $scope,
        ?array $tenant,
        string $dek,
        string $filename,
    ): array {
        $payload = [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'scope' => $scope,
            'backup_id' => (string)$backup['id'],
            'tenant' => $tenant,
            'backup_type' => (string)$backup['backup_type'],
            'encryption_algorithm' => (string)$backup['encryption_algorithm'],
            'archive' => [
                'sha256' => (string)$backup['object_sha256'],
                'size_bytes' => (int)$backup['object_size_bytes'],
            ],
            'data_encryption_key' => [
                'encoding' => 'base64',
                'value' => base64_encode($dek),
            ],
            'exported_at' => gmdate(DATE_ATOM),
            'warning' => 'This file can decrypt its matching backup archive. ' .
                'Store it separately and protect it as a secret.',
        ];

        try {
            $content = json_encode(
                $payload,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ) . PHP_EOL;
        } catch (JsonException $e) {
            throw new RuntimeException('Could not encode the backup recovery key.', previous: $e);
        }

        return compact('filename', 'content');
    }

    /**
     * @return array{
     *   scope: string,
     *   backup_id: string,
     *   tenant: array<string, mixed>|null,
     *   backup_type: string,
     *   encryption_algorithm: string,
     *   archive: array{sha256: string, size_bytes: int},
     *   data_encryption_key: array{encoding: string, value: string}
     * }
     */
    private function parsePackage(string $json): array
    {
        if ($json === '' || strlen($json) > self::MAX_KEY_FILE_BYTES) {
            throw new RuntimeException('The backup recovery-key file is empty or too large.');
        }
        try {
            $package = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('The backup recovery-key file is not valid JSON.', previous: $e);
        }
        if (
            !is_array($package)
            || ($package['format'] ?? null) !== self::FORMAT
            || ($package['version'] ?? null) !== self::VERSION
        ) {
            throw new RuntimeException('The backup recovery-key format is not supported.');
        }
        if (
            !is_string($package['scope'] ?? null)
            || !is_string($package['backup_id'] ?? null)
            || !preg_match(
                '/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i',
                $package['backup_id'],
            )
            || !is_string($package['backup_type'] ?? null)
            || !is_string($package['encryption_algorithm'] ?? null)
            || !is_array($package['archive'] ?? null)
            || !is_array($package['data_encryption_key'] ?? null)
        ) {
            throw new RuntimeException('The backup recovery-key metadata is incomplete.');
        }
        $archive = $package['archive'];
        if (
            !is_string($archive['sha256'] ?? null)
            || !preg_match('/\A[0-9a-f]{64}\z/', $archive['sha256'])
            || !is_int($archive['size_bytes'] ?? null)
            || $archive['size_bytes'] <= 0
        ) {
            throw new RuntimeException('The backup recovery-key archive identity is invalid.');
        }
        $key = $package['data_encryption_key'];
        if (($key['encoding'] ?? null) !== 'base64' || !is_string($key['value'] ?? null)) {
            throw new RuntimeException('The backup recovery-key material is invalid.');
        }

        return [
            'scope' => $package['scope'],
            'backup_id' => $package['backup_id'],
            'tenant' => is_array($package['tenant'] ?? null) ? $package['tenant'] : null,
            'backup_type' => $package['backup_type'],
            'encryption_algorithm' => $package['encryption_algorithm'],
            'archive' => [
                'sha256' => $archive['sha256'],
                'size_bytes' => $archive['size_bytes'],
            ],
            'data_encryption_key' => [
                'encoding' => $key['encoding'],
                'value' => $key['value'],
            ],
        ];
    }
}
