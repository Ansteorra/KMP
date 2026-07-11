<?php
declare(strict_types=1);

// phpcs:disable CakePHP.Commenting.FunctionComment.Missing, Generic.PHP.NoSilencedErrors.Discouraged

namespace App\Services\Backups;

use App\KMP\TenantMetadata;
use App\Services\Secrets\SensitiveString;
use RuntimeException;

class PgRestoreTenantBackupRestorer implements TenantBackupRestorerInterface
{
    public function validate(TenantMetadata $targetTenant, string $backupPath): void
    {
        $this->buildArgv($targetTenant, $backupPath);
    }

    /**
     * @return list<string>
     */
    public function buildArgv(TenantMetadata $targetTenant, string $backupPath): array
    {
        $this->assertSafeHost($targetTenant->dbServer);
        $this->assertSafeIdentifier($targetTenant->dbName, 'database name');
        $this->assertSafeIdentifier($targetTenant->dbRole, 'database role');
        $this->assertSafePath($backupPath);

        return [
            'pg_restore',
            '--clean',
            '--if-exists',
            '--no-owner',
            '--no-privileges',
            '--host',
            $targetTenant->dbServer,
            '--username',
            $targetTenant->dbRole,
            '--dbname',
            $targetTenant->dbName,
            $backupPath,
        ];
    }

    public function restore(
        TenantMetadata $targetTenant,
        SensitiveString $databasePassword,
        string $backupPath,
        ?callable $progressReporter = null,
    ): array {
        if (!is_file($backupPath)) {
            throw new RuntimeException('Decrypted tenant backup file is missing.');
        }
        $argv = $this->buildArgv($targetTenant, $backupPath);
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $processEnv = getenv();
        $env = array_merge(is_array($processEnv) ? $processEnv : [], ['PGPASSWORD' => $databasePassword->reveal()]);
        $process = proc_open($argv, $descriptorSpec, $pipes, null, $env);
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start pg_restore process.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw new RuntimeException(sprintf(
                'pg_restore failed with exit code %d: %s',
                $exitCode,
                $this->redactProcessOutput((string)$stderr . "\n" . (string)$stdout),
            ));
        }

        return [];
    }

    private function assertSafeIdentifier(string $identifier, string $label): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,62}$/', $identifier)) {
            throw new RuntimeException(sprintf('Unsafe tenant %s for pg_restore.', $label));
        }
    }

    private function assertSafeHost(string $host): void
    {
        if (
            $host === ''
            || strlen($host) > 255
            || str_starts_with($host, '-')
            || !preg_match('/^[A-Za-z0-9][A-Za-z0-9.-]*[A-Za-z0-9]$/', $host)
        ) {
            throw new RuntimeException('Unsafe tenant database host for pg_restore.');
        }
    }

    private function assertSafePath(string $path): void
    {
        if ($path === '' || str_contains($path, "\0")) {
            throw new RuntimeException('Unsafe pg_restore input path.');
        }
    }

    private function redactProcessOutput(string $output): string
    {
        $output = (string)preg_replace('/PGPASSWORD=[^\s]+/i', 'PGPASSWORD=[redacted]', $output);
        $output = (string)preg_replace('/password\s*[:=]\s*[^\s]+/i', 'password=[redacted]', $output);

        return mb_substr(trim($output), 0, 2000);
    }
}
