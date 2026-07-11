<?php
declare(strict_types=1);

namespace App\Services\Platform;

use App\Command\AgeUpMembersCommand;
use App\Command\BackupCheckCommand;
use App\Command\PlatformBackupsPruneCommand;
use App\Command\PlatformJobsRunCommand;
use App\Command\PlatformMetricsPruneCommand;
use App\Command\SyncActiveWindowStatusesCommand;
use App\Command\SyncMemberWarrantableStatusesCommand;
use App\Command\WorkflowSchedulerCommand;
use App\KMP\TenantContext;
use App\KMP\TenantMetadata;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOutput;
use Cake\Log\Log;
use InvalidArgumentException;
use RuntimeException;

class AllowlistedPlatformScheduleDispatcher implements PlatformScheduleDispatcherInterface
{
    private const COMMAND_NOOP = 'platform:noop';
    private const COMMAND_SHARED_QUEUE_FANOUT = 'platform:shared-queue-fanout';
    private const COMMAND_RUN_CAKE_COMMAND = 'platform:run-cake-command';
    private const COMMAND_RUN_PLATFORM_JOBS = 'platform:run-platform-jobs';

    /**
     * Constructor.
     *
     * @param \App\Services\Platform\TenantQueueDrainService|null $tenantQueueDrainService Tenant queue worker
     */
    public function __construct(private readonly ?TenantQueueDrainService $tenantQueueDrainService = null)
    {
    }

    /**
     * @var array<string, class-string<\Cake\Command\Command>>
     */
    private const TENANT_SAFE_COMMANDS = [
        'workflow_scheduler' => WorkflowSchedulerCommand::class,
        'sync_active_window_statuses' => SyncActiveWindowStatusesCommand::class,
        'sync_member_warrantable_statuses' => SyncMemberWarrantableStatusesCommand::class,
        'age_up_members' => AgeUpMembersCommand::class,
        'backup_check' => BackupCheckCommand::class,
        'platform_backups_prune' => PlatformBackupsPruneCommand::class,
        'platform_metrics_prune' => PlatformMetricsPruneCommand::class,
    ];

    /**
     * @inheritDoc
     */
    public function dispatch(array $schedule, ?TenantMetadata $tenant): void
    {
        $command = (string)($schedule['command'] ?? '');
        match ($command) {
            self::COMMAND_NOOP => null,
            self::COMMAND_SHARED_QUEUE_FANOUT => $this->dispatchSharedQueue($schedule, $tenant),
            self::COMMAND_RUN_CAKE_COMMAND => $this->runCakeCommand($schedule),
            self::COMMAND_RUN_PLATFORM_JOBS => $this->runPlatformJobs($schedule),
            default => throw new InvalidArgumentException(sprintf(
                'Platform schedule command "%s" is not allowlisted.',
                $command,
            )),
        };
    }

    /**
     * @param array<string, mixed> $schedule Platform schedule row
     * @param \App\KMP\TenantMetadata|null $tenant Tenant target, if any
     * @return void
     */
    private function dispatchSharedQueue(array $schedule, ?TenantMetadata $tenant): void
    {
        if ($tenant === null || TenantContext::tryCurrent()?->id !== $tenant->id) {
            throw new RuntimeException('Shared tenant queue processing requires the matching tenant context.');
        }
        if ($this->tenantQueueDrainService === null) {
            throw new RuntimeException('Tenant queue processing is not configured in the application container.');
        }

        $payload = (array)($schedule['payload'] ?? []);
        $maxJobs = $this->boundedPayloadInteger(
            $payload,
            'max_jobs',
            TenantQueueDrainService::DEFAULT_MAX_JOBS,
            TenantQueueDrainService::MAX_JOBS,
        );
        $maxRuntime = $this->boundedPayloadInteger(
            $payload,
            'max_runtime',
            TenantQueueDrainService::DEFAULT_MAX_RUNTIME_SECONDS,
            TenantQueueDrainService::MAX_RUNTIME_SECONDS,
        );
        $processed = $this->tenantQueueDrainService->drain($maxJobs, $maxRuntime);

        Log::info(sprintf(
            'Tenant queue drain attempted %d job(s) for tenant %s.',
            $processed,
            $tenant->slug,
        ), ['scope' => ['platform']]);
    }

