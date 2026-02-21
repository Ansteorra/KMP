<?php

declare(strict_types=1);

namespace App\Command;

use App\Services\BackupService;
use App\Services\BackupStorageService;
use App\Services\RestoreStatusService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

/**
 * CLI backup management: create and restore backups.
 *
 * Usage:
 *   bin/cake backup create --key "my-secret-key"
 *   bin/cake backup restore kmp-backup-20260220-120000.kmpbackup --key "my-secret-key"
 */
class BackupCommand extends Command
{
    public static function defaultName(): string
    {
        return 'backup';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('Create or restore encrypted database backups')
            ->addArgument('action', [
                'help' => 'Action to perform: create or restore',
                'required' => true,
                'choices' => ['create', 'restore'],
            ])
            ->addArgument('file', [
                'help' => 'Backup filename (required for restore)',
                'required' => false,
            ])
            ->addOption('key', [
                'help' => 'Encryption key (or set Backup.encryptionKey in AppSettings)',
                'short' => 'k',
            ]);

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $action = $args->getArgument('action');

        // Resolve encryption key
        $key = $args->getOption('key');
        if (empty($key)) {
            $appSettings = $this->fetchTable('AppSettings');
            $key = $appSettings->getSetting('Backup.encryptionKey');
        }

        if (empty($key)) {
            $io->error('No encryption key provided. Use --key or set Backup.encryptionKey in App Settings.');

            return self::CODE_ERROR;
        }

        return match ($action) {
            'create' => $this->doCreate($key, $io),
            'restore' => $this->doRestore($key, $args, $io),
            default => self::CODE_ERROR,
        };
    }

    private function doCreate(string $key, ConsoleIo $io): int
    {
        $backupService = new BackupService();
        $storage = new BackupStorageService();
        $backupsTable = $this->fetchTable('Backups');

        $filename = 'kmp-backup-' . date('Ymd-His') . '.kmpbackup';
        $io->out("Creating backup: {$filename}");

        $backup = $backupsTable->newEntity([
            'filename' => $filename,
            'storage_type' => $storage->getAdapterType(),
            'status' => 'running',
            'notes' => 'CLI backup',
        ]);
        $backupsTable->save($backup);

        try {
            $result = $backupService->export($key);
            $storage->write($filename, $result['data']);

            $backup->size_bytes = $result['meta']['size_bytes'];
            $backup->table_count = $result['meta']['table_count'];
            $backup->row_count = $result['meta']['row_count'];
            $backup->status = 'completed';
            $backupsTable->save($backup);

            $io->success(sprintf(
                'Backup created: %s (%d tables, %s rows, %s)',
                $filename,
                $result['meta']['table_count'],
                number_format($result['meta']['row_count']),
                $this->formatBytes($result['meta']['size_bytes']),
            ));

            return self::CODE_SUCCESS;
        } catch (\Exception $e) {
            $backup->status = 'failed';
            $backup->notes = $e->getMessage();
            $backupsTable->save($backup);
            $io->error('Backup failed: ' . $e->getMessage());

            return self::CODE_ERROR;
        }
    }

    private function doRestore(string $key, Arguments $args, ConsoleIo $io): int
    {
        $filename = $args->getArgument('file');
        if (empty($filename)) {
            $io->error('Filename required for restore. Usage: bin/cake backup restore <filename> --key "..."');

            return self::CODE_ERROR;
        }

        $storage = new BackupStorageService();
        if (!$storage->exists($filename)) {
            $io->error("Backup file not found: {$filename}");

            return self::CODE_ERROR;
        }

        $io->warning('⚠️  This will REPLACE ALL current data with the backup contents.');
        $confirm = $io->ask('Type "RESTORE" to confirm:');
        if ($confirm !== 'RESTORE') {
            $io->out('Restore cancelled.');

            return self::CODE_SUCCESS;
        }

        $backupService = new BackupService();
        $restoreStatusService = new RestoreStatusService();

        if (!$restoreStatusService->acquireLock([
            'source' => $filename,
            'actor' => 'cli',
            'message' => sprintf('CLI restore starting from %s.', $filename),
        ])) {
            $status = $restoreStatusService->getStatus();
            $io->error((string)($status['message'] ?? 'A restore/import is already running.'));

            return self::CODE_ERROR;
        }

        try {
            $data = $storage->read($filename);
            $restoreStatusService->updateStatus('starting', sprintf('CLI restore started from %s.', $filename), [
                'source' => $filename,
                'actor' => 'cli',
            ]);
            $io->out('Decrypting and restoring...');

            $lastPhase = null;
            $stats = $backupService->import(
                $data,
                $key,
                function (array $progress) use ($restoreStatusService, $filename, $io, &$lastPhase): void {
                    $phase = (string)($progress['phase'] ?? 'running');
                    $message = (string)($progress['message'] ?? 'Restore in progress.');
                    unset($progress['phase'], $progress['message']);

                    $restoreStatusService->updateStatus($phase, $message, array_merge($progress, [
                        'source' => $filename,
                        'actor' => 'cli',
                    ]));

                    if ($phase === 'table_restored' && isset($progress['tables_processed'], $progress['table_count'], $progress['rows_processed'])) {
                        $io->out(sprintf(
                            'Progress: %d/%d tables, %s rows.',
                            (int)$progress['tables_processed'],
                            (int)$progress['table_count'],
                            number_format((int)$progress['rows_processed']),
                        ));

                        return;
                    }

                    if ($phase !== $lastPhase) {
                        $io->out($message);
                        $lastPhase = $phase;
                    }
                },
            );

            $restoreStatusService->markCompleted(sprintf(
                'CLI restore completed from %s: %d tables, %d rows.',
                $filename,
                $stats['table_count'],
                $stats['row_count'],
            ), [
                'source' => $filename,
                'actor' => 'cli',
                'table_count' => $stats['table_count'],
                'tables_processed' => $stats['table_count'],
                'row_count' => $stats['row_count'],
                'rows_processed' => $stats['row_count'],
            ]);

            $io->success(sprintf(
                'Restore completed: %d tables, %s rows',
                $stats['table_count'],
                number_format($stats['row_count']),
            ));

            return self::CODE_SUCCESS;
        } catch (\Exception $e) {
            $restoreStatusService->markFailed('CLI restore failed: ' . $e->getMessage(), [
                'source' => $filename,
                'actor' => 'cli',
            ]);
            $io->error('Restore failed: ' . $e->getMessage());

            return self::CODE_ERROR;
        } finally {
            $restoreStatusService->releaseLock();
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $size = (float)$bytes;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, 1) . ' ' . $units[$i];
    }
}
