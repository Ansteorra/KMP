<?php
declare(strict_types=1);

namespace App\Controller;

use App\KMP\TenantContext;
use App\Queue\Task\BackupRestoreTask;
use App\Queue\Task\BackupTask;
use App\Services\Backups\BackupRecoveryKeyService;
use App\Services\BackupService;
use App\Services\BackupStorageService;
use App\Services\RestoreStagingService;
use App\Services\RestoreStatusService;
use Cake\Http\Response;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use Throwable;

/**
 * Manages database backups: list, create, restore, download, delete, settings.
 */
class BackupsController extends AppController
{
    /**
     * Set up this component.
     *
     * @return void
     */
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

        $backup = $this->Backups->newEntity([
            'filename' => 'kmp-backup-' . date('Ymd-His') . '.kmpbackup',
            'storage_type' => $storage->getAdapterType(),
            'status' => 'running',
            'notes' => 'Manual backup queued.',
        ]);
        $this->Backups->save($backup);

        try {
            $this->fetchTable('Queue.QueuedJobs')->createJob(BackupTask::class, [
                'backup_id' => $backup->id,
            ], [
                'group' => 'backup',
                'reference' => 'backup-' . $backup->id,
                'status' => 'Backup queued.',
            ]);

            $this->Flash->success(__('Backup queued: {0}', $backup->filename));
        } catch (Throwable $e) {
            Log::error('Backup queueing failed: ' . $e->getMessage());
            $backup->status = 'failed';
            $backup->notes = substr(strip_tags($e->getMessage()), 0, 500);
            $this->Backups->save($backup);
            $this->Flash->error(__('Backup failed to queue. Check logs for details.'));
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
        set_time_limit(0);
        ignore_user_abort(true);
        $encryptionKey = trim((string)$this->request->getData('restore_key', ''));
        $recoveryKeyUpload = $this->request->getData('recovery_key_file');
        $hasRecoveryKey = $recoveryKeyUpload instanceof UploadedFileInterface
            && $recoveryKeyUpload->getError() === UPLOAD_ERR_OK;
        $recoveryKeyWasSubmitted = $recoveryKeyUpload instanceof UploadedFileInterface
            && $recoveryKeyUpload->getError() !== UPLOAD_ERR_NO_FILE;

        if ($recoveryKeyWasSubmitted && !$hasRecoveryKey) {
            $message = __('Choose a valid backup recovery-key file.');
            if ($expectsJson) {
                return $this->jsonResponse(['success' => false, 'message' => $message], 400);
            }
            $this->Flash->error($message);

            return $this->redirect(['action' => 'index']);
        }
        if ($id !== null && $hasRecoveryKey) {
            $message = __('A recovery-key file can be used only when importing a managed backup archive.');
            if ($expectsJson) {
                return $this->jsonResponse(['success' => false, 'message' => $message], 400);
            }
            $this->Flash->error($message);

            return $this->redirect(['action' => 'index']);
        }
        if ($encryptionKey !== '' && $hasRecoveryKey) {
            $message = __('Provide either a backup encryption key or a recovery-key file, not both.');
            if ($expectsJson) {
                return $this->jsonResponse(['success' => false, 'message' => $message], 400);
            }
            $this->Flash->error($message);

            return $this->redirect(['action' => 'index']);
        }
        if ($encryptionKey === '' && !$hasRecoveryKey) {
            $message = __('Enter the backup encryption key or choose its recovery-key file.');
            if ($expectsJson) {
                return $this->jsonResponse(['success' => false, 'message' => $message], 400);
            }
            $this->Flash->error($message);

            return $this->redirect(['action' => 'index']);
        }

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

        try {
            $backupService = new BackupService();
            if ($hasRecoveryKey) {
                $recoveryKeyJson = $this->readRecoveryKeyUpload($recoveryKeyUpload);
                $logicalArchive = (new BackupRecoveryKeyService())->decryptTenantArchive(
                    $data,
                    $recoveryKeyJson,
                    TenantContext::slug(),
                );
                $encryptionKey = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
                try {
                    $data = $backupService->encryptLogicalArchive($logicalArchive, $encryptionKey);
                } finally {
                    sodium_memzero($logicalArchive);
                }
            } else {
                $backupService->validateImportHeader($data, $encryptionKey);
            }
        } catch (Throwable $e) {
            $message = $hasRecoveryKey
                ? __('The managed backup and recovery-key files could not be opened: {0}', $e->getMessage())
                : __('The backup file could not be opened with the provided encryption key: {0}', $e->getMessage());
            Log::warning(sprintf(
                'Restore preflight failed for %s: %s',
                (string)$sourceLabel,
                $e->getMessage(),
            ));
            if ($expectsJson) {
                return $this->jsonResponse(['success' => false, 'message' => $message], 400);
            }
            $this->Flash->error($message);

            return $this->redirect(['action' => 'index']);
        }

        $identity = $this->request->getAttribute('identity');
        $actor = is_object($identity) && method_exists(
            $identity,
            'getIdentifier',
        ) ? (string)$identity->getIdentifier() : null;
        $restoreId = bin2hex(random_bytes(16));
        if (
            !$restoreStatusService->acquireLock([
            'source' => $sourceLabel,
            'backup_id' => $id,
            'actor' => $actor,
            'restore_id' => $restoreId,
            'recovery_key_import' => $hasRecoveryKey,
            'message' => sprintf('Restore starting from %s.', $sourceLabel),
            ])
        ) {
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
                'restore_id' => $restoreId,
                'recovery_key_import' => $hasRecoveryKey,
            ]);

