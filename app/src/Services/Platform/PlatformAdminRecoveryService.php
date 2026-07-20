<?php
declare(strict_types=1);

namespace App\Services\Platform;

use App\Services\Secrets\SecretStoreInterface;
use App\Services\Secrets\SensitiveString;
use App\Services\Secrets\WritableSecretStoreInterface;
use Cake\Database\Connection;
use DateTimeImmutable;
use DateTimeInterface;
use RuntimeException;
use Throwable;

class PlatformAdminRecoveryService
{
    private const RECOVERY_CODE_COUNT = 10;
    private const RECOVERY_CODE_BYTES = 9;
    private const SESSION_SELECTOR_BYTES = 18;
    private const SESSION_VERIFIER_BYTES = 32;
    private const DEFAULT_SESSION_MINUTES = 15;

    /**
     * Constructor.
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly SecretStoreInterface $secretStore,
        private readonly PlatformTotpVerifierInterface $totpVerifier = new UnavailablePlatformTotpVerifier(),
        private readonly ?PlatformAuditService $auditService = null,
    ) {
    }

    /**
     * Create the first platform admin and one-time recovery material.
     *
     * @return \App\Services\Platform\PlatformAdminBootstrapResult
     */
    public function bootstrapFirstAdmin(string $email): PlatformAdminBootstrapResult
    {
        $email = $this->normalizeEmail($email);
        if ($this->countPlatformUsers() > 0) {
            $this->audit('platform.admin.bootstrap.refused', null, 'platform_user', null, null, [
                'email_hash' => hash('sha256', $email),
                'reason' => 'platform_users_not_empty',
                'page_on_call' => true,
            ]);

            throw new RuntimeException('Refusing bootstrap: at least one platform user already exists.');
        }
        if (!$this->secretStore instanceof WritableSecretStoreInterface) {
            throw new RuntimeException(
                'The configured secret store is not writable; cannot persist TOTP bootstrap secret.',
            );
        }

        $now = $this->now();
        $userId = $this->uuid();
        $initialPassword = new SensitiveString($this->randomToken(24));
        $totpSecret = new SensitiveString($this->base32Secret(20));
        $totpSecretRef = sprintf('platform.admin.%s.totp', $userId);
        $recoveryCodes = $this->generateRecoveryCodes();

        $this->connection->begin();
        try {
            $this->secretStore->put($totpSecretRef, $totpSecret);
            $this->connection->insert('platform_users', [
                'id' => $userId,
                'email' => $email,
                'password_hash' => password_hash($initialPassword->reveal(), PASSWORD_DEFAULT),
                'status' => 'pending_enrollment',
                'totp_secret_ref' => $totpSecretRef,
                'totp_enrolled_at' => null,
                'failed_login_count' => 0,
                'locked_until' => null,
                'last_login_at' => null,
                'created_at' => $now,
                'modified_at' => $now,
            ]);

            foreach ($recoveryCodes as $recoveryCode) {
                $this->connection->insert('platform_user_recovery_codes', [
                    'id' => $this->uuid(),
                    'platform_user_id' => $userId,
                    'code_hash' => password_hash($recoveryCode->reveal(), PASSWORD_DEFAULT),
                    'used_at' => null,
                    'created_at' => $now,
                ]);
            }

            $this->audit(
                'platform.admin.bootstrap.created',
                $userId,
                'platform_user',
                $userId,
                null,
                [
                    'email_hash' => hash('sha256', $email),
                    'totp_secret_ref' => $totpSecretRef,
                    'recovery_code_count' => count($recoveryCodes),
                    'page_on_call' => true,
                ],
                false,
            );
            $this->connection->commit();
        } catch (Throwable $exception) {
            $this->connection->rollback();
            throw $exception;
        }

        return new PlatformAdminBootstrapResult(
            $userId,
            $email,
            $totpSecretRef,
            $initialPassword,
            $totpSecret,
            $recoveryCodes,
        );
    }

