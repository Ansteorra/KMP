<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Security;

use App\Services\Security\RequestRateLimiter;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\Http\Exception\ServiceUnavailableException;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;

class RequestRateLimiterTest extends TestCase
{
    private Connection $connection;

    private const CONFIG = 'request_rate_limiter_test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = new Connection(['driver' => Sqlite::class, 'database' => ':memory:']);
        $this->connection->execute('CREATE TABLE security_rate_limits (bucket_key VARCHAR(64) PRIMARY KEY, attempts INT, expires_at BIGINT)');
    }

    public function testAllowsRequestsWithinLimit(): void
    {
        $limiter = new RequestRateLimiter($this->connection);

        for ($i = 0; $i < 10; $i++) {
            $result = $limiter->attempt(RequestRateLimiter::BUCKET_EMAIL_TAKEN, '203.0.113.10');
            $this->assertTrue($result->allowed, "Attempt {$i} should be allowed");
        }
    }

    public function testBlocksAfterLimitExceeded(): void
    {
        $limiter = new RequestRateLimiter($this->connection);

        for ($i = 0; $i < 10; $i++) {
            $limiter->attempt(RequestRateLimiter::BUCKET_EMAIL_TAKEN, '203.0.113.11');
        }

        $blocked = $limiter->attempt(RequestRateLimiter::BUCKET_EMAIL_TAKEN, '203.0.113.11');
        $this->assertFalse($blocked->allowed);
        $this->assertSame(0, $blocked->remaining);
        $this->assertGreaterThan(0, $blocked->retryAfterSeconds);
    }

    public function testBucketsAreIndependent(): void
    {
        $limiter = new RequestRateLimiter($this->connection);

        for ($i = 0; $i < 10; $i++) {
            $limiter->attempt(RequestRateLimiter::BUCKET_EMAIL_TAKEN, '203.0.113.12');
        }

        $searchResult = $limiter->attempt(RequestRateLimiter::BUCKET_SEARCH_MEMBERS, '203.0.113.12');
        $this->assertTrue($searchResult->allowed);
    }

    public function testUnknownBucketThrows(): void
    {
        $limiter = new RequestRateLimiter($this->connection);

        $this->expectException(InvalidArgumentException::class);
        $limiter->attempt('unknown.bucket', '203.0.113.13');
    }

    public function testDatabaseFailureDoesNotAllowRequests(): void
    {
        $this->connection->execute('DROP TABLE security_rate_limits');
        $this->expectException(ServiceUnavailableException::class);
        (new RequestRateLimiter($this->connection))->attempt(RequestRateLimiter::BUCKET_EMAIL_TAKEN, 'synthetic');
    }

    public function testParallelWorkersCannotExceedTheSharedLimit(): void
    {
        $database = tempnam(sys_get_temp_dir(), 'kmp-rate-test-');
        $runner = tempnam(sys_get_temp_dir(), 'kmp-rate-runner-');
        $connection = new Connection(['driver' => Sqlite::class, 'database' => $database]);
        $connection->execute('CREATE TABLE security_rate_limits (bucket_key TEXT PRIMARY KEY, attempts INT, expires_at BIGINT)');
        $autoload = var_export(ROOT . '/vendor/autoload.php', true);
        file_put_contents($runner, '<?php require ' . $autoload . ';' . <<<'PHP'
\Cake\Core\Configure::write('KMP.tenancy.enabled', false);
\Cake\Utility\Security::setSalt('synthetic-concurrency-test-salt');
$connection = new \Cake\Database\Connection(['driver' => \Cake\Database\Driver\Sqlite::class, 'database' => $argv[1]]);
$connection->execute('PRAGMA busy_timeout = 10000');
$limiter = new \App\Services\Security\RequestRateLimiter($connection, static fn(): int => 1800);
echo $limiter->attempt($limiter::BUCKET_EMAIL_TAKEN, 'synthetic-client')->allowed ? '1' : '0';
PHP);
        $workers = [];
        try {
            for ($index = 0; $index < 16; $index++) {
                $pipes = [];
                $process = proc_open(
                    [PHP_BINARY, $runner, $database],
                    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                    $pipes,
                    null,
                    ['XDEBUG_MODE' => 'off'],
                );
                $this->assertIsResource($process);
                $workers[] = [$process, $pipes];
            }
            $allowed = 0;
            foreach ($workers as [$process, $pipes]) {
                $output = stream_get_contents($pipes[1]);
                $errors = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $this->assertSame(0, proc_close($process), substr($errors, 0, 200));
                $allowed += $output === '1' ? 1 : 0;
            }
            $this->assertSame(10, $allowed);
        } finally {
            unlink($runner);
            unlink($database);
        }
    }
}