            if ($restoreTrackedBackup !== null) {
                $restoreTrackedBackup->status = 'running';
                $restoreTrackedBackup->notes = __('Restore started at {0}.', date('Y-m-d H:i:s'));
                $this->Backups->save($restoreTrackedBackup);
            }

            $token = (new RestoreStagingService())->stage($data, $encryptionKey, [
                'source' => $sourceLabel,
                'backup_id' => $id,
                'actor' => $actor,
                'restore_id' => $restoreId,
                'recovery_key_import' => $hasRecoveryKey,
            ]);
            $restoreJob = $this->enqueueRestoreRunner($token, $restoreId);

            $restoreStatusService->updateStatus('queued', sprintf('Restore queued from %s.', $sourceLabel), [
                'source' => $sourceLabel,
                'backup_id' => $id,
                'actor' => $actor,
                'restore_id' => $restoreId,
                'recovery_key_import' => $hasRecoveryKey,
                'queue_job_id' => $restoreJob->id,
            ]);

            if ($expectsJson) {
                return $this->jsonResponse([
                    'success' => true,
                    'message' => __('Restore started. Progress will continue in the background.'),
                    'status' => $restoreStatusService->getStatus(),
                ], 202);
            }

            $this->Flash->success(__('Restore started. Progress will continue in the background.'));

            return $this->redirect(['action' => 'index']);
        } catch (Throwable $e) {
            Log::error('Restore failed to start: ' . $e->getMessage());

            if ($restoreTrackedBackup !== null) {
                $restoreTrackedBackup->status = 'failed';
                $restoreTrackedBackup->notes = __(
                    'Restore failed to start at {0}: {1}',
                    date('Y-m-d H:i:s'),
                    $e->getMessage(),
                );
                $this->Backups->save($restoreTrackedBackup);
            }
            $restoreStatusService->markFailed(sprintf('Restore/import failed to start: %s', $e->getMessage()), [
                'source' => $sourceLabel,
                'backup_id' => $id,
                'actor' => $actor,
                'restore_id' => $restoreId,
                'recovery_key_import' => $hasRecoveryKey,
            ]);
            if ($expectsJson) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => __('Restore failed to start: {0}', $e->getMessage()),
                ], 500);
            }
            $this->Flash->error(__('Restore failed to start: {0}', $e->getMessage()));
            $restoreStatusService->releaseLock();
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Read a bounded recovery-key upload without persisting it.
     */
    private function readRecoveryKeyUpload(UploadedFileInterface $uploadedFile): string
    {
        $size = $uploadedFile->getSize();
        if ($size !== null && $size > BackupRecoveryKeyService::MAX_KEY_FILE_BYTES) {
            throw new RuntimeException('The backup recovery-key file is too large.');
        }
        $stream = $uploadedFile->getStream();
        $stream->rewind();
        $json = $stream->getContents();
        if ($json === '' || strlen($json) > BackupRecoveryKeyService::MAX_KEY_FILE_BYTES) {
            throw new RuntimeException('The backup recovery-key file is empty or too large.');
        }

        return $json;
    }

    /**
     * Queue a staged restore runner without exposing the encryption key in process args.
     */
    private function enqueueRestoreRunner(string $token, string $restoreId): object
    {
        $queuedJobs = $this->fetchTable('Queue.QueuedJobs');

        return $queuedJobs->createJob(BackupRestoreTask::class, [
            'token' => $token,
            'restore_id' => $restoreId,
        ], [
            'group' => 'backup_restore',
            'reference' => 'restore-' . $restoreId,
            'status' => 'Restore queued.',
        ]);
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
