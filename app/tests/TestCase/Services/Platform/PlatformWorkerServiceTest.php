<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Platform;

use App\Services\Platform\PlatformJobRunner;
use App\Services\Platform\PlatformQueueDrainService;
use App\Services\Platform\PlatformScheduleRunner;
use App\Services\Platform\PlatformWorkerService;
use Cake\TestSuite\TestCase;
use RuntimeException;

class PlatformWorkerServiceTest extends TestCase
{
    public function testRunExecutesEveryLaneAndReturnsStructuredSummary(): void
    {
        $scheduleRunner = $this->createMock(PlatformScheduleRunner::class);
        $scheduleRunner->expects($this->once())
            ->method('runDue')
            ->with(50)
            ->willReturn(['schedules' => 2, 'completed' => 2, 'failed' => 0, 'jobsCreated' => 1]);
        $queueDrain = $this->createMock(PlatformQueueDrainService::class);
        $queueDrain->expects($this->once())
            ->method('drain')
            ->with(75, 30, 180)
            ->willReturn([
                'default' => 4,
                'tenants' => ['alpha' => 3],
                'failures' => [],
                'duplicateTenants' => ['primary'],
                'deferredTenants' => [],
                'datasourcesProcessed' => 2,
                'jobsProcessed' => 7,
                'elapsedMs' => 12.5,
            ]);
        $platformJobRunner = $this->createMock(PlatformJobRunner::class);
        $platformJobRunner->expects($this->once())
            ->method('run')
            ->with(1, $this->isType('callable'))
            ->willReturn(['claimed' => 1, 'completed' => 1, 'failed' => 0]);
        $times = [10.0, 10.25];

        $result = (new PlatformWorkerService(
            $scheduleRunner,
            $queueDrain,
            $platformJobRunner,
            static function () use (&$times): float {
                return array_shift($times) ?? 10.25;
            },
        ))->run(50, 75, 30, 180, 1, static fn(): int => 0);

        $this->assertSame(2, $result['schedules']['completed']);
        $this->assertSame(4, $result['queues']['default']);
        $this->assertSame(1, $result['platformJobs']['completed']);
        $this->assertSame(2, $result['summary']['datasourcesProcessed']);
        $this->assertSame(7, $result['summary']['queueJobsProcessed']);
        $this->assertSame([], $result['errors']);
        $this->assertSame(250.0, $result['elapsedMs']);
    }

    public function testRunContinuesOtherLanesAfterScheduleFailure(): void
    {
        $scheduleRunner = $this->createMock(PlatformScheduleRunner::class);
        $scheduleRunner->method('runDue')->willThrowException(new RuntimeException('schedule unavailable'));
        $queueDrain = $this->createMock(PlatformQueueDrainService::class);
        $queueDrain->expects($this->once())
            ->method('drain')
            ->willReturn([
                'default' => 1,
                'tenants' => [],
                'failures' => [],
                'duplicateTenants' => [],
                'deferredTenants' => [],
                'datasourcesProcessed' => 1,
                'jobsProcessed' => 1,
                'elapsedMs' => 1.0,
            ]);
        $platformJobRunner = $this->createMock(PlatformJobRunner::class);
        $platformJobRunner->expects($this->once())
            ->method('run')
            ->willReturn(['claimed' => 0, 'completed' => 0, 'failed' => 0]);

        $result = (new PlatformWorkerService(
            $scheduleRunner,
            $queueDrain,
            $platformJobRunner,
        ))->run(10, 10, 10, 30, 1, static fn(): int => 0);

        $this->assertSame(1, $result['queues']['default']);
        $this->assertSame('schedule unavailable', $result['errors']['schedules']);
    }
}
