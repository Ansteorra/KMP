<?php

declare(strict_types=1);

namespace App\Command;

use App\Services\BackupService;
use App\Services\BackupStorageService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\I18n\DateTime;
use Cake\Log\Log;

/**
 * Scheduled backup check — runs from cron, creates a backup if the schedule is due.
 *
 * Checks Backup.schedule AppSetting (daily/weekly/disabled) and creates a backup
 * if no successful backup exists within the schedule window. Also enforces retention.
 */
class BackupCheckCommand extends Command
{
    public static function defaultName(): string
    {
        return 'backup_check';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser->setDescription('Check backup schedule and create backup if due');

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $appSettings = $this->fetchTable('AppSettings');
        $schedule = $appSettings->getAppSetting('Backup.schedule', 'disabled', 'string', false);

        if ($schedule === 'disabled') {
            return self::CODE_SUCCESS;
        }

        $encryptionKey = $appSettings->getAppSetting('Backup.encryptionKey', '', 'string', false);
        if (empty($encryptionKey)) {
            $io->out('Backup schedule active but no encryption key set — skipping.');

            return self::CODE_SUCCESS;
        }

        // Check if a backup is already due
        $backupsTable = $this->fetchTable('Backups');
        $since = match ($schedule) {
            'daily' => new DateTime('-24 hours'),
            'weekly' => new DateTime('-7 days'),
            default => null,
        };

        if ($since === null) {
            return self::CODE_SUCCESS;
        }

        $recentBackup = $backupsTable->find()
            ->where([
                'created >=' => $since,
                'status' => 'completed',
            ])
            ->first();

        if ($recentBackup !== null) {
            $io->out("Recent backup exists ({$recentBackup->filename}) — skipping.");

            return self::CODE_SUCCESS;
        }

        // Create backup
        $storage = new BackupStorageService();
        $backupService = new BackupService();

        $filename = 'kmp-backup-' . date('Ymd-His') . '.kmpbackup';
        $io->out("Scheduled backup: {$filename}");

        $backup = $backupsTable->newEntity([
            'filename' => $filename,
            'storage_type' => $storage->getAdapterType(),
            'status' => 'running',
            'notes' => "Scheduled ({$schedule})",
        ]);
        $backupsTable->save($backup);

        try {
            $result = $backupService->export($encryptionKey);
            $storage->write($filename, $result['data']);

            $backup->size_bytes = $result['meta']['size_bytes'];
            $backup->table_count = $result['meta']['table_count'];
            $backup->row_count = $result['meta']['row_count'];
            $backup->status = 'completed';
            $backupsTable->save($backup);

            $io->success("Backup completed: {$filename}");
            Log::info("Scheduled backup completed: {$filename}");
        } catch (\Exception $e) {
            $backup->status = 'failed';
            $backup->notes = $e->getMessage();
            $backupsTable->save($backup);

            $io->error('Scheduled backup failed: ' . $e->getMessage());
            Log::error('Scheduled backup failed: ' . $e->getMessage());

            return self::CODE_ERROR;
        }

        // Retention cleanup
        $retentionDays = (int)$appSettings->getAppSetting('Backup.retentionDays', '30', 'string', false);
        if ($retentionDays > 0) {
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
                    $io->out("Retention cleanup: deleted {$old->filename}");
                } catch (\Exception $e) {
                    Log::warning("Retention cleanup failed for {$old->filename}: " . $e->getMessage());
                }
            }
        }

        return self::CODE_SUCCESS;
    }
}
