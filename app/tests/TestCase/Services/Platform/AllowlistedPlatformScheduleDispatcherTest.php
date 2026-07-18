<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Platform;

use App\KMP\TenantContext;
use App\KMP\TenantMetadata;
use App\Services\Platform\AllowlistedPlatformScheduleDispatcher;
use App\Services\Platform\QueueDrainService;
use Cake\TestSuite\TestCase;
use RuntimeException;

class AllowlistedPlatformScheduleDispatcherTest extends TestCase
{
    public function testSharedQueueFanoutDrainsMatchingTenantWithPayloadBounds(): void
    {
        $service = $this->createMock(QueueDrainService::class);
        $service->expects($this->once())
            ->method('drainTenant')
            ->with(9, 13)
            ->willReturn(2);
        $tenant = $this->tenant();
        $dispatcher = new AllowlistedPlatformScheduleDispatcher($service);

        $processed = TenantContext::with($tenant, static function () use ($dispatcher, $tenant): int {
            return $dispatcher->dispatch([
                'command' => 'platform:shared-queue-fanout',
                'payload' => [
                    'max_jobs' => 9,
                    'max_runtime' => 13,
                ],
            ], $tenant);
        });

        $this->assertSame(2, $processed);
    }

    public function testDefaultQueueDrainsInPlatformScope(): void
    {
        $service = $this->createMock(QueueDrainService::class);
        $service->expects($this->once())
            ->method('drainDefault')
            ->with(12, 17)
            ->willReturn(4);
        $dispatcher = new AllowlistedPlatformScheduleDispatcher($service);

        $processed = $dispatcher->dispatch([
            'command' => 'platform:shared-default-queue',
            'payload' => [
                'max_jobs' => 12,
                'max_runtime' => 17,
            ],
        ], null);

        $this->assertSame(4, $processed);
    }

    public function testDefaultQueueRejectsTenantScope(): void
    {
        $dispatcher = new AllowlistedPlatformScheduleDispatcher(
            $this->createStub(QueueDrainService::class),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must run in platform scope');

        $dispatcher->dispatch([
            'command' => 'platform:shared-default-queue',
            'payload' => [],
        ], $this->tenant());
    }

    public function testSharedQueueFanoutRequiresTenantContext(): void
    {
        $dispatcher = new AllowlistedPlatformScheduleDispatcher(
            $this->createStub(QueueDrainService::class),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('matching tenant context');

        $dispatcher->dispatch([
            'command' => 'platform:shared-queue-fanout',
            'payload' => [],
        ], $this->tenant());
    }

    public function testSharedQueueFanoutRejectsUnsafeLimits(): void
    {
        $tenant = $this->tenant();
        $dispatcher = new AllowlistedPlatformScheduleDispatcher(
            $this->createStub(QueueDrainService::class),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"max_jobs" must be an integer between');

        TenantContext::with($tenant, static function () use ($dispatcher, $tenant): void {
            $dispatcher->dispatch([
                'command' => 'platform:shared-queue-fanout',
                'payload' => ['max_jobs' => 1000],
            ], $tenant);
        });
    }

    private function tenant(): TenantMetadata
    {
        return new TenantMetadata(
            '11111111-1111-4111-8111-111111111111',
            'alpha',
            'Alpha',
            'active',
            'db',
            'alpha',
            'alpha',
        );
    }
}
