<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Platform;

use App\KMP\TenantContext;
use App\Services\Platform\PlatformQueueDrainService;
use App\Services\Platform\QueueDrainService;
use App\Services\TenantConnectionManager;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\TestSuite\TestCase;
use RuntimeException;

class PlatformQueueDrainServiceTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = new Connection([
            'driver' => new Sqlite(),
            'database' => ':memory:',
        ]);
        $this->connection->execute(
            'CREATE TABLE tenants (
                id VARCHAR(36),
                slug VARCHAR(64),
                display_name VARCHAR(255),
                status VARCHAR(32),
                db_server VARCHAR(255),
                db_name VARCHAR(255),
                db_role VARCHAR(255),
                schema_version VARCHAR(64),
                tenant_config TEXT
            )',
        );
    }

    public function testDrainProcessesDefaultAndEveryActiveTenant(): void
    {
        $this->insertTenant('beta', 'active');
        $this->insertTenant('alpha', 'active');
        $this->insertTenant('disabled', 'disabled');

        $queueDrain = $this->createMock(QueueDrainService::class);
        $queueDrain->expects($this->once())
            ->method('drainDefault')
            ->with(100, 45)
            ->willReturn(8);
        $queueDrain->expects($this->exactly(2))
            ->method('drainTenant')
            ->with(100, 45)
            ->willReturnCallback(static fn(): int => TenantContext::slug() === 'alpha' ? 3 : 5);
        $connectionManager = $this->createMock(TenantConnectionManager::class);
        $connectionManager->expects($this->exactly(2))
            ->method('withTenant')
            ->willReturnCallback(
                static fn($tenant, $callback): mixed => TenantContext::with($tenant, $callback),
            );

        $result = (new PlatformQueueDrainService(
            $queueDrain,
            $connectionManager,
            $this->connection,
            '',
            null,
            static fn(): float => 0.0,
        ))->drain();

        $this->assertSame(8, $result['default']);
        $this->assertSame(['alpha' => 3, 'beta' => 5], $result['tenants']);
        $this->assertSame([], $result['failures']);
    }

    public function testDrainContinuesAfterTenantFailure(): void
    {
        $this->insertTenant('alpha', 'active');
        $this->insertTenant('beta', 'active');

        $queueDrain = $this->createMock(QueueDrainService::class);
        $queueDrain->method('drainDefault')->willReturn(0);
        $queueDrain->method('drainTenant')->willReturn(2);
        $connectionManager = $this->createMock(TenantConnectionManager::class);
        $connectionManager->method('withTenant')
            ->willReturnCallback(static function ($tenant, $callback): mixed {
                if ($tenant->slug === 'alpha') {
                    throw new RuntimeException('password=secret host=db.internal');
                }

                return TenantContext::with($tenant, $callback);
            });

        $result = (new PlatformQueueDrainService(
            $queueDrain,
            $connectionManager,
            $this->connection,
        ))->drain();

        $this->assertSame(['beta' => 2], $result['tenants']);
        $this->assertSame(['alpha' => 'password=[redacted] host=db.internal'], $result['failures']);
    }

    public function testDrainSkipsTenantThatUsesDefaultPhysicalDatasource(): void
    {
        $this->insertTenant('alpha', 'active');
        $this->insertTenant('beta', 'active');

        $queueDrain = $this->createMock(QueueDrainService::class);
        $queueDrain->method('drainDefault')->willReturn(1);
        $queueDrain->expects($this->once())
            ->method('drainTenant')
            ->willReturn(2);
        $connectionManager = $this->createMock(TenantConnectionManager::class);
        $connectionManager->expects($this->once())
            ->method('withTenant')
            ->willReturnCallback(
                static fn($tenant, $callback): mixed => TenantContext::with($tenant, $callback),
            );

        $result = (new PlatformQueueDrainService(
            $queueDrain,
            $connectionManager,
            $this->connection,
            'db|alpha',
        ))->drain();

        $this->assertSame(['beta' => 2], $result['tenants']);
        $this->assertSame(['alpha'], $result['duplicateTenants']);
    }

    public function testDrainRotatesTenantStartingPointForFairness(): void
    {
        $this->insertTenant('alpha', 'active');
        $this->insertTenant('beta', 'active');
        $visited = [];

        $queueDrain = $this->createMock(QueueDrainService::class);
        $queueDrain->method('drainDefault')->willReturn(0);
        $queueDrain->method('drainTenant')->willReturn(0);
        $connectionManager = $this->createMock(TenantConnectionManager::class);
        $connectionManager->method('withTenant')
            ->willReturnCallback(static function ($tenant, $callback) use (&$visited): mixed {
                $visited[] = $tenant->slug;

                return TenantContext::with($tenant, $callback);
            });

        (new PlatformQueueDrainService(
            $queueDrain,
            $connectionManager,
            $this->connection,
            '',
            static fn(): float => 0.0,
            static fn(): float => 60.0,
        ))->drain();

        $this->assertSame(['beta', 'alpha'], $visited);
    }

    public function testDrainDefersRemainingTenantsAtOverallDeadline(): void
    {
        $this->insertTenant('alpha', 'active');
        $this->insertTenant('beta', 'active');
        $times = [0.0, 0.0, 2.0, 2.0];

        $queueDrain = $this->createMock(QueueDrainService::class);
        $queueDrain->expects($this->once())
            ->method('drainDefault')
            ->with(100, 1)
            ->willReturn(0);
        $queueDrain->expects($this->never())->method('drainTenant');
        $connectionManager = $this->createMock(TenantConnectionManager::class);
        $connectionManager->expects($this->never())->method('withTenant');

        $result = (new PlatformQueueDrainService(
            $queueDrain,
            $connectionManager,
            $this->connection,
            '',
            static function () use (&$times): float {
                return array_shift($times) ?? 2.0;
            },
            static fn(): float => 0.0,
        ))->drain(cycleBudgetSeconds: 1);

        $this->assertSame(['alpha', 'beta'], $result['deferredTenants']);
    }

    private function insertTenant(string $slug, string $status): void
    {
        $this->connection->insert('tenants', [
            'id' => sprintf('tenant-%s', $slug),
            'slug' => $slug,
            'display_name' => ucfirst($slug),
            'status' => $status,
            'db_server' => 'db',
            'db_name' => $slug,
            'db_role' => $slug,
            'schema_version' => null,
            'tenant_config' => '{}',
        ]);
    }
}
