<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Platform;

use App\Services\Platform\TenantProvisioningRequest;
use App\Services\Platform\TenantProvisioningService;
use Cake\Core\Configure;
use Cake\Database\Connection;
use Cake\Database\Driver\Postgres;
use Cake\Database\StatementInterface;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

class TenantProvisioningBoundaryTest extends TestCase
{
    private array $originalEnvironment = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (
            [
            'DATABASE_URL' => 'postgres://default_runtime:synthetic-runtime-password@db.example.test/default_database',
            'PLATFORM_DATABASE_URL' => 'postgres://platform_runtime:synthetic-runtime-password@db.example.test/platform',
            ] as $variable => $value
        ) {
            $this->originalEnvironment[$variable] = [getenv($variable), $_ENV[$variable] ?? null, $_SERVER[$variable] ?? null];
            putenv($variable . '=' . $value);
            $_ENV[$variable] = $value;
            $_SERVER[$variable] = $value;
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnvironment as $variable => [$process, $environment, $server]) {
            putenv($process === false ? $variable : $variable . '=' . $process);
            unset($_ENV[$variable], $_SERVER[$variable]);
            if ($environment !== null) {
                $_ENV[$variable] = $environment;
            }
            if ($server !== null) {
                $_SERVER[$variable] = $server;
            }
        }
        parent::tearDown();
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidTargets(): array
    {
        return [
            'administrative role' => [['dbRole' => 'schema_owner'], '', 'administrative database role'],
            'missing runtime URL' => [[], 'missing_runtime_url', 'Explicit runtime PostgreSQL URLs'],
            'platform runtime role' => [['dbRole' => 'platform_runtime'], '', 'platform runtime role'],
            'default runtime role for new database' => [['dbRole' => 'default_runtime'], '', 'default runtime role'],
            'first registration cannot claim default runtime role' => [[
                'dbRole' => 'default_runtime', 'dbName' => 'default_database',
            ], '', 'default runtime role'],
            'registered default role for wrong database' => [[
                'dbRole' => 'default_runtime', 'dbName' => 'other_database',
            ], 'registered_other_default', 'default runtime role'],
            'existing default binding reaches remaining preflight' => [[
                'dbRole' => 'default_runtime', 'dbName' => 'default_database', 'smokeTable' => 'invalid;table',
            ], 'registered_default', 'Invalid smoke-test table'],
            'platform database' => [['dbName' => 'platform'], '', 'platform database'],
            'different database server' => [['dbServer' => 'other.example.test'], '', 'tenant server'],
            'privileged runtime role' => [[], 'unsafe', 'privileged database role'],
            'other tenant database' => [['dbName' => 'existing_database'], 'conflict', 'Another registered tenant'],
            'other tenant role' => [['dbRole' => 'existing_role'], 'conflict', 'Another registered tenant'],
            'registered database change' => [['dbName' => 'replacement_database'], 'registered', 'bindings cannot be changed'],
            'registered role change' => [['dbRole' => 'replacement_role'], 'registered', 'bindings cannot be changed'],
            'registered server change' => [['dbServer' => 'other.example.test'], 'registered', 'bindings cannot be changed'],
        ];
    }

    /**
     * Invalid requests must stop before secret, metadata, or PostgreSQL role writes.
     *
     * @param array<string, mixed> $overrides Request overrides
     * @param string $state Synthetic platform metadata state
     * @param string $expectedError Required rejection reason
     * @return void
     */
    #[DataProvider('invalidTargets')]
    public function testInvalidTargetHasNoSideEffects(array $overrides, string $state, string $expectedError): void
    {
        if ($state === 'missing_runtime_url') {
            putenv('PLATFORM_DATABASE_URL');
            unset($_ENV['PLATFORM_DATABASE_URL'], $_SERVER['PLATFORM_DATABASE_URL']);
        }
        $originalAdmin = Configure::read('Database.adminJob');
        $originalSecrets = Configure::read('Secrets');
        $secretPath = sys_get_temp_dir() . '/kmp-provision-rejection-' . bin2hex(random_bytes(8)) . '.json';
        Configure::write('Database.adminJob', true);
        Configure::write('Secrets', [
            'driver' => 'file',
            'drivers' => ['file' => ['path' => $secretPath, 'environment' => 'test', 'allowInEnvironments' => ['test']]],
        ]);
        $platform = $this->createMock(Connection::class);
        $platform->method('config')->willReturn([
            'driver' => Postgres::class,
            'host' => 'db.example.test',
            'username' => 'schema_owner',
            'database' => 'platform',
        ]);
        $platform->method('getDriver')->willReturn(new Postgres([]));
        foreach (['insert', 'update', 'delete'] as $method) {
            $platform->expects($this->never())->method($method);
        }
        $platform->method('execute')->willReturnCallback(function (string $sql, array $params) use ($state): StatementInterface {
            $this->assertStringStartsWith('SELECT ', $sql, 'Preflight must not mutate SQL state.');
            $statement = $this->createStub(StatementInterface::class);
            if (str_contains($sql, 'WHERE slug = ?')) {
                $this->assertSame(['acme'], $params);
                $statement->method('fetch')->willReturn(str_starts_with($state, 'registered') ? [
                    'db_server' => 'db.example.test',
                    'db_name' => match ($state) {
                        'registered_default' => 'default_database',
                        'registered_other_default' => 'other_database',
                        default => 'kmp_tenant_acme',
                    },
                    'db_role' => $state === 'registered' ? 'kmp_tenant_acme_role' : 'default_runtime',
                ] : false);
            } elseif (str_contains($sql, 'WHERE slug <> ?')) {
                $statement->method('fetchColumn')->willReturn($state === 'conflict' ? 1 : false);
            } elseif (str_contains($sql, 'FROM pg_roles')) {
                $statement->method('fetchColumn')->willReturn($state === 'unsafe' ? 1 : false);
            } else {
                $this->fail('Unexpected query during provisioning preflight.');
            }

            return $statement;
        });
        $request = TenantProvisioningRequest::fromArray(array_merge([
            'slug' => 'acme', 'displayName' => 'Acme', 'host' => 'acme.example.test',
            'createDatabase' => true, 'rotatePassword' => true,
        ], $overrides));
        try {
            (new TenantProvisioningService($platform))->provision(
                $request,
                function (): int {
                    $this->fail('Invalid targets must not execute migration commands.');
                },
            );
            $this->fail('Expected provisioning preflight rejection.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($expectedError, $exception->getMessage());
            $this->assertFileDoesNotExist($secretPath);
        } finally {
            Configure::write('Database.adminJob', $originalAdmin);
            Configure::write('Secrets', $originalSecrets);
            if (is_file($secretPath)) {
                unlink($secretPath);
            }
        }
    }
}
