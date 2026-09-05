<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Backups;

use App\Services\Backups\PgDumpPlatformDatabaseBackupDumper;
use App\Services\Secrets\SensitiveString;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

class PgDumpPlatformDatabaseBackupDumperTest extends TestCase
{
    private string $directory;
    private array $originalEnvironment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir() . '/kmp-dump-process-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
        file_put_contents($this->directory . '/pg_dump', '#!' . PHP_BINARY . "\n<?php\n" . <<<'FAKE'
$captured = ['argv' => $argv];
foreach (['PGPASSWORD', 'PGSSLMODE', 'PGSSLKEY', 'PGSSLCERT', 'PGSSLROOTCERT', 'PGHOSTADDR', 'PGSERVICE'] as $key) {
    $captured[$key] = getenv($key);
}
file_put_contents(__DIR__ . '/capture.json', json_encode($captured, JSON_THROW_ON_ERROR));
file_put_contents($argv[array_search('--file', $argv, true) + 1], 'synthetic database dump');
FAKE);
        chmod($this->directory . '/pg_dump', 0700);
        foreach (['PATH', 'PGHOSTADDR', 'PGSERVICE', 'PGSSLMODE', 'PGSSLROOTCERT'] as $variable) {
            $this->originalEnvironment[$variable] = getenv($variable);
        }
        putenv('PATH=' . $this->directory . ':' . (getenv('PATH') ?: ''));
        putenv('PGHOSTADDR=203.0.113.45');
        putenv('PGSERVICE=unrelated_service');
        putenv('PGSSLMODE=disable');
        putenv('PGSSLROOTCERT=/unrelated/ca.pem');
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnvironment as $variable => $value) {
            putenv($value === false ? $variable : $variable . '=' . $value);
        }
        foreach (glob($this->directory . '/*') as $path) {
            unlink($path);
        }
        rmdir($this->directory);
        parent::tearDown();
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function connectionPolicies(): array
    {
        return [
            'explicit TLS' => [[
                'port' => 6543, 'ssl' => true, 'ssl_mode' => 'verify-full',
                'ssl_ca' => '/synthetic/ca.pem', 'ssl_cert' => '/synthetic/client.pem', 'ssl_key' => '/synthetic/client.key',
            ], '6543', 'verify-full'],
            'legacy URL TLS' => [['sslmode' => 'require'], '5432', 'require'],
            'default connection' => [[], '5432', 'prefer'],
        ];
    }

    /**
     * @param array<string, mixed> $overrides Connection options
     * @param string $port Expected process port
     * @param string $sslMode Expected libpq TLS mode
     * @return void
     */
    #[DataProvider('connectionPolicies')]
    public function testDumpUsesOnlySelectedConnectionPolicy(array $overrides, string $port, string $sslMode): void
    {
        $config = array_merge([
            'host' => 'db.example.test', 'username' => 'platform_runtime', 'database' => 'platform',
        ], $overrides);
        $outputPath = $this->directory . '/backup.dump';
        $result = (new PgDumpPlatformDatabaseBackupDumper())->dump(
            $config,
            new SensitiveString('synthetic-selected-password'),
            $outputPath,
        );
        $captured = json_decode((string)file_get_contents($this->directory . '/capture.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($port, $captured['argv'][array_search('--port', $captured['argv'], true) + 1]);
        $this->assertSame('db.example.test', $captured['argv'][array_search('--host', $captured['argv'], true) + 1]);
        $this->assertSame('platform_runtime', $captured['argv'][array_search('--username', $captured['argv'], true) + 1]);
        $this->assertSame('synthetic-selected-password', $captured['PGPASSWORD']);
        $this->assertSame($sslMode, $captured['PGSSLMODE']);
        $this->assertSame($overrides['ssl_ca'] ?? false, $captured['PGSSLROOTCERT']);
        $this->assertSame($overrides['ssl_cert'] ?? false, $captured['PGSSLCERT']);
        $this->assertSame($overrides['ssl_key'] ?? false, $captured['PGSSLKEY']);
        $this->assertFalse($captured['PGHOSTADDR']);
        $this->assertFalse($captured['PGSERVICE']);
        $this->assertSame($outputPath, $result->path);
        $this->assertSame(strlen('synthetic database dump'), $result->sizeBytes);
        $this->assertStringNotContainsString('password', implode(' ', $result->argv));
    }

    public function testOutOfRangePortFailsBeforeLaunchingDump(): void
    {
        try {
            (new PgDumpPlatformDatabaseBackupDumper())->dump([
                'host' => 'db.example.test', 'username' => 'platform_runtime', 'database' => 'platform', 'port' => 65536,
            ], new SensitiveString('synthetic-selected-password'), $this->directory . '/backup.dump');
            $this->fail('Invalid port must reject the backup.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Unsafe platform database port for pg_dump.', $exception->getMessage());
            $this->assertFileDoesNotExist($this->directory . '/capture.json');
            $this->assertFileDoesNotExist($this->directory . '/backup.dump');
        }
    }
}
