<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Backups;

use App\KMP\TenantMetadata;
use App\Services\Backups\BackupRecoveryKeyService;
use App\Services\Backups\PlatformDatabaseBackupEncryptor;
use App\Services\Backups\PlatformDatabaseBackupService;
use App\Services\Backups\TenantBackupEncryptor;
use App\Services\Backups\TenantBackupService;
use App\Services\Secrets\SensitiveString;
use App\Test\TestCase\Support\ArraySecretStore;
use Cake\TestSuite\TestCase;
use RuntimeException;

/**
 * @covers \App\Services\Backups\BackupRecoveryKeyService
 */
class BackupRecoveryKeyServiceTest extends TestCase
{
    private const TENANT_ID = '11111111-1111-4111-8111-111111111111';
    private const BACKUP_ID = '22222222-2222-4222-8222-222222222222';
    private const PLATFORM_BACKUP_ID = '33333333-3333-4333-8333-333333333333';

    /**
     * @var list<string>
     */
    private array $temporaryPaths = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryPaths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    public function testTenantExportCanOpenOnlyItsMatchingManagedArchive(): void
    {
        $tenant = $this->tenant();
        $kekValue = 'tenant-kek-must-never-be-exported';
        $plaintext = gzencode('{"meta":{"version":2},"tables":{}}');
        $this->assertIsString($plaintext);
        [$encryptedPath, $backup] = $this->tenantBackup($tenant, $plaintext, $kekValue);

        $export = (new BackupRecoveryKeyService())->exportTenant(
            $backup,
            $tenant,
            new ArraySecretStore(['tenant.demo.kek' => $kekValue]),
        );
        $package = json_decode($export['content'], true, 16, JSON_THROW_ON_ERROR);

        $this->assertSame('demo-' . self::BACKUP_ID . '.kmpbackup-key.json', $export['filename']);
        $this->assertSame(BackupRecoveryKeyService::FORMAT, $package['format']);
        $this->assertSame('tenant', $package['scope']);
        $this->assertSame(self::BACKUP_ID, $package['backup_id']);
        $this->assertSame(['id' => self::TENANT_ID, 'slug' => 'demo'], $package['tenant']);
        $this->assertStringNotContainsString($kekValue, $export['content']);
        $this->assertStringNotContainsString((string)$backup['wrapped_dek'], $export['content']);

        $restored = (new BackupRecoveryKeyService())->decryptTenantArchive(
            (string)file_get_contents($encryptedPath),
            $export['content'],
            'demo',
        );
        $this->assertSame($plaintext, $restored);
    }

