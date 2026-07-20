<?php
declare(strict_types=1);

namespace App\Controller;

use App\KMP\TenantContext;
use App\Services\Backups\BackupStorageFactory;
use App\Services\Backups\TenantBackupService;
use App\Services\Backups\TenantSelfServiceBackupService;
use App\Services\BackupStorageService;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Cake\Http\Response;
use Cake\Log\Log;
use RuntimeException;
use Throwable;

/**
 * Tenant self-service view of platform-managed backups.
 *
 * Tenant admins can list backups, request an on-demand backup, download an
 * archive plus its one-time recovery key, and see backup status. Scheduling,
 * retention, and restores are owned by Platform Admin.
 */
class BackupsController extends AppController
{
    use ManagedBackupDownloadTrait;

    /**
     * Set up authorization for this controller.
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->Authorization->authorizeModel(
            'index',
            'create',
            'download',
            'downloadRecoveryKey',
            'legacyDownload',
        );
    }

    /**
     * List managed backups, backup status, and read-only legacy archives.
     */
    public function index(): void
    {
        $managedBackups = [];
        $backupStatus = null;
        $managedAvailable = false;
        $tenant = TenantContext::tryCurrent();
        if ($tenant !== null) {
            try {
                $service = $this->selfService();
                $managedBackups = $service->listManagedBackups($tenant->id);
                $backupStatus = $service->status($tenant->id);
                $managedAvailable = true;
            } catch (Throwable $exception) {
                Log::warning('Managed backup listing unavailable: ' . $exception->getMessage());
            }
        }

        $legacyBackups = $this->Backups->find()
            ->orderBy(['Backups.created' => 'DESC'])
            ->limit(25)
            ->all();

        $this->set(compact('managedBackups', 'backupStatus', 'managedAvailable', 'legacyBackups'));
    }

    /**
     * Request an on-demand managed backup.
     */
    public function create(): ?Response
    {
        $this->request->allowMethod(['post']);
        $tenant = TenantContext::tryCurrent();
        if ($tenant === null) {
            $this->Flash->error(__('Managed backups require an active tenant context.'));

            return $this->redirect(['action' => 'index']);
        }

        try {
            $job = $this->selfService()->requestBackup($tenant->id, $tenant->slug, $this->actorId());
            $this->Flash->success(__('Backup has been queued: {0}', $job['id']));
        } catch (RuntimeException $exception) {
            $this->Flash->error(__($exception->getMessage()));
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Download a managed encrypted backup archive.
     */
    public function download(string $backupId): ?Response
    {
        $this->request->allowMethod(['post']);
        $tenant = TenantContext::current();

        try {
            $backup = $this->selfService()->getBackupForDownload($tenant->id, $backupId);
            $this->assertUsableBackup($backup, [
                TenantBackupService::BACKUP_TYPE,
                TenantBackupService::LEGACY_BACKUP_TYPE,
                'pg_dump',
            ]);
            $download = $this->stageBackupDownload(
                $backup,
                BackupStorageFactory::tenantArchive((string)$backup['backup_type']),
                'tenant-' . $tenant->slug,
            );
            register_shutdown_function(static function () use ($download): void {
                if (is_file($download['path'])) {
                    unlink($download['path']);
                }
            });

            return $this->response
                ->withType('application/octet-stream')
                ->withFile($download['path'], [
                    'download' => true,
                    'name' => $download['filename'],
                ]);
        } catch (Throwable $exception) {
            $this->Flash->error(__($exception->getMessage()));
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Export a managed backup's recovery key. One export per backup.
     */
    public function downloadRecoveryKey(string $backupId): ?Response
    {
        $this->request->allowMethod(['post']);
        $tenant = TenantContext::current();

        try {
            $service = $this->selfService();
            $backup = $service->getBackupForDownload($tenant->id, $backupId);
            $this->assertUsableBackup($backup, [TenantBackupService::BACKUP_TYPE]);
            if (!empty($backup['recovery_key_exported_at'])) {
                throw new RuntimeException(
                    'The recovery key for this backup was already exported. '
                    . 'Contact a platform administrator if you need it re-issued.',
                );
            }
            if (!$service->claimRecoveryKeyExport($backupId, 'member:' . ($this->actorId() ?? 'unknown'))) {
                throw new RuntimeException('The recovery key for this backup was already exported.');
            }
            $export = $this->exportTenantBackupRecoveryKey($backup, $service->tenantRow($tenant->id));

            return $this->recoveryKeyDownloadResponse($export);
        } catch (Throwable $exception) {
            $this->Flash->error(__($exception->getMessage()));
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Download a legacy self-service .kmpbackup file (read-only surface).
     */
    public function legacyDownload(int $id): Response
    {
        $backup = $this->Backups->get($id);
        $storage = new BackupStorageService();
        $data = $storage->read($backup->filename);

        return $this->response
            ->withType('application/octet-stream')
            ->withDownload($backup->filename)
            ->withStringBody($data);
    }

    /**
     * Build the tenant self-service backup service on the platform connection.
     */
    private function selfService(): TenantSelfServiceBackupService
    {
        $platform = ConnectionManager::get('platform');
        if (!$platform instanceof Connection) {
            throw new RuntimeException('Platform database connection is unavailable.');
        }

        return new TenantSelfServiceBackupService($platform);
    }

    /**
     * The acting member's identifier, if authenticated.
     */
    private function actorId(): ?string
    {
        $identity = $this->request->getAttribute('identity');
        if (is_object($identity) && method_exists($identity, 'getIdentifier')) {
            return (string)$identity->getIdentifier();
        }

        return null;
    }
}
