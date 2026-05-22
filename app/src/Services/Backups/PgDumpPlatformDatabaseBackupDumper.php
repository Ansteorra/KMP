<?php
declare(strict_types=1);

// phpcs:disable CakePHP.Commenting.FunctionComment.Missing, Generic.PHP.NoSilencedErrors.Discouraged

namespace App\Services\Backups;

use App\Services\Secrets\SensitiveString;
use RuntimeException;

class PgDumpPlatformDatabaseBackupDumper implements PlatformDatabaseBackupDumperInterface
{
    /**
     * Build a pg_dump argv list without shell interpolation.
     *
     * @param array<string, mixed> $platformConfig Platform datasource configuration
     * @return list<string>
     */
    public function buildArgv(array $platformConfig, string $outputPath): array
    {
        $host = (string)($platformConfig['host'] ?? '');
        $port = (string)($platformConfig['port'] ?? '');
        $database = (string)($platformConfig['database'] ?? '');
        $username = (string)($platformConfig['username'] ?? '');
        $this->assertSafeHost($host);
        $this->assertSafePort($port);
        $this->assertSafeIdentifier($database, 'database name');
        $this->assertSafeIdentifier($username, 'database username');
        $this->assertSafePath($outputPath);

        $argv = [
            'pg_dump',
            '--format=custom',
            '--no-owner',
            '--no-privileges',
            '--host',
            $host,
        ];
        if ($port !== '') {
            $argv[] = '--port';
            $argv[] = $port;
        }
        array_push(
            $argv,
            '--username',
            $username,
            '--dbname',
            $database,
            '--file',
            $outputPath,
        );

        return $argv;
    }

    public function dump(
        array $platformConfig,
        SensitiveString $databasePassword,
        string $outputPath,
    ): TenantBackupDumpResult {
        $argv = $this->buildArgv($platformConfig, $outputPath);
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $processEnv = getenv();
        $env = array_merge(is_array($processEnv) ? $processEnv : [], ['PGPASSWORD' => $databasePassword->reveal()]);
        $process = proc_open($argv, $descriptorSpec, $pipes, null, $env);
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start platform pg_dump process.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw new RuntimeException(sprintf(
                'platform pg_dump failed with exit code %d: %s',
                $exitCode,
                $this->redactProcessOutput((string)$stderr . "\n" . (string)$stdout),
            ));
        }
        if (!is_file($outputPath)) {
            throw new RuntimeException('platform pg_dump completed without creating a backup file.');
        }

        return new TenantBackupDumpResult($outputPath, (int)filesize($outputPath), $argv);
    }

    private function assertSafeIdentifier(string $identifier, string $label): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,62}$/', $identifier)) {
            throw new RuntimeException(sprintf('Unsafe platform %s for pg_dump.', $label));
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
            throw new RuntimeException('Unsafe platform database host for pg_dump.');
        }
    }

    private function assertSafePort(string $port): void
    {
        if ($port !== '' && !preg_match('/^[0-9]{1,5}$/', $port)) {
            throw new RuntimeException('Unsafe platform database port for pg_dump.');
        }
    }

    private function assertSafePath(string $path): void
    {
        if ($path === '' || str_contains($path, "\0")) {
            throw new RuntimeException('Unsafe platform pg_dump output path.');
        }
    }

    private function redactProcessOutput(string $output): string
    {
        $output = (string)preg_replace('/PGPASSWORD=[^\s]+/i', 'PGPASSWORD=[redacted]', $output);
        $output = (string)preg_replace('/password\s*[:=]\s*[^\s]+/i', 'password=[redacted]', $output);

        return mb_substr(trim($output), 0, 2000);
    }
}