    public function testTenantRecoveryKeyRejectsDifferentTenantContext(): void
    {
        $tenant = $this->tenant();
        $kekValue = 'tenant-kek';
        [$encryptedPath, $backup] = $this->tenantBackup($tenant, 'logical archive', $kekValue);
        $export = (new BackupRecoveryKeyService())->exportTenant(
            $backup,
            $tenant,
            new ArraySecretStore(['tenant.demo.kek' => $kekValue]),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('different tenant');

        (new BackupRecoveryKeyService())->decryptTenantArchive(
            (string)file_get_contents($encryptedPath),
            $export['content'],
            'another-tenant',
        );
    }

    public function testTenantRecoveryKeyRejectsDifferentArchive(): void
    {
        $tenant = $this->tenant();
        $kekValue = 'tenant-kek';
        [$encryptedPath, $backup] = $this->tenantBackup($tenant, 'logical archive', $kekValue);
        $export = (new BackupRecoveryKeyService())->exportTenant(
            $backup,
            $tenant,
            new ArraySecretStore(['tenant.demo.kek' => $kekValue]),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not match');

        (new BackupRecoveryKeyService())->decryptTenantArchive(
            (string)file_get_contents($encryptedPath) . 'tampered',
            $export['content'],
            'demo',
        );
    }

    public function testPlatformExportContainsOnlyTheBackupScopedDataKey(): void
    {
        $inputPath = $this->temporaryPath('platform-plaintext-');
        $encryptedPath = $this->temporaryPath('platform-encrypted-');
        file_put_contents($inputPath, 'platform database dump');
        $kekValue = 'platform-kek-must-never-be-exported';
        $encryption = (new PlatformDatabaseBackupEncryptor())->encryptFile(
            $inputPath,
            $encryptedPath,
            self::PLATFORM_BACKUP_ID,
            new SensitiveString($kekValue),
            PlatformDatabaseBackupService::KEK_SECRET_NAME,
            'unversioned',
        );
        $backup = [
            'id' => self::PLATFORM_BACKUP_ID,
            'backup_type' => PlatformDatabaseBackupService::BACKUP_TYPE,
            'status' => 'completed',
            'object_size_bytes' => filesize($encryptedPath),
            'object_sha256' => hash_file('sha256', $encryptedPath),
            'encryption_algorithm' => $encryption->algorithm,
            'wrapped_dek' => $encryption->wrappedDek,
            'wrapped_dek_key_name' => PlatformDatabaseBackupService::KEK_SECRET_NAME,
            'wrapped_dek_metadata' => json_encode($encryption->wrappedDekMetadata, JSON_THROW_ON_ERROR),
        ];

        $service = new BackupRecoveryKeyService();
        $export = $service->exportPlatform(
            $backup,
            new ArraySecretStore([PlatformDatabaseBackupService::KEK_SECRET_NAME => $kekValue]),
        );
        $package = json_decode($export['content'], true, 16, JSON_THROW_ON_ERROR);

        $this->assertSame(
            'platform-' . self::PLATFORM_BACKUP_ID . '.kmpbackup-key.json',
            $export['filename'],
        );
        $this->assertSame('platform', $package['scope']);
        $this->assertNull($package['tenant']);
        $this->assertStringNotContainsString($kekValue, $export['content']);
        $this->assertStringNotContainsString($encryption->wrappedDek, $export['content']);
        $this->assertSame(SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES, strlen(
            (string)base64_decode($package['data_encryption_key']['value'], true),
        ));

        $outputPath = TMP . 'platform-recovery-output-' . uniqid('', true) . '.pgdump';
        $this->temporaryPaths[] = $outputPath;
        $service->decryptPlatformArchiveFile($encryptedPath, $export['content'], $outputPath);
        $this->assertSame('platform database dump', file_get_contents($outputPath));
        $this->assertSame(0600, fileperms($outputPath) & 0777);
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function tenantBackup(TenantMetadata $tenant, string $plaintext, string $kekValue): array
    {
        $inputPath = $this->temporaryPath('tenant-plaintext-');
        $encryptedPath = $this->temporaryPath('tenant-encrypted-');
        file_put_contents($inputPath, $plaintext);
        $encryption = (new TenantBackupEncryptor())->encryptFile(
            $inputPath,
            $encryptedPath,
            $tenant,
            self::BACKUP_ID,
            new SensitiveString($kekValue),
            'tenant.demo.kek',
            'unversioned',
        );

        return [
            $encryptedPath,
            [
                'id' => self::BACKUP_ID,
                'tenant_id' => self::TENANT_ID,
                'backup_type' => TenantBackupService::BACKUP_TYPE,
                'status' => 'completed',
                'object_size_bytes' => filesize($encryptedPath),
                'object_sha256' => hash_file('sha256', $encryptedPath),
                'encryption_algorithm' => $encryption->algorithm,
                'wrapped_dek' => $encryption->wrappedDek,
                'wrapped_dek_key_name' => 'tenant.demo.kek',
                'wrapped_dek_metadata' => json_encode($encryption->wrappedDekMetadata, JSON_THROW_ON_ERROR),
            ],
        ];
    }

    private function tenant(): TenantMetadata
    {
        return new TenantMetadata(
            self::TENANT_ID,
            'demo',
            'Demo Kingdom',
            'active',
            'db',
            'demo',
            'demo_role',
        );
    }

    private function temporaryPath(string $prefix): string
    {
        $path = tempnam(TMP, $prefix);
        $this->assertIsString($path);
        $this->temporaryPaths[] = $path;

        return $path;
    }
}
