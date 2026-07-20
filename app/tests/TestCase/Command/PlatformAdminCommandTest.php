<?php
declare(strict_types=1);

namespace App\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;

class PlatformAdminCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    private string $secretFile;

    protected function setUp(): void
    {
        parent::setUp();
        $directory = dirname(__DIR__, 3) . DS . 'tmp' . DS . 'tests';
        if (!is_dir($directory)) {
            mkdir($directory, 0770, true);
        }
        chmod($directory, 0770);
        $this->secretFile = $directory . DS . 'platform-admin-command-secrets.json';
        if (is_file($this->secretFile)) {
            unlink($this->secretFile);
        }
        Configure::write('Secrets', [
            'driver' => 'file',
            'drivers' => [
                'file' => [
                    'path' => $this->secretFile,
                    'environment' => 'test',
                    'allowInEnvironments' => ['test'],
                ],
            ],
        ]);
        $this->resetPlatformConnection();
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        if (is_file($this->secretFile)) {
            unlink($this->secretFile);
        }
        ConnectionManager::drop('platform');
        parent::tearDown();
    }

    public function testBootstrapPrintsFirstLoginSecretsByDefault(): void
    {
        $this->exec('platform admin bootstrap --email admin@example.org');

        $this->assertExitSuccess();
        $this->assertOutputContains('Platform admin bootstrap created the first platform user.');
        $this->assertOutputRegExp('/Initial password \(shown once\): [A-Za-z0-9_-]{32}/');
        $this->assertOutputContains('TOTP secret (shown once):');
        $this->assertOutputContains('Recovery codes (shown once):');

        $secretPayload = json_decode((string)file_get_contents($this->secretFile), true);
        $this->assertIsArray($secretPayload);
        $secretRows = (array)($secretPayload['secrets'] ?? []);
        $secretRecord = reset($secretRows);
        $this->assertIsArray($secretRecord);
        $totpSecret = (string)($secretRecord['value'] ?? '');
        $this->assertNotSame('', $totpSecret);
        $this->assertOutputContains($totpSecret);

        $connection = ConnectionManager::get('platform');
        $this->assertInstanceOf(Connection::class, $connection);
        $user = $connection->execute('SELECT password_hash FROM platform_users WHERE email = ?', ['admin@example.org'])
            ->fetch('assoc');
        $this->assertIsArray($user);
        $output = implode("\n", $this->_out->messages());
        $this->assertMatchesRegularExpression('/Initial password \(shown once\): (?<password>[A-Za-z0-9_-]{32})/', $output);
        preg_match('/Initial password \(shown once\): (?<password>[A-Za-z0-9_-]{32})/', $output, $matches);
        $this->assertTrue(password_verify((string)$matches['password'], (string)$user['password_hash']));

        $recoveryRows = $connection->execute('SELECT code_hash FROM platform_user_recovery_codes')
            ->fetchAll('assoc');
        $this->assertCount(10, $recoveryRows);
        foreach ($recoveryRows as $row) {
            $this->assertOutputNotContains((string)$row['code_hash']);
        }
    }

    public function testResetPasswordPrintsReplacementPasswordByDefault(): void
    {
        $connection = ConnectionManager::get('platform');
        $this->assertInstanceOf(Connection::class, $connection);
        $connection->insert('platform_users', [
            'id' => 'platform-admin-1',
            'email' => 'admin@example.org',
            'password_hash' => password_hash('OldPassword', PASSWORD_DEFAULT),
            'status' => 'active',
            'totp_secret_ref' => 'platform.admin.platform-admin-1.totp',
            'totp_enrolled_at' => '2026-05-16 12:00:00',
            'failed_login_count' => 5,
            'locked_until' => '2026-05-17 12:00:00',
            'last_login_at' => null,
            'created_at' => '2026-05-16 12:00:00',
            'modified_at' => null,
        ]);

        $this->exec(
            'platform admin reset-password --email admin@example.org --reason "bootstrap output was not captured"',
        );

        $this->assertExitSuccess();
        $this->assertOutputContains('Platform admin password reset for: admin@example.org');
        $this->assertOutputRegExp('/New password \(shown once\): [A-Za-z0-9_-]{32}/');
        $output = implode("\n", $this->_out->messages());
        preg_match('/New password \(shown once\): (?<password>[A-Za-z0-9_-]{32})/', $output, $matches);
        $row = $connection->execute('SELECT password_hash, failed_login_count, locked_until FROM platform_users')
            ->fetch('assoc');
        $this->assertIsArray($row);
        $this->assertTrue(password_verify((string)$matches['password'], (string)$row['password_hash']));
        $this->assertSame(0, (int)$row['failed_login_count']);
        $this->assertNull($row['locked_until']);
    }

    private function resetPlatformConnection(): void
    {
        ConnectionManager::drop('platform');
        ConnectionManager::setConfig('platform', [
            'className' => Connection::class,
            'driver' => 'Cake\Database\Driver\Sqlite',
            'database' => ':memory:',
            'cacheMetadata' => false,
            'quoteIdentifiers' => false,
        ]);
    }

    private function createSchema(): void
    {
        $connection = ConnectionManager::get('platform');
        $this->assertInstanceOf(Connection::class, $connection);
        $connection->execute(
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
        $connection->execute(
            'CREATE TABLE platform_user_recovery_codes (
                id TEXT PRIMARY KEY,
                platform_user_id TEXT NOT NULL,
                code_hash TEXT NOT NULL,
                used_at TEXT NULL,
                created_at TEXT NOT NULL
            )',
        );
        $connection->execute(
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
        $connection->execute(
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
}
