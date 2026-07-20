<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Platform;

use App\Services\Platform\PlatformHealthService;
use App\Services\Platform\PlatformHealthStatus;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;

class PlatformHealthServiceTest extends TestCase
{
    private mixed $platformConfig = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->platformConfig = ConnectionManager::getConfig('platform');
    }

    protected function tearDown(): void
    {
        ConnectionManager::drop('platform');
        if ($this->platformConfig !== null) {
            ConnectionManager::setConfig('platform', $this->platformConfig);
        }
        parent::tearDown();
    }

    public function testCheckReportsHealthyPlatformConnection(): void
    {
        ConnectionManager::drop('platform');
        ConnectionManager::setConfig('platform', [
            'className' => 'Cake\Database\Connection',
            'driver' => Sqlite::class,
            'database' => ':memory:',
            'password' => 'do-not-leak',
            'cacheMetadata' => false,
        ]);

        $status = (new PlatformHealthService('platform'))->check();
        $safeStatus = $status->toSafeArray();

        $this->assertTrue($status->isHealthy());
        $this->assertSame(PlatformHealthStatus::STATE_HEALTHY, $safeStatus['state']);
        $this->assertSame('platform', $safeStatus['connection']);
        $this->assertStringNotContainsString('do-not-leak', json_encode($safeStatus) ?: '');
    }

    public function testCheckReportsDegradedPlatformConnectionSafely(): void
    {
        ConnectionManager::drop('platform');

        $status = (new PlatformHealthService('platform'))->check();
        $safeStatus = $status->toSafeArray();

        $this->assertFalse($status->isHealthy());
        $this->assertSame(PlatformHealthStatus::STATE_DEGRADED, $safeStatus['state']);
        $this->assertSame('Platform metadata database is unavailable.', $safeStatus['message']);
        $this->assertArrayHasKey('error_class', $safeStatus);
        $this->assertArrayNotHasKey('password', $safeStatus);
        $this->assertArrayNotHasKey('host', $safeStatus);
        $this->assertArrayNotHasKey('database', $safeStatus);
    }
}
