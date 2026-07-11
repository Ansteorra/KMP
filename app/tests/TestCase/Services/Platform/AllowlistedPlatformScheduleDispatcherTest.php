<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Platform;

use App\KMP\TenantContext;
use App\KMP\TenantMetadata;
use App\Services\Platform\AllowlistedPlatformScheduleDispatcher;
use App\Services\Platform\TenantQueueDrainService;
use Cake\TestSuite\TestCase;
use RuntimeException;

class AllowlistedPlatformScheduleDispatcherTest extends TestCase
{
    public function testSharedQueueFanoutDrainsMatchingTenantWithPayloadBounds(): void
    {
        $service = $this->createMock(TenantQueueDrainService::class);
        $service->expects($this->once())
            ->method('drain')
            ->with(9, 13)
            ->willReturn(2);
        $tenant = $this->tenant();
        $dispatcher = new AllowlistedPlatformScheduleDispatcher($service);

        TenantContext::with($tenant, static function () use ($dispatcher, $tenant): void {
            $dispatcher->dispatch([
                'command' => 'platform:shared-queue-fanout',
                'payload' => [
                    'max_jobs' => 9,
                    'max_runtime' => 13,
                ],
            ], $tenant);
        });
    }

    public function testSharedQueueFanoutRequiresTenantContext(): void
    {
        $dispatcher = new AllowlistedPlatformScheduleDispatcher(
            $this->createStub(TenantQueueDrainService::class),
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
            $this->createStub(TenantQueueDrainService::class),
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
