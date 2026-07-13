<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Remove the legacy tenant backup scheduling AppSettings.
 *
 * Backup scheduling and retention are platform responsibilities (global
 * backup policy + tenant-backup-fleet schedule). `Backup.encryptionKey`
 * is deliberately kept: it still opens previously downloaded .kmpbackup
 * files until the legacy backups table is sunset.
 */
class RemoveLegacyBackupScheduleSettings extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(
            "DELETE FROM app_settings WHERE name IN ('Backup.schedule', 'Backup.retentionDays')"
        );
    }

    public function down(): void
    {
        // Settings are recreated on demand with defaults; nothing to restore.
    }
}
