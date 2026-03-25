<?php

declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Services\RestoreStatusService;
use App\Test\TestCase\BaseTestCase;
use Cake\Cache\Cache;

class RestoreStatusServiceTest extends BaseTestCase
{
    protected ?RestoreStatusService $service = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();

        if (!in_array('restore_status', Cache::configured(), true)) {
            Cache::setConfig('restore_status', [
                'className' => 'Cake\Cache\Engine\ArrayEngine',
                'duration' => '+1 hours',
            ]);
        }

        $this->clearRestoreCache();
        $this->service = new RestoreStatusService();
    }

    protected function tearDown(): void
    {
        $this->clearRestoreCache();
        parent::tearDown();
    }

    private function clearRestoreCache(): void
    {
        try {
            Cache::clear('restore_status');
        } catch (\Exception $e) {
            // Cache engine may not support clear
        }
    }

    public function testInstantiation(): void
    {
        $this->assertInstanceOf(RestoreStatusService::class, $this->service);
    }

    public function testGetStatusDefaultIsIdle(): void
    {
        $status = $this->service->getStatus();

        $this->assertIsArray($status);
        $this->assertFalse($status['locked']);
        $this->assertEquals('idle', $status['status']);
        $this->assertEquals('idle', $status['phase']);
        $this->assertNull($status['started_at']);
        $this->assertNull($status['completed_at']);
    }

    public function testAcquireLockSucceeds(): void
    {
        $result = $this->service->acquireLock(['actor' => 'admin']);
        $this->assertTrue($result);
        $this->assertTrue($this->service->isLocked());
    }

    public function testAcquireLockFailsWhenAlreadyLocked(): void
    {
        $this->service->acquireLock();
        $secondAttempt = $this->service->acquireLock();
        $this->assertFalse($secondAttempt);
    }

    public function testReleaseLock(): void
    {
        $this->service->acquireLock();
        $this->assertTrue($this->service->isLocked());

        $this->service->releaseLock();
        $this->assertFalse($this->service->isLocked());
    }

    public function testIsLockedReturnsFalseInitially(): void
    {
        $this->assertFalse($this->service->isLocked());
    }

    public function testUpdateStatusChangesPhaseAndMessage(): void
    {
        $this->service->acquireLock();
        $this->service->updateStatus('importing', 'Processing table members...');

        $status = $this->service->getStatus();
        $this->assertEquals('running', $status['status']);
        $this->assertEquals('importing', $status['phase']);
        $this->assertEquals('Processing table members...', $status['message']);
    }

    public function testUpdateStatusWithContext(): void
    {
        $this->service->acquireLock();
        $this->service->updateStatus('importing', 'Processing...', [
            'table_count' => 10,
            'tables_processed' => 3,
            'current_table' => 'members',
        ]);

        $status = $this->service->getStatus();
        $this->assertEquals(10, $status['table_count']);
        $this->assertEquals(3, $status['tables_processed']);
        $this->assertEquals('members', $status['current_table']);
    }

    public function testMarkCompleted(): void
    {
        $this->service->acquireLock();
        $this->service->markCompleted('Restore completed successfully', [
            'rows_processed' => 5000,
        ]);

        $status = $this->service->getStatus();
        $this->assertFalse($status['locked']);
        $this->assertEquals('completed', $status['status']);
        $this->assertEquals('completed', $status['phase']);
        $this->assertEquals('Restore completed successfully', $status['message']);
        $this->assertNotNull($status['completed_at']);
        $this->assertEquals(5000, $status['rows_processed']);
    }

    public function testMarkFailed(): void
    {
        $this->service->acquireLock();
        $this->service->markFailed('SQL import error');

        $status = $this->service->getStatus();
        $this->assertFalse($status['locked']);
        $this->assertEquals('failed', $status['status']);
        $this->assertEquals('failed', $status['phase']);
        $this->assertEquals('SQL import error', $status['message']);
        $this->assertNotNull($status['completed_at']);
    }

    public function testMarkCompletedReleasesLock(): void
    {
        $this->service->acquireLock();
        $this->assertTrue($this->service->isLocked());

        $this->service->markCompleted('Done');
        $this->assertFalse($this->service->isLocked());
    }

    public function testMarkFailedReleasesLock(): void
    {
        $this->service->acquireLock();
        $this->assertTrue($this->service->isLocked());

        $this->service->markFailed('Error');
        $this->assertFalse($this->service->isLocked());
    }

    public function testGetStatusAfterLockReleasedWithRunningStateDetectsInterrupted(): void
    {
        $this->service->acquireLock();
        $this->service->updateStatus('importing', 'In progress...');

        // Simulate lock expiring by deleting it directly
        $this->service->releaseLock();

        $status = $this->service->getStatus();
        $this->assertEquals('failed', $status['status']);
        $this->assertEquals('interrupted', $status['phase']);
        $this->assertStringContainsString('lock expired', $status['message']);
    }

    public function testAcquireLockSetsContext(): void
    {
        $this->service->acquireLock([
            'source' => 'manual',
            'backup_id' => 'backup-123',
            'actor' => 'admin@test.com',
        ]);

        $status = $this->service->getStatus();
        $this->assertEquals('manual', $status['source']);
        $this->assertEquals('backup-123', $status['backup_id']);
        $this->assertEquals('admin@test.com', $status['actor']);
    }

    public function testAcquireLockMinimumTtl(): void
    {
        // TTL below minimum 60 should be forced to 60
        $result = $this->service->acquireLock([], 10);
        $this->assertTrue($result);

        $status = $this->service->getStatus();
        $this->assertNotNull($status['expires_at']);
    }

    public function testDefaultStatusKeys(): void
    {
        $status = $this->service->getStatus();

        $expectedKeys = [
            'locked', 'status', 'phase', 'message',
            'started_at', 'updated_at', 'completed_at', 'expires_at',
            'source', 'backup_id', 'actor',
            'table_count', 'tables_processed',
            'row_count', 'rows_processed', 'current_table',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $status, "Missing key: $key");
        }
    }

    public function testFullLifecycle(): void
    {
        // Idle
        $this->assertEquals('idle', $this->service->getStatus()['status']);

        // Acquire lock
        $this->assertTrue($this->service->acquireLock(['actor' => 'test']));

        // Update progress
        $this->service->updateStatus('importing', 'Table 1 of 3', ['tables_processed' => 1]);
        $status = $this->service->getStatus();
        $this->assertEquals('running', $status['status']);

        // Complete
        $this->service->markCompleted('All done');
        $status = $this->service->getStatus();
        $this->assertEquals('completed', $status['status']);
        $this->assertFalse($status['locked']);
    }
}