    /**
     * Read a bounded positive integer from schedule payload.
     *
     * @param array<string, mixed> $payload Schedule payload
     */
    private function boundedPayloadInteger(array $payload, string $key, int $default, int $maximum): int
    {
        $value = $payload[$key] ?? $default;
        $validated = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => [
                'min_range' => 1,
                'max_range' => $maximum,
            ],
        ]);
        if ($validated === false) {
            throw new RuntimeException(sprintf(
                'Schedule payload "%s" must be an integer between 1 and %d.',
                $key,
                $maximum,
            ));
        }

        return $validated;
    }

    /**
     * Run a small allowlist of tenant-safe legacy commands inside the current tenant scope.
     *
     * @param array<string, mixed> $schedule Platform schedule row
     * @return void
     */
    private function runCakeCommand(array $schedule): void
    {
        $payload = (array)($schedule['payload'] ?? []);
        $name = (string)($payload['command'] ?? '');
        if (!isset(self::TENANT_SAFE_COMMANDS[$name])) {
            throw new InvalidArgumentException(sprintf('Cake command "%s" is not tenant-schedule allowlisted.', $name));
        }

        $arguments = $this->stringList($payload['arguments'] ?? []);
        $options = $this->options($payload['options'] ?? []);
        $argumentNames = $this->stringList($payload['argumentNames'] ?? []);
        $className = self::TENANT_SAFE_COMMANDS[$name];
        $command = new $className();
        $io = $this->quietIo();

        $result = $command->execute(new Arguments($arguments, $options, $argumentNames), $io);
        if ($result !== Command::CODE_SUCCESS && $result !== null) {
            throw new RuntimeException(sprintf('Cake command "%s" exited with status %d.', $name, $result));
        }
    }

    /**
     * Run queued platform-admin jobs from a platform-scoped schedule.
     *
     * @param array<string, mixed> $schedule Platform schedule row
     * @return void
     */
    private function runPlatformJobs(array $schedule): void
    {
        $payload = (array)($schedule['payload'] ?? []);
        $limit = max(1, min(100, (int)($payload['limit'] ?? 10)));
        $io = $this->quietIo();

        $result = (new PlatformJobsRunCommand())->execute(
            new Arguments([], ['limit' => (string)$limit], []),
            $io,
        );
        if ($result !== Command::CODE_SUCCESS && $result !== null) {
            throw new RuntimeException(sprintf('Platform jobs runner exited with status %d.', $result));
        }
    }

    /**
     * @param mixed $value Raw value
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map(static fn(mixed $item): string => (string)$item, $value));
    }

    /**
     * @param mixed $value Raw value
     * @return array<string, array<string>|string|bool|null>
     */
    private function options(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $options = [];
        foreach ($value as $key => $option) {
            if (is_array($option)) {
                $options[(string)$key] = array_map(static fn(mixed $item): string => (string)$item, $option);
            } elseif (is_bool($option) || is_string($option) || $option === null) {
                $options[(string)$key] = $option;
            } else {
                $options[(string)$key] = (string)$option;
            }
        }

        return $options;
    }

    /**
     * Create non-interactive command I/O without emitting nested command output.
     */
    private function quietIo(): ConsoleIo
    {
        $stdout = tmpfile();
        $stderr = tmpfile();
        if ($stdout === false || $stderr === false) {
            throw new RuntimeException('Unable to create temporary platform schedule output streams.');
        }
        $io = new ConsoleIo(new ConsoleOutput($stdout), new ConsoleOutput($stderr));
        $io->setInteractive(false);

        return $io;
    }
}
