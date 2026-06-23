<?php
declare(strict_types=1);

namespace App\Command;

use App\Services\BackupService;
use App\Services\RestoreStagingService;
use App\Services\RestoreStatusService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use RuntimeException;
use Throwable;

/**
 * Internal restore runner for web-initiated background restores.
 */
class BackupRestoreRunCommand extends Command
{
    /**
     * Return the internal restore runner command name.
     */
    public static function defaultName(): string
    {
        return 'backup_restore_run';
    }

    /**
     * Configure the restore token and ownership arguments.
     *
     * @param \Cake\Console\ConsoleOptionParser $parser Console option parser
     * @return \Cake\Console\ConsoleOptionParser
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser
            ->setDescription('Internal command that runs a staged backup restore.')
            ->addArgument('token', [
                'help' => 'Restore staging token',
                'required' => true,
            ])
            ->addArgument('restore_id', [
                'help' => 'Restore ownership identifier',
                'required' => true,
            ]);
    }

    /**
     * Run a staged restore and return the command exit code.
     *
     * @param \Cake\Console\Arguments $args Command arguments
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return int|null
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $token = (string)$args->getArgument('token');
        $restoreId = (string)($args->getArgument('restore_id') ?? '');
        $restoreStatusService = new RestoreStatusService();
        $ownsRestoreLock = $restoreId !== '' && $this->restoreIdMatchesCurrentLock($restoreStatusService, $restoreId);
        $context = [
            'restore_id' => $restoreId,
        ];

        try {
            if ($restoreStatusService->isLocked() && !$ownsRestoreLock) {
                throw new RuntimeException('Restore lock belongs to a different restore operation.');
            }

            $staged = (new RestoreStagingService())->consume($token);
            $context = $staged['context'];
            if (
                $restoreId !== ''
                && (string)($context['restore_id'] ?? '') !== $restoreId
            ) {
                throw new RuntimeException('Restore staging token does not match the active restore.');
            }

            if (!$restoreStatusService->isLocked()) {
                if (!$restoreStatusService->acquireLock(array_merge($context, [
                    'message' => 'Restore runner recovered missing restore lock.',
                ]))) {
                    throw new RuntimeException('Unable to acquire restore lock for runner.');
                }
                $ownsRestoreLock = true;
            } else {
                $ownsRestoreLock = true;
            }

            $restoreStatusService->updateStatus('starting', 'Background restore runner started.', $context);
            $lastPhase = null;
            $stats = (new BackupService())->import(
                $staged['encrypted_data'],
                $staged['encryption_key'],
                function (array $progress) use ($restoreStatusService, $context, $io, &$lastPhase): void {
                    $phase = (string)($progress['phase'] ?? 'running');
                    $message = (string)($progress['message'] ?? 'Restore in progress.');
                    unset($progress['phase'], $progress['message']);
                    $restoreStatusService->updateStatus($phase, $message, array_merge($progress, $context));

                    if ($phase !== $lastPhase) {
                        $io->out($message);
                        $lastPhase = $phase;
                    }
                },
                function () use ($io): void {
                    $exitCode = $this->executeCommand(UpdateDatabaseCommand::class, [], $io);
                    if ($exitCode !== null && $exitCode !== self::CODE_SUCCESS) {
                        throw new RuntimeException('Database migrations failed during restore.');
                    }
                },
            );

            $this->updateTrackedBackupStatus($context, 'completed', sprintf(
                'Restore completed at %s: %d tables, %d rows.',
                DateTime::now()->format('Y-m-d H:i:s'),
                $stats['table_count'],
                $stats['row_count'],
            ), $stats);
            $restoreStatusService->markCompleted(sprintf(
                'Restore/import completed: %d tables, %d rows.',
                $stats['table_count'],
                $stats['row_count'],
            ), array_merge($context, [
                'table_count' => $stats['table_count'],
                'tables_processed' => $stats['table_count'],
                'row_count' => $stats['row_count'],
                'rows_processed' => $stats['row_count'],
            ]));

            return self::CODE_SUCCESS;
        } catch (Throwable $e) {
            if ($ownsRestoreLock) {
                $this->updateTrackedBackupStatus($context, 'failed', sprintf(
                    'Restore failed at %s: %s',
                    DateTime::now()->format('Y-m-d H:i:s'),
                    $e->getMessage(),
                ));
                $restoreStatusService->markFailed('Restore/import failed: ' . $e->getMessage());
            }
            $io->error('Restore failed: ' . $e->getMessage());

            return self::CODE_ERROR;
        } finally {
            if ($ownsRestoreLock) {
                $restoreStatusService->releaseLock();
            }
        }
    }

    /**
     * Check whether the active lock belongs to this restore runner.
     */
    private function restoreIdMatchesCurrentLock(RestoreStatusService $restoreStatusService, string $restoreId): bool
    {
        $status = $restoreStatusService->getStatus();

        return (string)($status['restore_id'] ?? '') === $restoreId;
    }

    /**
     * Persist final async restore status to the tracked backup row when available.
     *
     * @param array<string, mixed> $context
     * @param array{table_count: int, row_count: int}|null $stats
     */
    private function updateTrackedBackupStatus(
        array $context,
        string $status,
        string $notes,
        ?array $stats = null,
    ): void {
        $backupId = $context['backup_id'] ?? null;
        if ($backupId === null || $backupId === '') {
            return;
        }

        try {
            $backups = TableRegistry::getTableLocator()->get('Backups');
            $backup = $backups->find()->where(['id' => $backupId])->first();
            if ($backup === null) {
                $backup = $backups->newEntity([
                    'id' => $backupId,
                    'filename' => (string)($context['source'] ?? 'Restored backup'),
                    'storage_type' => 'local',
                ], ['accessibleFields' => [
                    'id' => true,
                    'filename' => true,
                    'storage_type' => true,
                ]]);
            }

            $backup->status = $status;
            $backup->notes = $notes;
            if ($stats !== null) {
                $backup->table_count = $stats['table_count'];
                $backup->row_count = $stats['row_count'];
            }
            $backups->saveOrFail($backup);
        } catch (Throwable $e) {
            Log::warning('Unable to update backup restore status row: ' . $e->getMessage());
        }
    }
}
