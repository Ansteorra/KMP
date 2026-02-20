<?php

declare(strict_types=1);

namespace App\Controller;

use App\Services\BackupService;
use App\Services\BackupStorageService;
use Cake\Http\Response;
use Cake\Log\Log;
use Exception;

/**
 * Manages database backups: list, create, restore, download, delete, settings.
 */
class BackupsController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->Authorization->authorizeModel('index', 'create', 'restore', 'download', 'delete', 'settings');
    }

    /**
     * List all backups and show settings panel.
     */
    public function index(): void
    {
        $backups = $this->paginate($this->Backups, [
            'order' => ['Backups.created' => 'DESC'],
            'limit' => 25,
        ]);

        $appSettings = $this->fetchTable('AppSettings');
        $hasKey = !empty($appSettings->getAppSetting('Backup.encryptionKey', '', 'string', false));
        $schedule = $appSettings->getAppSetting('Backup.schedule', 'disabled', 'string', false);
        $retention = (int)$appSettings->getAppSetting('Backup.retentionDays', '30', 'string', false);

        $storage = new BackupStorageService();

        $this->set(compact('backups', 'hasKey', 'schedule', 'retention'));
        $this->set('storageType', $storage->getAdapterType());
    }

    /**
     * Create a new backup.
     */
    public function create(): ?Response
    {
        $this->request->allowMethod(['post']);

        $appSettings = $this->fetchTable('AppSettings');
        $encryptionKey = $appSettings->getAppSetting('Backup.encryptionKey', '', 'string', false);

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
    public function restore(int $id): ?Response
    {
        $this->request->allowMethod(['post']);

        $appSettings = $this->fetchTable('AppSettings');
        $encryptionKey = $appSettings->getAppSetting('Backup.encryptionKey', '', 'string', false);

        if (empty($encryptionKey)) {
            $this->Flash->error(__('Set an encryption key before restoring.'));

            return $this->redirect(['action' => 'index']);
        }

        $backup = $this->Backups->get($id);
        $storage = new BackupStorageService();
        $backupService = new BackupService();

        try {
            $data = $storage->read($backup->filename);
            $stats = $backupService->import($data, $encryptionKey);
            $this->Flash->success(__(
                'Restore completed: {0} tables, {1} rows',
                $stats['table_count'],
                $stats['row_count'],
            ));
        } catch (Exception $e) {
            Log::error('Restore failed: ' . $e->getMessage());
            $this->Flash->error(__('Restore failed: {0}', $e->getMessage()));
        }

        return $this->redirect(['action' => 'index']);
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
            $appSettings->updateSetting('Backup.encryptionKey', 'string', $key, false);
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
}
