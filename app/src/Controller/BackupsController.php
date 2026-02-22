<?php

declare(strict_types=1);

namespace App\Controller;

use App\Services\BackupService;
use App\Services\BackupStorageService;
use App\Services\RestoreStatusService;
use Cake\Http\Response;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Exception;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Manages database backups: list, create, restore, download, delete, settings.
 */
class BackupsController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->Authorization->authorizeModel('index', 'create', 'restore', 'download', 'delete', 'settings', 'status');
    }

    /**
     * List all backups and show settings panel.
     */
    public function index(): void
    {
        $this->markStaleRunningBackups();

        $backups = $this->paginate($this->Backups, [
            'order' => ['Backups.created' => 'DESC'],
            'limit' => 25,
        ]);

        $appSettings = $this->fetchTable('AppSettings');
        $hasKey = !empty((string)$appSettings->getSetting('Backup.encryptionKey'));
        $schedule = $appSettings->getAppSetting('Backup.schedule', 'disabled', 'string', false);
        $retention = (int)$appSettings->getAppSetting('Backup.retentionDays', '30', 'string', false);

        $storage = new BackupStorageService();
        $restoreStatus = (new RestoreStatusService())->getStatus();

        $this->set(compact('backups', 'hasKey', 'schedule', 'retention', 'restoreStatus'));
        $this->set('storageType', $storage->getAdapterType());
    }

    /**
     * Return current restore lock/progress status for polling clients.
     */
    public function status(): Response
    {
        $this->request->allowMethod(['get']);
        $this->request->getSession()->close();

        $status = (new RestoreStatusService())->getStatus();

        return $this->response
            ->withType('application/json')
            ->withStringBody((string)json_encode($status));
    }

    /**
     * Create a new backup.
     */
    public function create(): ?Response
    {
        $this->request->allowMethod(['post']);

        $appSettings = $this->fetchTable('AppSettings');
        $encryptionKey = (string)$appSettings->getSetting('Backup.encryptionKey');

        if (empty($encryptionKey)) {
            $this->Flash->error(__('Set an encryption key in Backup Settings before creating a backup.'));

            return $this->redirect(['action' => 'index']);
        }

        $storage = new BackupStorageService();
        $backupService = new BackupService();

        $backup = $this->Backups->newEntity([
            'filename' => 'kmp-backup-' . date('Ymd-His') . '.kmpbackup',
            'storage_type' => $storage->getAdapterType(),
            'status' => 'running',
        ]);
        $this->Backups->save($backup);

        try {
            $result = $backupService->export($encryptionKey);
            $storage->write($backup->filename, $result['data']);

            $backup->size_bytes = $result['meta']['size_bytes'];
            $backup->table_count = $result['meta']['table_count'];
            $backup->row_count = $result['meta']['row_count'];
            $backup->status = 'completed';
            $this->Backups->save($backup);

            $this->Flash->success(__('Backup created successfully: {0}', $backup->filename));
        } catch (Exception $e) {
            Log::error('Backup creation failed: ' . $e->getMessage());
            $backup->status = 'failed';
            $backup->notes = $e->getMessage();
            $this->Backups->save($backup);
            $this->Flash->error(__('Backup failed: {0}', $e->getMessage()));
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Restore from an existing backup.
     */
    public function restore(?int $id = null): ?Response
    {
        $this->request->allowMethod(['post']);
        $expectsJson = $this->request->is('ajax') || $this->request->accepts('application/json');
        @set_time_limit(0);
        ignore_user_abort(true);
        $encryptionKey = trim((string)$this->request->getData('restore_key', ''));
        if ($encryptionKey === '') {
            $message = __('Enter the encryption key for this backup restore.');
            if ($expectsJson) {
                return $this->jsonResponse(['success' => false, 'message' => $message], 400);
            }
            $this->Flash->error($message);

            return $this->redirect(['action' => 'index']);
        }

        $backupService = new BackupService();
        $restoreStatusService = new RestoreStatusService();
        $restoreTrackedBackup = null;
        $data = '';
        $sourceLabel = __('backup file');

        if ($id !== null) {
            $backup = $this->Backups->get($id);
            $restoreTrackedBackup = $backup;

            $storage = new BackupStorageService();
            $data = $storage->read($backup->filename);
            $sourceLabel = $backup->filename;
        } else {
            $uploadedFile = $this->request->getData('backup_file');
            if (!$uploadedFile instanceof UploadedFileInterface || $uploadedFile->getError() !== UPLOAD_ERR_OK) {
                $message = __('Choose a valid backup file to import.');
                if ($expectsJson) {
                    return $this->jsonResponse(['success' => false, 'message' => $message], 400);
                }
                $this->Flash->error($message);

                return $this->redirect(['action' => 'index']);
            }

            $stream = $uploadedFile->getStream();
            $stream->rewind();
            $data = $stream->getContents();
            if ($data === '') {
                $message = __('The uploaded backup file was empty.');
                if ($expectsJson) {
                    return $this->jsonResponse(['success' => false, 'message' => $message], 400);
                }
                $this->Flash->error($message);

                return $this->redirect(['action' => 'index']);
            }

            $sourceLabel = $uploadedFile->getClientFilename() ?: __('uploaded backup file');
        }

        $identity = $this->request->getAttribute('identity');
        $actor = is_object($identity) && method_exists($identity, 'getIdentifier') ? (string)$identity->getIdentifier() : null;
        if (!$restoreStatusService->acquireLock([
            'source' => $sourceLabel,
            'backup_id' => $id,
            'actor' => $actor,
            'message' => sprintf('Restore starting from %s.', $sourceLabel),
        ])) {
            $activeStatus = $restoreStatusService->getStatus();
            $activeMessage = (string)($activeStatus['message'] ?? '');
            if ($activeMessage === '') {
                $activeMessage = __('A restore/import is already running.');
            }
            if ($expectsJson) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => $activeMessage,
                    'status' => $activeStatus,
                ], 409);
            }
            $this->Flash->error($activeMessage);

            return $this->redirect(['action' => 'index']);
        }

        try {
            if ($expectsJson) {
                // Release session lock so /backups/status polling can run while restore is in progress.
                $this->request->getSession()->close();
            }

            $restoreStatusService->updateStatus('starting', sprintf('Restore started from %s.', $sourceLabel), [
                'source' => $sourceLabel,
                'backup_id' => $id,
                'actor' => $actor,
            ]);

            if ($restoreTrackedBackup !== null) {
                $restoreTrackedBackup->status = 'running';
                $restoreTrackedBackup->notes = __('Restore started at {0}.', date('Y-m-d H:i:s'));
                $this->Backups->save($restoreTrackedBackup);
            }

            $stats = $backupService->import(
                $data,
                $encryptionKey,
                function (array $progress) use ($restoreStatusService, $sourceLabel, $id, $actor): void {
                    $phase = (string)($progress['phase'] ?? 'running');
                    $message = (string)($progress['message'] ?? 'Restore in progress.');
                    unset($progress['phase'], $progress['message']);

                    $restoreStatusService->updateStatus($phase, $message, array_merge($progress, [
                        'source' => $sourceLabel,
                        'backup_id' => $id,
                        'actor' => $actor,
                    ]));
                },
            );

            if ($restoreTrackedBackup !== null) {
                $restoreTrackedBackup->status = 'completed';
                $restoreTrackedBackup->table_count = $stats['table_count'];
                $restoreTrackedBackup->row_count = $stats['row_count'];
                $restoreTrackedBackup->notes = __(
                    'Restore completed at {0}: {1} tables, {2} rows.',
                    date('Y-m-d H:i:s'),
                    $stats['table_count'],
                    $stats['row_count'],
                );
                $this->Backups->save($restoreTrackedBackup);
            }

            $restoreStatusService->markCompleted(sprintf(
                'Restore/import completed from %s: %d tables, %d rows.',
                $sourceLabel,
                $stats['table_count'],
                $stats['row_count'],
            ), [
                'source' => $sourceLabel,
                'backup_id' => $id,
                'actor' => $actor,
                'table_count' => $stats['table_count'],
                'tables_processed' => $stats['table_count'],
                'row_count' => $stats['row_count'],
                'rows_processed' => $stats['row_count'],
            ]);

            if ($expectsJson) {
                return $this->jsonResponse([
                    'success' => true,
                    'message' => __(
                        'Restore/import completed from {0}: {1} tables, {2} rows',
                        $sourceLabel,
                        $stats['table_count'],
                        $stats['row_count'],
                    ),
                    'stats' => $stats,
                ]);
            }

            $this->Flash->success(__(
                'Restore/import completed from {0}: {1} tables, {2} rows',
                $sourceLabel,
                $stats['table_count'],
                $stats['row_count'],
            ));
        } catch (Exception $e) {
            Log::error('Restore failed: ' . $e->getMessage());

            if ($restoreTrackedBackup !== null) {
                $restoreTrackedBackup->status = 'failed';
                $restoreTrackedBackup->notes = __(
                    'Restore failed at {0}: {1}',
                    date('Y-m-d H:i:s'),
                    $e->getMessage(),
                );
                $this->Backups->save($restoreTrackedBackup);
            }
            $restoreStatusService->markFailed(sprintf('Restore/import failed: %s', $e->getMessage()), [
                'source' => $sourceLabel,
                'backup_id' => $id,
                'actor' => $actor,
            ]);
            if ($expectsJson) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => __('Restore failed: {0}', $e->getMessage()),
                ], 500);
            }
            $this->Flash->error(__('Restore failed: {0}', $e->getMessage()));
        } finally {
            $restoreStatusService->releaseLock();
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Build a JSON response payload for AJAX restore flows.
     *
     * @param array<string, mixed> $payload
     */
    private function jsonResponse(array $payload, int $status = 200): Response
    {
        return $this->response
            ->withType('application/json')
            ->withStatus($status)
            ->withStringBody((string)json_encode($payload));
    }

    /**
     * Download a backup file.
     */
    public function download(int $id): Response
    {
        $backup = $this->Backups->get($id);
        $storage = new BackupStorageService();

        $data = $storage->read($backup->filename);

        $response = $this->response
            ->withType('application/octet-stream')
            ->withDownload($backup->filename)
            ->withStringBody($data);

        return $response;
    }

    /**
     * Delete a backup record and its file.
     */
    public function delete(int $id): ?Response
    {
        $this->request->allowMethod(['post', 'delete']);

        $backup = $this->Backups->get($id);
        $storage = new BackupStorageService();

        try {
            if ($storage->exists($backup->filename)) {
                $storage->delete($backup->filename);
            }
        } catch (Exception $e) {
            Log::warning('Could not delete backup file: ' . $e->getMessage());
        }

        $this->Backups->delete($backup);
        $this->Flash->success(__('Backup deleted.'));

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Save backup settings (encryption key, schedule, retention).
     */
    public function settings(): ?Response
    {
        $this->request->allowMethod(['post']);

        $appSettings = $this->fetchTable('AppSettings');

        $key = $this->request->getData('encryption_key');
        if (!empty($key)) {
            $appSettings->updateSetting('Backup.encryptionKey', 'password', (string)$key, false);
            $this->Flash->success(__('Encryption key saved.'));
        }

        $schedule = $this->request->getData('schedule');
        if ($schedule !== null) {
            $appSettings->updateSetting('Backup.schedule', 'string', $schedule, false);
        }

        $retention = $this->request->getData('retention_days');
        if ($retention !== null) {
            $appSettings->updateSetting('Backup.retentionDays', 'string', (string)$retention, false);
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Mark old "running" backup rows as failed so the UI reflects actionable state.
     */
    private function markStaleRunningBackups(): void
    {
        $staleBefore = DateTime::now()->subMinutes(10);
        $staleRows = $this->Backups->find()
            ->where([
                'status' => 'running',
                'modified <=' => $staleBefore,
            ])
            ->all();

        foreach ($staleRows as $staleBackup) {
            $staleBackup->status = 'failed';
            if (empty($staleBackup->notes)) {
                $staleBackup->notes = __(
                    'Stale running state reset at {0}. Previous operation did not complete.',
                    DateTime::now()->format('Y-m-d H:i:s'),
                );
            }
            $this->Backups->save($staleBackup);
        }
    }
}
