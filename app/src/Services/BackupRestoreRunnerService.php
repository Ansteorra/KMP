<?php
declare(strict_types=1);

namespace App\Services;

use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use RuntimeException;
use Throwable;

/**
 * Runs staged backup restores from worker or CLI entry points.
 */
class BackupRestoreRunnerService
{
    /**
     * Consume a staged restore payload and execute the restore workflow.
     *
     * @param callable|null $log Callback receiving each user-visible restore log line.
     * @param callable $migrationRunner Callback that runs post-schema-reset migrations.
     * @return array{table_count: int, row_count: int}
     */
    public function run(string $token, string $restoreId, ?callable $log, callable $migrationRunner): array
    {
        $restoreStatusService = new RestoreStatusService();
        $ownsRestoreLock = $restoreId !== '' && $this->restoreIdMatchesCurrentLock($restoreStatusService, $restoreId);
        $context = [
            'restore_id' => $restoreId,
        ];
        $maintenanceRequired = false;

        try {
            if ($restoreStatusService->isLocked() && !$ownsRestoreLock) {
                throw new RuntimeException('Restore lock belongs to a different restore operation.');
            }

            $stagingService = new RestoreStagingService();
            $staged = $stagingService->consume($token);
            $context = $staged['context'];
            if ($restoreId !== '' && (string)($context['restore_id'] ?? '') !== $restoreId) {
                throw new RuntimeException('Restore staging token does not match the active restore.');
            }

            if (!$restoreStatusService->isLocked()) {
                if (
                    !$restoreStatusService->acquireLock(array_merge($context, [
                        'message' => 'Restore worker recovered missing restore lock.',
                    ]))
                ) {
                    throw new RuntimeException('Unable to acquire restore lock for worker.');
                }
            }
            $ownsRestoreLock = true;

            $this->recordProgress(
                $restoreStatusService,
                'starting',
                'Backup restore worker started.',
                $context,
                $log,
            );

            $lastPhase = null;
            $stats = (new BackupService())->import(
                $staged['encrypted_data'],
                $staged['encryption_key'],
                function (array $progress) use (
                    $restoreStatusService,
                    $context,
                    $log,
                    &$lastPhase,
                    &$maintenanceRequired,
                ): void {
                    $phase = (string)($progress['phase'] ?? 'running');
                    $message = (string)($progress['message'] ?? 'Restore in progress.');
                    unset($progress['phase'], $progress['message']);
                    if (!in_array($phase, ['decrypting', 'decompressing', 'validating'], true)) {
                        $maintenanceRequired = true;
                    }

                    $restoreStatusService->updateStatus($phase, $message, array_merge($progress, $context));
                    if (
                        $phase === 'table_restored'
                        && isset($progress['tables_processed'], $progress['table_count'], $progress['rows_processed'])
                    ) {
                        $this->appendLog(
                            $restoreStatusService,
                            sprintf(
                                'Progress: %d/%d tables, %s rows.',
                                (int)$progress['tables_processed'],
                                (int)$progress['table_count'],
                                number_format((int)$progress['rows_processed']),
                            ),
                            $log,
                        );

                        return;
                    }
                    if ($phase !== $lastPhase) {
                        $this->appendLog($restoreStatusService, $message, $log);
                        $lastPhase = $phase;
                    }
                },
                $migrationRunner,
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
            $this->appendLog($restoreStatusService, 'Restore completed successfully.', $log);
            $stagingService->discard($token);

            return $stats;
        } catch (Throwable $e) {
            if ($ownsRestoreLock) {
                $this->updateTrackedBackupStatus($context, 'failed', sprintf(
                    'Restore failed at %s: %s',
                    DateTime::now()->format('Y-m-d H:i:s'),
                    $e->getMessage(),
                ));
                $restoreStatusService->markFailed('Restore/import failed: ' . $e->getMessage(), array_merge(
                    $context,
                    ['maintenance_required' => $maintenanceRequired],
                ));
                $this->appendLog($restoreStatusService, 'Restore failed: ' . $e->getMessage(), $log);
            }

            throw $e;
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

        return !empty($status['locked'])
            && ($status['status'] ?? null) === 'running'
            && (string)($status['restore_id'] ?? '') === $restoreId;
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

    /**
     * Record a phase update and append it to the visible restore log.
     *
     * @param array<string, mixed> $context
     */
    private function recordProgress(
        RestoreStatusService $restoreStatusService,
        string $phase,
        string $message,
        array $context,
        ?callable $log,
    ): void {
        $restoreStatusService->updateStatus($phase, $message, $context);
        $this->appendLog($restoreStatusService, $message, $log);
    }

    /**
     * Append a bounded log line to restore status and the caller log.
     */
    private function appendLog(RestoreStatusService $restoreStatusService, string $message, ?callable $log): void
    {
        $restoreStatusService->appendLog($message);
        if ($log !== null) {
            $log($message);
        }
    }
}