    /**
     * Create a one-time session with TOTP plus a valid recovery code.
     *
     * @return \App\Services\Platform\PlatformEmergencyLoginResult
     */
    public function emergencyLogin(
        string $email,
        string $totpCode,
        string $recoveryCode,
        int $sessionMinutes = self::DEFAULT_SESSION_MINUTES,
    ): PlatformEmergencyLoginResult {
        if (!$this->totpVerifier->isAvailable()) {
            throw new RuntimeException(
                'Emergency login is unavailable until production TOTP verification is configured.',
            );
        }
        $email = $this->normalizeEmail($email);
        $user = $this->fetchUserByEmail($email);
        if ($user === null) {
            throw new RuntimeException('Emergency login failed.');
        }
        $userId = (string)$user['id'];
        if (!$this->totpVerifier->verify($userId, $user['totp_secret_ref'] ?? null, $totpCode)) {
            $this->audit('platform.admin.emergency_login.failed', $userId, 'platform_user', $userId, null, [
                'email_hash' => hash('sha256', $email),
                'reason' => 'invalid_totp',
                'page_on_call' => true,
            ]);

            throw new RuntimeException('Emergency login failed.');
        }

        $recoveryCodeId = $this->findUsableRecoveryCodeId($userId, $recoveryCode);
        if ($recoveryCodeId === null) {
            $this->audit('platform.admin.emergency_login.failed', $userId, 'platform_user', $userId, null, [
                'email_hash' => hash('sha256', $email),
                'reason' => 'invalid_recovery_code',
                'page_on_call' => true,
            ]);

            throw new RuntimeException('Emergency login failed.');
        }

        $now = new DateTimeImmutable('now');
        $expiresAt = $now->modify(sprintf('+%d minutes', max(1, $sessionMinutes)));
        $selector = $this->randomToken(self::SESSION_SELECTOR_BYTES);
        $verifier = $this->randomToken(self::SESSION_VERIFIER_BYTES);
        $sessionToken = new SensitiveString($selector . '.' . $verifier);
        $sessionId = $this->uuid();

        $this->connection->begin();
        try {
            $this->connection->update('platform_user_recovery_codes', [
                'used_at' => $this->formatTime($now),
            ], ['id' => $recoveryCodeId]);
            $this->connection->insert('platform_auth_sessions', [
                'id' => $sessionId,
                'platform_user_id' => $userId,
                'selector_hash' => hash('sha256', $selector),
                'verifier_hash' => password_hash($verifier, PASSWORD_DEFAULT),
                'ip_address' => 'cli',
                'user_agent' => 'bin/cake platform admin emergency-login',
                'last_seen_at' => null,
                'expires_at' => $this->formatTime($expiresAt),
                'revoked_at' => null,
                'created_at' => $this->formatTime($now),
            ]);
            $this->audit(
                'platform.admin.emergency_login.succeeded',
                $userId,
                'platform_auth_session',
                $sessionId,
                null,
                [
                    'email_hash' => hash('sha256', $email),
                    'recovery_code_id' => $recoveryCodeId,
                    'expires_at' => $expiresAt->format(DateTimeInterface::ATOM),
                    'page_on_call' => true,
                ],
                false,
            );
            $this->connection->commit();
        } catch (Throwable $exception) {
            $this->connection->rollback();
            throw $exception;
        }

        return new PlatformEmergencyLoginResult($userId, $sessionId, $sessionToken, $expiresAt);
    }

    /**
     * Rotate unused recovery codes after a valid admin TOTP challenge.
     *
     * @return list<\App\Services\Secrets\SensitiveString>
     */
    public function rotateRecoveryCodes(string $email, string $totpCode, string $reason): array
    {
        if (!$this->totpVerifier->isAvailable()) {
            throw new RuntimeException(
                'Recovery code rotation is unavailable until production TOTP verification is configured.',
            );
        }
        $email = $this->normalizeEmail($email);
        if (trim($reason) === '') {
            throw new RuntimeException('A non-empty reason is required to rotate recovery codes.');
        }
        $user = $this->fetchUserByEmail($email);
        if ($user === null) {
            throw new RuntimeException('Recovery code rotation failed.');
        }
        $userId = (string)$user['id'];
        if (!$this->totpVerifier->verify($userId, $user['totp_secret_ref'] ?? null, $totpCode)) {
            $this->audit('platform.admin.recovery_codes.rotate_failed', $userId, 'platform_user', $userId, $reason, [
                'email_hash' => hash('sha256', $email),
                'reason' => 'invalid_totp',
                'page_on_call' => true,
            ]);

            throw new RuntimeException('Recovery code rotation failed.');
        }

        $now = $this->now();
        $recoveryCodes = $this->generateRecoveryCodes();
        $this->connection->begin();
        try {
            $this->connection->execute(
                'DELETE FROM platform_user_recovery_codes WHERE platform_user_id = ? AND used_at IS NULL',
                [$userId],
            );
            foreach ($recoveryCodes as $recoveryCode) {
                $this->connection->insert('platform_user_recovery_codes', [
                    'id' => $this->uuid(),
                    'platform_user_id' => $userId,
                    'code_hash' => password_hash($recoveryCode->reveal(), PASSWORD_DEFAULT),
                    'used_at' => null,
                    'created_at' => $now,
                ]);
            }
            $this->audit(
                'platform.admin.recovery_codes.rotated',
                $userId,
                'platform_user',
                $userId,
                $reason,
                [
                    'email_hash' => hash('sha256', $email),
                    'recovery_code_count' => count($recoveryCodes),
                    'page_on_call' => true,
                ],
                false,
            );
            $this->connection->commit();
        } catch (Throwable $exception) {
            $this->connection->rollback();
            throw $exception;
        }

        return $recoveryCodes;
    }

