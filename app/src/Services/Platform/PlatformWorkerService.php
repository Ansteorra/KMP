<?php
declare(strict_types=1);

namespace App\Services\Platform;

use Closure;
use Throwable;

/**
 * Runs one bounded scheduler and queue cycle for a fleet worker execution.
 */
class PlatformWorkerService
{
    private readonly Closure $clock;

    /**
     * @param \App\Services\Platform\PlatformScheduleRunner $scheduleRunner Timed schedule dispatcher
     * @param \App\Services\Platform\PlatformQueueDrainService $queueDrainService Fleet queue processor
     * @param \App\Services\Platform\PlatformJobRunner $platformJobRunner Platform job processor
     * @param \Closure|null $clock Optional monotonic clock returning seconds
     */
    public function __construct(
        private readonly PlatformScheduleRunner $scheduleRunner,
        private readonly PlatformQueueDrainService $queueDrainService,
        private readonly PlatformJobRunner $platformJobRunner,
        ?Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn(): float => hrtime(true) / 1_000_000_000;
    }

    /**
     * @param callable $commandRunner Callable receiving (object|string $command, list<string> $args): int|null
     * @return array{
     *     schedules: array{schedules: int, completed: int, failed: int, jobsCreated: int},
     *     queues: array{
     *         default: int,
     *         tenants: array<string, int>,
     *         failures: array<string, string>,
     *         duplicateTenants: list<string>,
     *         deferredTenants: list<string>,
     *         datasourcesProcessed: int,
     *         jobsProcessed: int,
     *         elapsedMs: float
     *     },
     *     platformJobs: array{claimed: int, completed: int, failed: int},
     *     errors: array<string, string>,
     *     summary: array{
     *         schedulesDispatched: int,
     *         datasourcesProcessed: int,
     *         queueJobsProcessed: int,
     *         platformJobsCompleted: int,
     *         platformJobsFailed: int
     *     },
     *     elapsedMs: float
     * }
     */
    public function run(
        int $scheduleLimit,
        int $maxJobs,
        int $maxRuntimeSeconds,
        int $cycleBudgetSeconds,
        int $platformLimit,
        callable $commandRunner,
    ): array {
        $startedAt = ($this->clock)();
        $errors = [];
        $schedules = ['schedules' => 0, 'completed' => 0, 'failed' => 0, 'jobsCreated' => 0];
        $queues = [
            'default' => 0,
            'tenants' => [],
            'failures' => [],
            'duplicateTenants' => [],
            'deferredTenants' => [],
            'datasourcesProcessed' => 0,
            'jobsProcessed' => 0,
            'elapsedMs' => 0.0,
        ];
        $platformJobs = ['claimed' => 0, 'completed' => 0, 'failed' => 0];

        try {
            $schedules = $this->scheduleRunner->runDue($scheduleLimit);
        } catch (Throwable $exception) {
            $errors['schedules'] = PlatformScheduleRunner::scrubError($exception->getMessage());
        }

        try {
            $queues = $this->queueDrainService->drain(
                $maxJobs,
                $maxRuntimeSeconds,
                $cycleBudgetSeconds,
            );
        } catch (Throwable $exception) {
            $errors['queues'] = PlatformScheduleRunner::scrubError($exception->getMessage());
        }

        try {
            $platformJobs = $this->platformJobRunner->run($platformLimit, $commandRunner);
        } catch (Throwable $exception) {
            $errors['platformJobs'] = PlatformScheduleRunner::scrubError($exception->getMessage());
        }

        return [
            'schedules' => $schedules,
            'queues' => $queues,
            'platformJobs' => $platformJobs,
            'errors' => $errors,
            'summary' => [
                'schedulesDispatched' => $schedules['completed'],
                'datasourcesProcessed' => $queues['datasourcesProcessed'],
                'queueJobsProcessed' => $queues['jobsProcessed'],
                'platformJobsCompleted' => $platformJobs['completed'],
                'platformJobsFailed' => $platformJobs['failed'],
            ],
            'elapsedMs' => round((($this->clock)() - $startedAt) * 1000, 2),
        ];
    }
}
