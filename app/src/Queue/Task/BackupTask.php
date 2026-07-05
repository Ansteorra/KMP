<?php
declare(strict_types=1);

namespace App\Queue\Task;

use App\Services\BackupService;
use App\Services\BackupStorageService;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;
use Exception;
use Queue\Queue\Task;

/**
 * Scheduled backup queue task.
 *
 * Triggered by cron to create encrypted backups on a schedule.
 */
class BackupTask extends Task
{
    use LocatorAwareTrait;

    public ?int $timeout = 600; // 10 minutes max
    public ?int $retries = 1;

    /**
     * @param array<string, mixed> $data Optional manual backup data
     * @param int $jobId Queue job ID
     */
    public function run(array $data, int $jobId): void
    {
        $appSettings = $this->fetchTable('AppSettings');
        $encryptionKey = $appSettings->getSetting('Backup.encryptionKey');

        $backupsTable = $this->fetchTable('Backups');
        $storage = new BackupStorageService();
        $backupService = new BackupService();
        $backupId = $data['backup_id'] ?? null;

        if (empty($encryptionKey) && $backupId === null) {
            Log::warning('Scheduled backup skipped - no encryption key configured');

            return;
        }

        if ($backupId !== null) {
            $backup = $backupsTable->get((int)$backupId);
            $backup->status = 'running';
            $backup->notes = 'Manual backup running.';
            $backupsTable->save($backup);
        } else {
            $backup = $backupsTable->newEntity([
                'filename' => 'kmp-backup-' . date('Ymd-His') . '.kmpbackup',
                'storage_type' => $storage->getAdapterType(),
                'status' => 'running',
                'notes' => 'Scheduled backup',
            ]);
            $backupsTable->save($backup);
        }

        if (empty($encryptionKey)) {
            $backup->status = 'failed';
            $backup->notes = 'No encryption key configured.';
            $backupsTable->save($backup);
            Log::warning('Backup skipped - no encryption key configured');

            return;
        }

        try {
            $result = $backupService->export($encryptionKey);
            $storage->write($backup->filename, $result['data']);

            $backup->size_bytes = $result['meta']['size_bytes'];
            $backup->table_count = $result['meta']['table_count'];
            $backup->row_count = $result['meta']['row_count'];
            $backup->status = 'completed';
            $backupsTable->save($backup);

            Log::info("Backup completed: {$backup->filename}");

            if ($backupId === null) {
                $this->cleanOldBackups($backupsTable, $storage, $appSettings);
            }
        } catch (Exception $e) {
            $backup->status = 'failed';
            $backup->notes = $e->getMessage();
            $backupsTable->save($backup);

            Log::error('Scheduled backup failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete backups older than the configured retention period.
     */
    private function cleanOldBackups($backupsTable, BackupStorageService $storage, $appSettings): void
    {
        $retentionDays = (int)$appSettings->getAppSetting('Backup.retentionDays', '30', 'string', false);
        if ($retentionDays <= 0) {
            return;
        }

        $cutoff = new DateTime("-{$retentionDays} days");
        $oldBackups = $backupsTable->find()
            ->where(['created <' => $cutoff, 'status' => 'completed'])
            ->all();

        foreach ($oldBackups as $old) {
            try {
                if ($storage->exists($old->filename)) {
                    $storage->delete($old->filename);
                }
                $backupsTable->delete($old);
                Log::info("Retention cleanup: deleted {$old->filename}");
            } catch (Exception $e) {
                Log::warning("Failed to clean old backup {$old->filename}: " . $e->getMessage());
            }
        }
    }
}
