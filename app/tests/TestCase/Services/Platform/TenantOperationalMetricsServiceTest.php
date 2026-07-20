<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Platform;

use App\Services\Platform\TenantOperationalMetricsService;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\TestSuite\TestCase;
use Cake\Utility\Text;

class TenantOperationalMetricsServiceTest extends TestCase
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
            'CREATE TABLE tenant_request_metrics_hourly (
                id TEXT PRIMARY KEY,
                tenant_id TEXT NOT NULL,
                metric_hour TEXT NOT NULL,
                route_name TEXT NOT NULL,
                request_count INTEGER NOT NULL,
                error_count INTEGER NOT NULL,
                server_error_count INTEGER NOT NULL,
                slow_request_count INTEGER NOT NULL,
                duration_total_ms INTEGER NOT NULL,
                duration_max_ms INTEGER NOT NULL,
                created_at TEXT NOT NULL,
                modified_at TEXT NULL,
                UNIQUE (tenant_id, metric_hour, route_name)
            )',
        );
    }

    public function testRecordAggregatesStatusAndLatencyWithoutRawRequestData(): void
    {
        $service = new TenantOperationalMetricsService($this->connection, 1000);

        $service->record('tenant-1', 'Members/view', 200, 125, '2026-07-10 12:15:00');
        $service->record('tenant-1', 'Members/view', 500, 1500, '2026-07-10 12:45:00');

        $metric = $this->connection->execute(
            'SELECT * FROM tenant_request_metrics_hourly WHERE tenant_id = ?',
            ['tenant-1'],
        )->fetch('assoc');
        $this->assertSame('Members/view', $metric['route_name']);
        $this->assertSame(2, (int)$metric['request_count']);
        $this->assertSame(1, (int)$metric['error_count']);
        $this->assertSame(1, (int)$metric['server_error_count']);
        $this->assertSame(1, (int)$metric['slow_request_count']);
        $this->assertSame(1625, (int)$metric['duration_total_ms']);
        $this->assertSame(1500, (int)$metric['duration_max_ms']);
    }

    public function testUnsafeRouteInputIsCollapsedToUnrouted(): void
    {
        $service = new TenantOperationalMetricsService($this->connection);

        $service->record(
            'tenant-1',
            '/members/42?token=super-secret',
            404,
            20,
            '2026-07-10 12:15:00',
        );

        $route = $this->connection->execute(
            'SELECT route_name FROM tenant_request_metrics_hourly',
        )->fetchColumn(0);
        $this->assertSame('unrouted', $route);
        $this->assertStringNotContainsString('super-secret', (string)$route);
    }

    public function testPruneDeletesOnlyMetricsOutsideRetentionWindow(): void
    {
        $this->insertMetric('old', '2020-01-01 00:00:00');
        $this->insertMetric('current', gmdate('Y-m-d H:00:00'));

        $deleted = (new TenantOperationalMetricsService($this->connection))->prune(30);

        $this->assertSame(1, $deleted);
        $remaining = $this->connection->execute(
            'SELECT route_name FROM tenant_request_metrics_hourly',
        )->fetchColumn(0);
        $this->assertSame('current', $remaining);
    }

    private function insertMetric(string $routeName, string $metricHour): void
    {
        $this->connection->insert('tenant_request_metrics_hourly', [
            'id' => Text::uuid(),
            'tenant_id' => 'tenant-1',
            'metric_hour' => $metricHour,
            'route_name' => $routeName,
            'request_count' => 1,
            'error_count' => 0,
            'server_error_count' => 0,
            'slow_request_count' => 0,
            'duration_total_ms' => 10,
            'duration_max_ms' => 10,
            'created_at' => $metricHour,
            'modified_at' => null,
        ]);
    }
}
