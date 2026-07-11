<?php
declare(strict_types=1);

namespace App\Controller\PlatformAdmin;

use App\Controller\AppController;
use App\KMP\TenantMetadata;
use App\Services\Backups\BackupArchiveStorageInterface;
use App\Services\Backups\BackupDownloadService;
use App\Services\Backups\BackupRecoveryKeyService;
use App\Services\Backups\TenantBackupService;
use App\Services\Platform\PlatformTotpVerifier;
use App\Services\Secrets\SecretStoreFactory;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Datasource\Exception\MissingDatasourceConfigException;
use Cake\Event\EventInterface;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use RuntimeException;

/**
 * Base controller for the isolated platform admin portal.
 */
class PlatformAdminAppController extends AppController
{
    /**
     * @var array<string, mixed>|null Authenticated platform admin context.
     */
    protected ?array $platformAdmin = null;

    /**
     * Enforce fail-closed portal feature flag and platform-admin session auth.
     *
     * @param \Cake\Event\EventInterface $event The beforeFilter event
     * @return \Cake\Http\Response|null|void
     */
    public function beforeFilter(EventInterface $event)
    {
        parent::beforeFilter($event);
        $this->Authentication->allowUnauthenticated([(string)$this->request->getParam('action')]);
        $this->Authorization->skipAuthorization();
        $this->viewBuilder()->setLayout('platform_admin');
        $this->set('platformAdmin', null);

        if (!Configure::read('Platform.adminPortal.enabled')) {
            throw new NotFoundException('Platform admin portal is not enabled.');
        }

        if ($this->isLoginAction()) {
            return null;
        }

        $admin = $this->authenticatePlatformAdmin();
        if ($admin === null) {
            if (!$this->request->is(['json', 'ajax'])) {
                return $this->redirect([
                    'prefix' => 'PlatformAdmin',
                    'controller' => 'Auth',
                    'action' => 'login',
                    '?' => ['redirect' => $this->request->getRequestTarget()],
                ]);
            }

            throw new ForbiddenException('Platform admin access denied.');
        }

        $this->platformAdmin = $admin;
        $this->set('platformAdmin', $admin);
    }

    /**
     * Return the platform metadata connection.
     *
     * @return \Cake\Database\Connection
     */
    protected function platform()
    {
        return ConnectionManager::get('platform');
    }

    /**
     * Validate typed confirmation, reason, and TOTP step-up for sensitive admin actions.
     */
    protected function validateStepUpAction(string $expectedConfirmation): string
    {
        $confirmation = trim((string)$this->request->getData('confirmation', ''));
        if (!hash_equals($expectedConfirmation, $confirmation)) {
            throw new BadRequestException(sprintf('Type "%s" to confirm this action.', $expectedConfirmation));
        }

        $reason = trim((string)$this->request->getData('reason', ''));
        if (strlen($reason) < 10) {
            throw new BadRequestException('Enter a reason of at least 10 characters.');
        }

        $totp = trim((string)$this->request->getData('totp', ''));
        if (!$this->verifyPlatformAdminTotp($totp)) {
            throw new BadRequestException('Platform admin MFA code was not valid.');
        }

        return $reason;
    }

    /**
     * Validate completed encrypted backup metadata before a guarded action.
     *
     * @param array<string, mixed> $backup
     * @param list<string> $allowedTypes
     */
    protected function assertUsableBackup(
        array $backup,
        array $allowedTypes = [TenantBackupService::BACKUP_TYPE, 'pg_dump'],
    ): void {
        if ((string)($backup['status'] ?? '') !== 'completed') {
            throw new BadRequestException('Only completed backups can be used for this action.');
        }
        if (!in_array((string)($backup['backup_type'] ?? ''), $allowedTypes, true)) {
            throw new BadRequestException('This backup format is not supported for this action.');
        }
        if ((string)($backup['object_uri'] ?? '') === '') {
            throw new BadRequestException('Backup object metadata is incomplete.');
        }
        $backupType = (string)($backup['backup_type'] ?? '');
        if (
            $backupType !== TenantBackupService::LEGACY_BACKUP_TYPE
            && !preg_match('/^[0-9a-f]{64}$/', strtolower((string)($backup['object_sha256'] ?? '')))
        ) {
            throw new BadRequestException('Backup integrity metadata is incomplete.');
        }
        $retentionUntil = trim((string)($backup['retention_until'] ?? ''));
        if ($retentionUntil !== '') {
            $timestamp = strtotime($retentionUntil . ' UTC');
            if ($timestamp !== false && $timestamp <= time()) {
                throw new BadRequestException('This backup has passed its retention period.');
            }
        }
    }

    /**
     * Validate that a managed archive can be removed without interrupting active work.
     *
     * @param array<string, mixed> $backup
     * @param list<string> $allowedTypes
     */
    protected function assertDeletableBackup(
        array $backup,
        array $allowedTypes = [
            TenantBackupService::BACKUP_TYPE,
            TenantBackupService::LEGACY_BACKUP_TYPE,
            'pg_dump',
        ],
    ): void {
        if (!in_array((string)($backup['status'] ?? ''), ['completed', 'failed', 'deleting'], true)) {
            throw new BadRequestException('Queued or running backups cannot be deleted.');
        }
        if (!in_array((string)($backup['backup_type'] ?? ''), $allowedTypes, true)) {
            throw new BadRequestException('This backup format is not supported for deletion.');
        }
        if (trim((string)($backup['object_uri'] ?? '')) === '') {
            throw new BadRequestException('This backup archive has already been removed.');
        }
    }

