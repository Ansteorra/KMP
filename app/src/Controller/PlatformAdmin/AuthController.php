<?php
declare(strict_types=1);

namespace App\Controller\PlatformAdmin;

use App\Services\Platform\PlatformTotpVerifier;
use App\Services\Secrets\SecretStoreFactory;
use Cake\Core\Configure;
use Cake\Http\Response;
use DateTimeImmutable;

class AuthController extends PlatformAdminAppController
{
    private const MAX_FAILED_LOGINS = 5;
    private const LOCK_MINUTES = 15;

    /**
     * Platform-admin login.
     *
     * @return \Cake\Http\Response|null
     */
    public function login(): ?Response
    {
        $this->request->allowMethod(['get', 'post']);
        $this->set('redirect', (string)$this->request->getQuery('redirect', '/platform-admin'));

        if (!$this->request->is('post')) {
            return null;
        }

        $email = strtolower(trim((string)$this->request->getData('email', '')));
        $password = (string)$this->request->getData('password', '');
        $totp = trim((string)$this->request->getData('totp', ''));
        $redirect = $this->safeRedirect((string)$this->request->getData('redirect', '/platform-admin'));
        $user = $this->findLoginUser($email);

        $failureMessage = $this->loginFailureMessage($user, $email, $password, $totp);
        if ($failureMessage !== null) {
            $this->recordFailedLoginIfUserExists($user);
            $this->Flash->error(__($failureMessage));
            $this->set('redirect', $redirect);

            return null;
        }

        $this->recordSuccessfulLogin($user, $password);
        $session = $this->request->getSession();
        $session->write('PlatformAdmin', [
            'id' => (string)$user['id'],
            'email' => (string)$user['email'],
            'status' => (string)$user['status'] === 'pending_enrollment' ? 'active' : (string)$user['status'],
            'host' => strtolower(rtrim($this->request->getUri()->getHost(), '.')),
            'created_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);

        return $this->redirect($redirect);
    }

    /**
     * Platform-admin logout.
     *
     * @return \Cake\Http\Response|null
     */
    public function logout(): ?Response
    {
        $this->request->getSession()->delete('PlatformAdmin');
        $this->Flash->success(__('You have been logged out of platform admin.'));

        return $this->redirect(['prefix' => 'PlatformAdmin', 'controller' => 'Auth', 'action' => 'login']);
    }

    /**
     * Fetch a platform user row for login.
     *
     * @return array<string, mixed>|null
     */
    private function findLoginUser(string $email): ?array
    {
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $row = $this->platform()->execute(
            'SELECT id, email, password_hash, status, totp_secret_ref, failed_login_count, locked_until ' .
            'FROM platform_users WHERE lower(email) = :email LIMIT 1',
            ['email' => $email],
        )->fetch('assoc');

        return is_array($row) ? $row : null;
    }

    /**
     * Check whether a user status can complete the login form.
     */
    private function statusCanLogin(string $status): bool
    {
        return $status === 'pending_enrollment' || $this->statusIsAllowed($status);
    }

    /**
     * Verify the platform admin TOTP challenge.
     */
    private function verifyTotp(string $userId, mixed $secretRef, string $totp): bool
    {
        $mfaConfig = (array)Configure::read('Platform.adminMfa');
        $verifier = new PlatformTotpVerifier(
            SecretStoreFactory::fromConfig(),
            (int)($mfaConfig['window'] ?? 1),
            (int)($mfaConfig['period'] ?? 30),
            (int)($mfaConfig['digits'] ?? 6),
            (string)($mfaConfig['algorithm'] ?? 'sha1'),
        );

        return $verifier->verify($userId, is_string($secretRef) ? $secretRef : null, $totp);
    }

    /**
     * Return a safe, optionally detailed login failure reason.
     *
     * @param array<string, mixed>|null $user Platform user row
     */
    private function loginFailureMessage(?array $user, string $email, string $password, string $totp): ?string
    {
        if ($user === null) {
            return $this->loginFailureText(sprintf(
                'No platform admin account matched "%s".',
                $email === '' ? '(blank)' : $email,
            ));
        }
        if ($this->isLocked($user['locked_until'] ?? null)) {
            return $this->loginFailureText('Platform admin account is locked.');
        }
        if (!$this->statusCanLogin((string)($user['status'] ?? ''))) {
            return $this->loginFailureText('Platform admin account status is not allowed to sign in.');
        }
        if (!password_verify($password, (string)($user['password_hash'] ?? ''))) {
            return $this->loginFailureText('Platform admin password verification failed.');
        }
        if (!$this->verifyTotp((string)$user['id'], $user['totp_secret_ref'] ?? null, $totp)) {
            return $this->loginFailureText('Platform admin password verified, but MFA code verification failed.');
        }

        return null;
    }

    /**
     * Keep production login failures generic unless detailed diagnostics are enabled.
     */
    private function loginFailureText(string $detailedMessage): string
    {
        if (Configure::read('Platform.adminPortal.detailedLoginErrors')) {
            return $detailedMessage;
        }

        return 'Platform admin login failed.';
    }

    /**
     * Preserve login lockout behavior for all failures tied to a known user.
     *
     * @param array<string, mixed>|null $user Platform user row
     */
    private function recordFailedLoginIfUserExists(?array $user): void
    {
        if ($user === null) {
            return;
        }

        $this->recordFailedLogin((string)$user['id'], (int)($user['failed_login_count'] ?? 0));
    }

    /**
     * Record a failed login and lock the account after repeated failures.
     */
    private function recordFailedLogin(string $userId, int $currentFailedCount): void
    {
        $failedCount = $currentFailedCount + 1;
        $fields = [
            'failed_login_count' => $failedCount,
            'modified_at' => $this->dbNow(),
        ];
        if ($failedCount >= self::MAX_FAILED_LOGINS) {
            $fields['locked_until'] = (new DateTimeImmutable())
                ->modify(sprintf('+%d minutes', self::LOCK_MINUTES))
                ->format('Y-m-d H:i:s');
        }

        $this->platform()->update('platform_users', $fields, ['id' => $userId]);
    }

    /**
     * Record successful login, first TOTP enrollment, and password hash upgrades.
     *
     * @param array<string, mixed> $user User row
     */
    private function recordSuccessfulLogin(array $user, string $password): void
    {
        $fields = [
            'failed_login_count' => 0,
            'locked_until' => null,
            'last_login_at' => $this->dbNow(),
            'modified_at' => $this->dbNow(),
        ];
        if ((string)$user['status'] === 'pending_enrollment') {
            $fields['status'] = 'active';
            $fields['totp_enrolled_at'] = $this->dbNow();
        }
        if (password_needs_rehash((string)$user['password_hash'], PASSWORD_DEFAULT)) {
            $fields['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $this->platform()->update('platform_users', $fields, ['id' => (string)$user['id']]);
    }

    /**
     * Keep post-login redirects inside the platform-admin prefix.
     */
    private function safeRedirect(string $redirect): string
    {
        if (str_starts_with($redirect, '/platform-admin')) {
            return $redirect;
        }

        return '/platform-admin';
    }

    /**
     * Current DB timestamp string.
     */
    private function dbNow(): string
    {
        return (new DateTimeImmutable())->format('Y-m-d H:i:s');
    }
}
