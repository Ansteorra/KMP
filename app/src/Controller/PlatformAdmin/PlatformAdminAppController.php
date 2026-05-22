<?php
declare(strict_types=1);

namespace App\Controller\PlatformAdmin;

use App\Controller\AppController;
use App\Services\Platform\PlatformTotpVerifier;
use App\Services\Secrets\SecretStoreFactory;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Datasource\Exception\MissingDatasourceConfigException;
use Cake\Event\EventInterface;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;

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
     * Validate that a backup storage object points to an encrypted KMP backup archive.
     */
    protected function safeKmpBackupObjectName(mixed $objectUri): string
    {
        $filename = trim((string)$objectUri);
        if (
            $filename === ''
            || str_contains($filename, '://')
            || str_starts_with($filename, '/')
            || str_contains($filename, '..')
            || str_contains($filename, '\\')
            || !str_ends_with($filename, '.kmpbackup')
        ) {
            throw new BadRequestException('Only encrypted .kmpbackup archives can be used for this action.');
        }

        return $filename;
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
