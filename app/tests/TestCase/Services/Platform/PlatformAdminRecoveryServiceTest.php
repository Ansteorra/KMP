<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Platform;

use App\Services\Platform\Audit\FileWormAuditSink;
use App\Services\Platform\PlatformAdminBootstrapResult;
use App\Services\Platform\PlatformAdminRecoveryService;
use App\Services\Platform\PlatformAuditService;
use App\Services\Platform\PlatformTotpVerifier;
use App\Services\Platform\UnavailablePlatformTotpVerifier;
use App\Services\Secrets\SensitiveString;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\TestSuite\TestCase;
use RuntimeException;

class PlatformAdminRecoveryServiceTest extends TestCase
{
    private Connection $connection;
    private InMemoryWritableSecretStore $secretStore;
    private string $wormFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = new Connection([
            'driver' => Sqlite::class,
            'database' => ':memory:',
        ]);
        $this->createSchema();
        $this->secretStore = new InMemoryWritableSecretStore();
        $directory = dirname(__DIR__, 4) . DS . 'tmp' . DS . 'tests';
        if (!is_dir($directory)) {
            mkdir($directory, 0770, true);
        }
        $this->wormFile = $directory . DS . 'admin-recovery-worm-' . str_replace('.', '-', uniqid('', true)) . '.jsonl';
    }

    protected function tearDown(): void
    {
        if (is_file($this->wormFile)) {
            unlink($this->wormFile);
        }
        parent::tearDown();
    }

    public function testBootstrapCreatesFirstAdminWithHashOnlyPersistenceAndAudit(): void
    {
        $service = new PlatformAdminRecoveryService($this->connection, $this->secretStore);

        $result = $service->bootstrapFirstAdmin('Admin@Example.Org');

        $user = $this->connection->execute('SELECT * FROM platform_users')->fetch('assoc');
        $this->assertSame('admin@example.org', $user['email']);
        $this->assertSame('pending_enrollment', $user['status']);
        $this->assertSame($result->totpSecretRef, $user['totp_secret_ref']);
        $this->assertNotSame($result->initialPassword->reveal(), $user['password_hash']);
        $this->assertTrue(password_verify($result->initialPassword->reveal(), (string)$user['password_hash']));
        $this->assertSame($result->totpSecret->reveal(), $this->secretStore->get($result->totpSecretRef)?->reveal());

        $recoveryRows = $this->connection
            ->execute('SELECT code_hash FROM platform_user_recovery_codes WHERE platform_user_id = ?', [$result->platformUserId])
            ->fetchAll('assoc');
        $this->assertCount(10, $recoveryRows);
        foreach ($result->recoveryCodes as $index => $recoveryCode) {
            $this->assertNotSame($recoveryCode->reveal(), $recoveryRows[$index]['code_hash']);
            $this->assertTrue(password_verify($recoveryCode->reveal(), (string)$recoveryRows[$index]['code_hash']));
        }

        $audit = $this->connection->execute('SELECT * FROM audit_events')->fetch('assoc');
        $this->assertSame('platform.admin.bootstrap.created', $audit['action']);
        $this->assertStringContainsString('"page_on_call":true', (string)$audit['metadata']);
        $this->assertNoPlaintextSecretsInAudit($result);
    }

    public function testBootstrapMirrorsAuditThroughInjectedWormSink(): void
    {
        $service = new PlatformAdminRecoveryService(
            $this->connection,
            $this->secretStore,
            new UnavailablePlatformTotpVerifier(),
            new PlatformAuditService($this->connection, new FileWormAuditSink($this->wormFile), true),
        );

        $result = $service->bootstrapFirstAdmin('admin@example.org');

        $mirrorJson = (string)file_get_contents($this->wormFile);
        $this->assertStringContainsString('platform.admin.bootstrap.created', $mirrorJson);
        $this->assertStringContainsString('mirror_hash', $mirrorJson);
        $this->assertStringNotContainsString($result->initialPassword->reveal(), $mirrorJson);
        $this->assertStringNotContainsString($result->totpSecret->reveal(), $mirrorJson);
        foreach ($result->recoveryCodes as $recoveryCode) {
            $this->assertStringNotContainsString($recoveryCode->reveal(), $mirrorJson);
        }
    }

    public function testBootstrapRefusesWhenPlatformUserAlreadyExists(): void
    {
        $service = new PlatformAdminRecoveryService($this->connection, $this->secretStore);
        $service->bootstrapFirstAdmin('admin@example.org');

        try {
            $service->bootstrapFirstAdmin('second@example.org');
            $this->fail('Expected bootstrap refusal.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Refusing bootstrap', $exception->getMessage());
        }

        $count = $this->connection->execute('SELECT COUNT(*) AS count FROM platform_users')->fetch('assoc');
        $this->assertSame(1, (int)$count['count']);
        $actions = $this->connection
            ->execute('SELECT action FROM audit_events ORDER BY created_at ASC')
            ->fetchAll('assoc');
        $this->assertSame('platform.admin.bootstrap.created', $actions[0]['action']);
        $this->assertSame('platform.admin.bootstrap.refused', $actions[1]['action']);
    }

    public function testEmergencyLoginFailsClosedWhenTotpVerifierUnavailable(): void
    {
        $service = new PlatformAdminRecoveryService(
            $this->connection,
            $this->secretStore,
            new UnavailablePlatformTotpVerifier(),
        );
        $result = $service->bootstrapFirstAdmin('admin@example.org');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('production TOTP verification');
        $service->emergencyLogin('admin@example.org', '123456', $result->recoveryCodes[0]->reveal());
    }

    public function testEmergencyLoginConsumesRecoveryCodeAndStoresOnlySessionHashes(): void
    {
        $now = 1_111_111_111;
        $totp = new PlatformTotpVerifier($this->secretStore, 1, 30, 6, 'sha1', static fn(): int => $now);
        $service = new PlatformAdminRecoveryService(
            $this->connection,
            $this->secretStore,
            $totp,
        );
        $bootstrap = $service->bootstrapFirstAdmin('admin@example.org');
        $totpCode = $totp->codeForTimestamp($bootstrap->totpSecret, $now);
        $recoveryCode = $bootstrap->recoveryCodes[0]->reveal();

        $login = $service->emergencyLogin('admin@example.org', $totpCode, $recoveryCode, 5);

        [$selector, $verifier] = explode('.', $login->sessionToken->reveal(), 2);
        $session = $this->connection->execute('SELECT * FROM platform_auth_sessions')->fetch('assoc');
        $this->assertSame(hash('sha256', $selector), $session['selector_hash']);
        $this->assertNotSame($selector, $session['selector_hash']);
        $this->assertNotSame($verifier, $session['verifier_hash']);
        $this->assertTrue(password_verify($verifier, (string)$session['verifier_hash']));

        $usedCodes = $this->connection
            ->execute('SELECT COUNT(*) AS count FROM platform_user_recovery_codes WHERE used_at IS NOT NULL')
            ->fetch('assoc');
        $this->assertSame(1, (int)$usedCodes['count']);
        $this->assertNoPlaintextSecretsInAudit($bootstrap, $login->sessionToken->reveal());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Emergency login failed.');
        $service->emergencyLogin('admin@example.org', $totpCode, $recoveryCode, 5);
    }

    public function testEmergencyLoginFailsClosedWhenTotpSecretMissing(): void
    {
        $now = 1_111_111_111;
        $totp = new PlatformTotpVerifier($this->secretStore, 1, 30, 6, 'sha1', static fn(): int => $now);
        $service = new PlatformAdminRecoveryService($this->connection, $this->secretStore, $totp);
        $bootstrap = $service->bootstrapFirstAdmin('admin@example.org');
        $this->secretStore->delete($bootstrap->totpSecretRef);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Emergency login failed.');
        $service->emergencyLogin(
            'admin@example.org',
            $totp->codeForTimestamp(new SensitiveString('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'), $now),
            $bootstrap->recoveryCodes[0]->reveal(),
        );
    }

    public function testRotateRecoveryCodesRequiresTotpAndStoresOnlyHashes(): void
    {
        $now = 1_111_111_111;
        $totp = new PlatformTotpVerifier($this->secretStore, 1, 30, 6, 'sha1', static fn(): int => $now);
        $service = new PlatformAdminRecoveryService($this->connection, $this->secretStore, $totp);
        $bootstrap = $service->bootstrapFirstAdmin('admin@example.org');
        $oldRecoveryCode = $bootstrap->recoveryCodes[0]->reveal();
        $totpCode = $totp->codeForTimestamp($bootstrap->totpSecret, $now);

        $newCodes = $service->rotateRecoveryCodes('admin@example.org', $totpCode, 'operator requested rotation');

        $this->assertCount(10, $newCodes);
        $rows = $this->connection
            ->execute('SELECT code_hash FROM platform_user_recovery_codes WHERE used_at IS NULL')
            ->fetchAll('assoc');
        $this->assertCount(10, $rows);
        foreach ($newCodes as $index => $newCode) {
            $this->assertNotSame($newCode->reveal(), $rows[$index]['code_hash']);
            $this->assertTrue(password_verify($newCode->reveal(), (string)$rows[$index]['code_hash']));
        }
        foreach ($rows as $row) {
            $this->assertFalse(password_verify($oldRecoveryCode, (string)$row['code_hash']));
        }

        $auditJson = json_encode($this->connection->execute('SELECT * FROM audit_events')->fetchAll('assoc'));
        $this->assertIsString($auditJson);
        foreach ($newCodes as $newCode) {
            $this->assertStringNotContainsString($newCode->reveal(), $auditJson);
        }
    }

    public function testRotateRecoveryCodesFailsClosedWithoutVerifier(): void
    {
        $service = new PlatformAdminRecoveryService(
            $this->connection,
            $this->secretStore,
            new UnavailablePlatformTotpVerifier(),
        );
        $service->bootstrapFirstAdmin('admin@example.org');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Recovery code rotation is unavailable');
        $service->rotateRecoveryCodes('admin@example.org', '123456', 'lost codes');
    }

    public function testResetMfaFailsClosedUntilAnotherAdminTotpExists(): void
    {
        $service = new PlatformAdminRecoveryService($this->connection, $this->secretStore);

        try {
            $service->resetMfa('admin@example.org', 'lost phone');
            $this->fail('Expected fail-closed reset-mfa exception.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('MFA reset is unavailable', $exception->getMessage());
        }

        $audit = $this->connection->execute('SELECT * FROM audit_events')->fetch('assoc');
        $this->assertSame('platform.admin.reset_mfa.refused', $audit['action']);
        $this->assertStringContainsString('"page_on_call":true', (string)$audit['metadata']);
    }

    private function createSchema(): void
    {
        $this->connection->execute(
            'CREATE TABLE platform_users (
                id TEXT PRIMARY KEY,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                status TEXT NOT NULL,
                totp_secret_ref TEXT NULL,
                totp_enrolled_at TEXT NULL,
                failed_login_count INTEGER NOT NULL DEFAULT 0,
                locked_until TEXT NULL,
                last_login_at TEXT NULL,
                created_at TEXT NOT NULL,
                modified_at TEXT NULL
            )',
        );
        $this->connection->execute(
            'CREATE TABLE platform_user_recovery_codes (
                id TEXT PRIMARY KEY,
                platform_user_id TEXT NOT NULL,
                code_hash TEXT NOT NULL,
                used_at TEXT NULL,
                created_at TEXT NOT NULL
            )',
        );
        $this->connection->execute(
            'CREATE TABLE platform_auth_sessions (
                id TEXT PRIMARY KEY,
                platform_user_id TEXT NOT NULL,
                selector_hash TEXT NOT NULL UNIQUE,
                verifier_hash TEXT NOT NULL,
                ip_address TEXT NULL,
                user_agent TEXT NULL,
                last_seen_at TEXT NULL,
                expires_at TEXT NOT NULL,
                revoked_at TEXT NULL,
                created_at TEXT NOT NULL
            )',
        );
        $this->connection->execute(
            'CREATE TABLE audit_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id TEXT NULL,
                platform_user_id TEXT NULL,
                action TEXT NOT NULL,
                subject_type TEXT NULL,
                subject_id TEXT NULL,
                reason TEXT NULL,
                metadata TEXT NULL,
                ip_address TEXT NULL,
                user_agent TEXT NULL,
                previous_hash TEXT NULL,
                event_hash TEXT NULL,
                created_at TEXT NOT NULL
            )',
        );
    }

    private function assertNoPlaintextSecretsInAudit(
        PlatformAdminBootstrapResult $bootstrap,
        ?string $sessionToken = null,
    ): void {
        $auditRows = $this->connection->execute('SELECT * FROM audit_events')->fetchAll('assoc');
        $auditJson = json_encode($auditRows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->assertIsString($auditJson);
        $this->assertStringNotContainsString($bootstrap->initialPassword->reveal(), $auditJson);
        $this->assertStringNotContainsString($bootstrap->totpSecret->reveal(), $auditJson);
        foreach ($bootstrap->recoveryCodes as $recoveryCode) {
            $this->assertStringNotContainsString($recoveryCode->reveal(), $auditJson);
        }
        if ($sessionToken !== null) {
            $this->assertStringNotContainsString($sessionToken, $auditJson);
        }
    }
}
