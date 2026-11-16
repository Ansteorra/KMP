<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Services\PostgresPool;
use App\Test\TestCase\BaseTestCase;
use InvalidArgumentException;
use RuntimeException;

class PostgresPoolTest extends BaseTestCase
{
    public function testRoutesSeparateServersAndUsersAndRetainRealCredentials(): void
    {
        $a = $this->connection();
        $b = array_replace($a, ['host' => 'other.example', 'username' => 'other', 'password' => 'other-secret']);
        $built = PostgresPool::build([$a, $b], '/run/kmp-db-pool', 2, 3);
        $this->assertCount(2, $built['manifest']['routes']);
        $this->assertNotSame(PostgresPool::alias($a), PostgresPool::alias($b));
        $this->assertStringContainsString('max_db_connections=3', $built['ini']);
        $this->assertStringContainsString('server_tls_sslmode = verify-full', $built['ini']);
        $this->assertStringContainsString('server_reset_query = DISCARD ALL', $built['ini']);
        $this->assertStringNotContainsString('user=', $built['ini']);
        $this->assertStringNotContainsString($a['password'], json_encode($built['manifest']));
        $this->assertStringContainsString('local all all reject', $built['hba']);
    }

    public function testRemovedDatabaseKeepsItsBudgetSlotWithoutGrantingAccess(): void
    {
        $a = $this->connection();
        $b = array_replace($a, ['database' => 'second']);
        $old = PostgresPool::build([$a, $b], '/run/kmp-db-pool', 2, 2);
        $new = PostgresPool::build([$a], '/run/kmp-db-pool', 2, 2, $old['manifest']);
        $this->assertCount(2, $new['manifest']['routes']);
        $this->assertSame([], $new['manifest']['routes'][PostgresPool::alias($b)]['credentials']);
        $this->assertStringNotContainsString(PostgresPool::alias($b), $new['hba']);
        $this->expectException(RuntimeException::class);
        PostgresPool::build([$a, array_replace($a, ['database' => 'third'])], '/run/kmp-db-pool', 2, 2, $new['manifest']);
    }

    public function testRotatedOrUnknownCredentialsCannotUseAnOldManifest(): void
    {
        $directory = sys_get_temp_dir() . '/kmp-pool-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $config = $this->connection();
        $built = PostgresPool::build([$config], '/run/kmp-db-pool', 1, 2);
        file_put_contents($directory . '/routes.json', json_encode($built['manifest']));
        try {
            $this->assertStringContainsString(PostgresPool::alias($config), PostgresPool::dsn($config, $directory));
            foreach ([['password' => 'rotated'], ['username' => 'other'], ['database' => 'other']] as $change) {
                try {
                    PostgresPool::dsn(array_replace($config, $change), $directory);
                    $this->fail('Stale or unknown pool credentials were accepted.');
                } catch (RuntimeException $exception) {
                    $this->assertStringNotContainsString($config['password'], $exception->getMessage());
                }
            }
        } finally {
            unlink($directory . '/routes.json');
            rmdir($directory);
        }
    }

    public function testConflictingPasswordsAndControlCharactersAreRejected(): void
    {
        $config = $this->connection();
        foreach (
            [
            [$config, array_replace($config, ['password' => 'different'])],
            [array_replace($config, ['host' => "bad\n[pgbouncer]"])],
            [$config, array_replace($config, ['ssl' => false])],
            ] as $connections
        ) {
            try {
                PostgresPool::build($connections, '/run/kmp-db-pool', 4, 2);
                $this->fail('Unsafe pool configuration was accepted.');
            } catch (InvalidArgumentException $exception) {
                $this->assertStringNotContainsString($config['password'], $exception->getMessage());
            }
        }
    }

    /** @return array<string, mixed> */
    private function connection(): array
    {
        return ['host' => 'postgres.example', 'port' => 5432, 'database' => 'tenant',
            'username' => 'tenant_user', 'password' => 'test-secret', 'ssl' => true];
    }
}
