<?php
declare(strict_types=1);

namespace App\Services\Backups;

use App\Services\BackupStorageService;
use Cake\Core\Configure;

final class BackupStorageFactory
{
    /**
     * Build configured tenant archive storage.
     */
    public static function tenant(): TenantBackupStorageInterface
    {
        return new RoutedTenantBackupStorage(
            new ConfiguredTenantBackupStorage(self::objects()),
            self::localTenant(),
        );
    }

    /**
     * Build configured platform archive storage.
     */
    public static function platform(): PlatformDatabaseBackupStorageInterface
    {
        return new RoutedPlatformDatabaseBackupStorage(
            new ConfiguredPlatformDatabaseBackupStorage(self::objects()),
            self::localPlatform(),
        );
    }

    /**
     * Build storage for a tenant archive type.
     */
    public static function tenantArchive(string $backupType): BackupArchiveStorageInterface
    {
        if ($backupType === TenantBackupService::LEGACY_BACKUP_TYPE) {
            return self::legacy();
        }

        return self::tenant();
    }

    /**
     * Build storage for a platform archive type.
     */
    public static function platformArchive(string $backupType): BackupArchiveStorageInterface
    {
        if ($backupType === TenantBackupService::LEGACY_BACKUP_TYPE) {
            return self::legacy();
        }

        return self::platform();
    }

    /**
     * Build the legacy .kmpbackup storage adapter.
     */
    public static function legacy(): BackupArchiveStorageInterface
    {
        return new LegacyKmpBackupStorage(new BackupStorageService());
    }

    /**
     * Build the shared configured object-storage adapter.
     */
    private static function objects(): BackupObjectStorage
    {
        return new BackupObjectStorage(new BackupStorageService());
    }

    /**
     * Build the former local tenant adapter for historical local:// records.
     */
    private static function localTenant(): TenantBackupStorageInterface
    {
        $enabled = (bool)Configure::read('TenantBackups.local.enabled', false);
        if (env('KMP_LOCAL_BACKUPS_ENABLED', null) !== null) {
            $enabled = filter_var(env('KMP_LOCAL_BACKUPS_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
        }
        $root = (string)Configure::read('TenantBackups.local.path', TMP . 'backups');
        $configuredRoot = env('KMP_LOCAL_BACKUPS_PATH', null);
        if (is_string($configuredRoot) && $configuredRoot !== '') {
            $root = $configuredRoot;
        }

        return new LocalTenantBackupStorage($root, $enabled);
    }

    /**
     * Build the former local platform adapter for historical local:// records.
     */
    private static function localPlatform(): PlatformDatabaseBackupStorageInterface
    {
        $enabled = (bool)Configure::read('PlatformBackups.local.enabled', false);
        if (env('KMP_PLATFORM_LOCAL_BACKUPS_ENABLED', null) !== null) {
            $enabled = filter_var(env('KMP_PLATFORM_LOCAL_BACKUPS_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
        }
        $root = (string)Configure::read('PlatformBackups.local.path', TMP . 'platform-backups');
        $configuredRoot = env('KMP_PLATFORM_LOCAL_BACKUPS_PATH', null);
        if (is_string($configuredRoot) && $configuredRoot !== '') {
            $root = $configuredRoot;
        }

        return new LocalPlatformDatabaseBackupStorage(
            $root,
            $enabled,
            (string)env('KMP_ENV', env('APP_ENV', 'production')),
        );
    }
}