    /**
     * Generate a replacement platform admin password and store only its hash.
     */
    public function resetPassword(string $email, string $reason): SensitiveString
    {
        $email = $this->normalizeEmail($email);
        $user = $this->fetchUserByEmail($email);
        if ($user === null) {
            $this->audit('platform.admin.password_reset.failed', null, 'platform_user', null, $reason, [
                'email_hash' => hash('sha256', $email),
                'reason' => 'user_not_found',
                'page_on_call' => true,
            ]);

            throw new RuntimeException('Platform admin password reset failed.');
        }

        $password = new SensitiveString($this->randomToken(24));
        $now = $this->now();
        $this->connection->update('platform_users', [
            'password_hash' => password_hash($password->reveal(), PASSWORD_DEFAULT),
            'failed_login_count' => 0,
            'locked_until' => null,
            'modified_at' => $now,
        ], ['id' => (string)$user['id']]);
        $this->audit(
            'platform.admin.password_reset.completed',
            (string)$user['id'],
            'platform_user',
            (string)$user['id'],
            $reason,
            [
                'email_hash' => hash('sha256', $email),
                'page_on_call' => true,
            ],
            false,
        );

        return $password;
    }

    /**
     * Fail closed until multi-admin TOTP approval is available.
     */
    public function resetMfa(string $email, string $reason): void
    {
        $email = $this->normalizeEmail($email);
        $this->audit('platform.admin.reset_mfa.refused', null, 'platform_user', null, $reason, [
            'email_hash' => hash('sha256', $email),
            'reason' => 'totp_approval_unavailable',
            'page_on_call' => true,
        ]);

        throw new RuntimeException(
            'MFA reset is unavailable until another-admin TOTP approval is configured. No changes were made.',
        );
    }

    /**
     * Count existing platform users.
     */
    private function countPlatformUsers(): int
    {
        $row = $this->connection->execute('SELECT COUNT(*) AS user_count FROM platform_users')->fetch('assoc') ?: [];

        return (int)($row['user_count'] ?? 0);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchUserByEmail(string $email): ?array
    {
        $row = $this->connection
            ->execute('SELECT id, email, totp_secret_ref FROM platform_users WHERE email = ?', [$email])
            ->fetch('assoc');

        return is_array($row) ? $row : null;
    }

    /**
     * Find the matching unused recovery code row.
     */
    private function findUsableRecoveryCodeId(string $userId, string $recoveryCode): ?string
    {
        $rows = $this->connection
            ->execute(
                'SELECT id, code_hash FROM platform_user_recovery_codes WHERE platform_user_id = ? AND used_at IS NULL',
                [$userId],
            )
            ->fetchAll('assoc');
        foreach ($rows as $row) {
            if (password_verify($recoveryCode, (string)$row['code_hash'])) {
                return (string)$row['id'];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function audit(
        string $action,
        ?string $platformUserId,
        ?string $subjectType,
        ?string $subjectId,
        ?string $reason,
        array $metadata,
        bool $withTransaction = true,
    ): void {
        ($this->auditService ?? new PlatformAuditService($this->connection))->record(
            $action,
            $platformUserId,
            $subjectType,
            $subjectId,
            $reason,
            $metadata,
            $withTransaction,
            [
                'ipAddress' => 'cli',
                'userAgent' => 'bin/cake platform admin',
            ],
        );
    }

    /**
     * @return list<\App\Services\Secrets\SensitiveString>
     */
    private function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            $codes[] = new SensitiveString(strtoupper($this->randomToken(self::RECOVERY_CODE_BYTES)));
        }

        return $codes;
    }

    /**
     * Normalize and validate an email address.
     */
    private function normalizeEmail(string $email): string
    {
        $email = strtolower(trim($email));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('A valid --email value is required.');
        }

        return $email;
    }

    /**
     * Generate a base32 TOTP-compatible secret.
     */
    private function base32Secret(int $bytes): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $random = random_bytes($bytes);
        $secret = '';
        for ($i = 0, $length = strlen($random); $i < $length; $i++) {
            $secret .= $alphabet[ord($random[$i]) & 31];
        }

        return $secret;
    }

    /**
     * Generate a URL-safe random token.
     */
    private function randomToken(int $bytes): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    /**
     * Generate a UUIDv4 without requiring database extensions.
     */
    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Return the current timestamp for persistence.
     */
    private function now(): string
    {
        return $this->formatTime(new DateTimeImmutable('now'));
    }

    /**
     * Format timestamps consistently for the platform database.
     */
    private function formatTime(DateTimeImmutable $time): string
    {
        return $time->format('Y-m-d H:i:s');
    }
}