    /**
     * Stage and verify a backup for a streaming file response.
     *
     * @param array<string, mixed> $backup
     * @return array{path: string, filename: string}
     */
    protected function stageBackupDownload(
        array $backup,
        BackupArchiveStorageInterface $storage,
        string $filenamePrefix,
    ): array {
        try {
            return (new BackupDownloadService())->stage($backup, $storage, $filenamePrefix);
        } catch (RuntimeException $exception) {
            throw new BadRequestException($exception->getMessage(), null, $exception);
        }
    }

    /**
     * Export a tenant backup recovery-key package.
     *
     * @param array<string, mixed> $backup Backup metadata row
     * @param array<string, mixed> $tenant Tenant metadata row
     * @return array{filename: string, content: string}
     */
    protected function exportTenantBackupRecoveryKey(array $backup, array $tenant): array
    {
        return (new BackupRecoveryKeyService())->exportTenant(
            $backup,
            TenantMetadata::fromPlatformRow($tenant),
            SecretStoreFactory::fromConfig(),
        );
    }

    /**
     * Export a platform database backup recovery-key package.
     *
     * @param array<string, mixed> $backup Backup metadata row
     * @return array{filename: string, content: string}
     */
    protected function exportPlatformBackupRecoveryKey(array $backup): array
    {
        return (new BackupRecoveryKeyService())->exportPlatform(
            $backup,
            SecretStoreFactory::fromConfig(),
        );
    }

    /**
     * Return a recovery-key attachment that browsers and intermediary caches must not retain.
     *
     * @param array{filename: string, content: string} $export Recovery-key export
     */
    protected function recoveryKeyDownloadResponse(array $export): Response
    {
        return $this->response
            ->withType('application/json')
            ->withDownload($export['filename'])
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private')
            ->withHeader('Pragma', 'no-cache')
            ->withHeader('Expires', '0')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withStringBody($export['content']);
    }

    /**
     * Check whether the current request is for the platform-admin login action.
     */
    private function isLoginAction(): bool
    {
        return $this->request->getParam('controller') === 'Auth'
            && in_array((string)$this->request->getParam('action'), ['login', 'logout'], true);
    }

    /**
     * Authenticate the current platform admin session.
     *
     * @return array<string, mixed>|null
     */
    private function authenticatePlatformAdmin(): ?array
    {
        return $this->authenticatePlatformAdminSession();
    }

    /**
     * Validate the platform-admin form-login session.
     *
     * @return array<string, mixed>|null
     */
    private function authenticatePlatformAdminSession(): ?array
    {
        $session = $this->request->getSession();
        $admin = $session->read('PlatformAdmin');
        if (!is_array($admin)) {
            return null;
        }

        $email = strtolower(trim((string)($admin['email'] ?? '')));
        $userId = (string)($admin['id'] ?? '');
        $sessionHost = strtolower(rtrim((string)($admin['host'] ?? ''), '.'));
        $requestHost = strtolower(rtrim($this->request->getUri()->getHost(), '.'));
        if ($email === '' || $userId === '' || $sessionHost !== $requestHost) {
            $session->delete('PlatformAdmin');

            return null;
        }

        try {
            $row = $this->platform()->execute(
                'SELECT id, email, status, locked_until FROM platform_users ' .
                'WHERE id = :id AND lower(email) = :email LIMIT 1',
                ['id' => $userId, 'email' => $email],
            )->fetch('assoc');
        } catch (MissingDatasourceConfigException) {
            $session->delete('PlatformAdmin');

            return null;
        }

        if (!is_array($row) || !$this->statusIsAllowed((string)($row['status'] ?? ''))) {
            $session->delete('PlatformAdmin');

            return null;
        }
        if ($this->isLocked($row['locked_until'] ?? null)) {
            $session->delete('PlatformAdmin');

            return null;
        }

        return [
            'id' => (string)$row['id'],
            'email' => (string)$row['email'],
            'status' => (string)$row['status'],
            'source' => 'form',
        ];
    }

    /**
     * Check whether a platform user status is allowed for authenticated access.
     */
    protected function statusIsAllowed(string $status): bool
    {
        $allowedStatuses = (array)Configure::read('Platform.adminPortal.allowedStatuses', ['active']);

        return in_array($status, $allowedStatuses, true);
    }

    /**
     * Check whether a platform user lockout timestamp is still active.
     */
    protected function isLocked(mixed $lockedUntil): bool
    {
        if ($lockedUntil === null || $lockedUntil === '') {
            return false;
        }

        $timestamp = strtotime((string)$lockedUntil);

        return $timestamp !== false && $timestamp > time();
    }

    /**
     * Verify a TOTP code against the authenticated platform admin.
     */
    private function verifyPlatformAdminTotp(string $totp): bool
    {
        $platformAdminId = (string)($this->platformAdmin['id'] ?? '');
        if ($platformAdminId === '') {
            return false;
        }

        $row = $this->platform()->execute(
            'SELECT totp_secret_ref FROM platform_users WHERE id = :id LIMIT 1',
            ['id' => $platformAdminId],
        )->fetch('assoc');
        if (!is_array($row)) {
            return false;
        }

        $mfaConfig = (array)Configure::read('Platform.adminMfa');
        $verifier = new PlatformTotpVerifier(
            SecretStoreFactory::fromConfig(),
            (int)($mfaConfig['window'] ?? 1),
            (int)($mfaConfig['period'] ?? 30),
            (int)($mfaConfig['digits'] ?? 6),
            (string)($mfaConfig['algorithm'] ?? 'sha1'),
        );

        return $verifier->verify($platformAdminId, $row['totp_secret_ref'] ?? null, $totp);
    }
}
