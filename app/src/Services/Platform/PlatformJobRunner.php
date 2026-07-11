<?php
declare(strict_types=1);

namespace App\Services\Platform;

use App\Command\PlatformBackupCommand;
use App\Command\TenantBackupCommand;
use App\Command\TenantRestoreCommand;
use App\Services\Backups\PlatformDatabaseBackupService;
use App\Services\Backups\TenantBackupService;
use App\Services\Backups\TenantRestoreService;
use Cake\Command\Command;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Cake\Log\Log;
use RuntimeException;
use Throwable;

/**
 * Drains executable Platform Admin jobs.
 */
class PlatformJobRunner
{
    public const JOB_TENANT_PROVISION = 'tenant_provision';
    public const JOB_TENANT_BACKUP = TenantBackupService::JOB_TYPE;
    public const JOB_TENANT_RESTORE = TenantRestoreService::JOB_TYPE;
    public const JOB_PLATFORM_BACKUP = PlatformDatabaseBackupService::JOB_TYPE;

    /**
     * @var list<string>
     */
    private const EXECUTABLE_JOB_TYPES = [
        self::JOB_TENANT_PROVISION,
        self::JOB_TENANT_BACKUP,
        self::JOB_TENANT_RESTORE,
        self::JOB_PLATFORM_BACKUP,
    ];

    /**
     * Return whether this runner can execute a platform job type.
     */
    public static function supports(string $jobType): bool
    {
        return in_array($jobType, self::EXECUTABLE_JOB_TYPES, true);
    }

    /**
     * Constructor.
     *
     * @param \Cake\Database\Connection|null $connection Optional platform connection override
     */
    public function __construct(private readonly ?Connection $connection = null)
    {
    }

    /**
     * @param callable $commandRunner Callable receiving (object|string $command, list<string> $args): int|null
     * @return array{claimed: int, completed: int, failed: int}
     */
    public function run(int $limit, callable $commandRunner): array
    {
        if ($limit < 1 || $limit > 100) {
            throw new RuntimeException('Platform job runner limit must be between 1 and 100.');
        }

        $jobs = $this->claimQueuedJobs($limit);
        $completed = 0;
        $failed = 0;

        foreach ($jobs as $job) {
            try {
                $this->recordEvent($job, 'info', 'job.started', 'Job execution started.');
                $this->runJob($job, $commandRunner);
                $this->markCompleted($job);
                $this->recordEvent($job, 'success', 'job.completed', 'Job completed successfully.');
                $completed++;
            } catch (Throwable $exception) {
                $failureMessage = $this->markFailed($job, $exception);
                $this->recordEvent($job, 'error', 'job.failed', $failureMessage);
                $failed++;
            }
        }

        return [
            'claimed' => count($jobs),
            'completed' => $completed,
            'failed' => $failed,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function claimQueuedJobs(int $limit): array
    {
        $connection = $this->connection();
        $rows = $connection->execute(
            sprintf(
                "SELECT * FROM platform_jobs
                  WHERE status = :status
                    AND job_type IN ('%s')
               ORDER BY created_at ASC
                  LIMIT %d",
                implode("','", self::EXECUTABLE_JOB_TYPES),
                $limit,
            ),
            ['status' => 'queued'],
        )->fetchAll('assoc');

        $now = $this->now();
        $claimed = [];
        foreach ($rows as $row) {
            $statement = $connection->update('platform_jobs', [
                'status' => 'running',
                'started_at' => $row['started_at'] ?: $now,
                'last_error' => null,
                'modified_at' => $now,
            ], [
                'id' => $row['id'],
                'status' => 'queued',
            ]);
            if ($statement->rowCount() > 0) {
                $row['status'] = 'running';
                $row['started_at'] = $row['started_at'] ?: $now;
                $row['last_error'] = null;
                $claimed[] = $row;
            }
        }

        return $claimed;
    }

    /**
     * @param array<string, mixed> $job Platform job row
     * @param callable $commandRunner Callable receiving (object|string $command, list<string> $args): int|null
     */
    private function runJob(array $job, callable $commandRunner): void
    {
        match ((string)$job['job_type']) {
            self::JOB_TENANT_PROVISION => $this->runTenantProvisioning($job, $commandRunner),
            self::JOB_TENANT_BACKUP => $this->runTenantBackup($job, $commandRunner),
            self::JOB_TENANT_RESTORE => $this->runTenantRestore($job, $commandRunner),
            self::JOB_PLATFORM_BACKUP => $this->runPlatformBackup($job, $commandRunner),
            default => throw new RuntimeException(sprintf(
                'Platform job type "%s" is not executable.',
                $job['job_type'],
            )),
        };
    }

    /**
     * @param array<string, mixed> $job Platform job row
     * @param callable $commandRunner Callable receiving (object|string $command, list<string> $args): int|null
     */
    private function runTenantProvisioning(array $job, callable $commandRunner): void
    {
        $parameters = $this->parameters($job);
        $request = TenantProvisioningRequest::fromArray($parameters);
        $result = (new TenantProvisioningService($this->connection()))->provision(
            $request,
            $commandRunner,
            function (string $level, string $message) use ($job): void {
                $this->recordEvent($job, $level, 'tenant_provision.progress', $message);
            },
        );

        $now = $this->now();
        $this->connection()->update('platform_jobs', [
            'tenant_id' => $result->tenantId(),
            'modified_at' => $now,
        ], ['id' => $job['id']]);
    }

    /**
     * @param array<string, mixed> $job
     * @param callable $commandRunner
     */
    private function runTenantBackup(array $job, callable $commandRunner): void
    {
        $parameters = $this->parameters($job);
        $this->runCommand($commandRunner, TenantBackupCommand::class, [
            '--tenant',
            $this->requiredString($parameters, 'tenant_slug'),
            '--retention-days',
            (string)$this->retentionDays($parameters),
            '--platform-job-id',
            (string)$job['id'],
        ]);
    }

    /**
     * @param array<string, mixed> $job
     * @param callable $commandRunner
     */
    private function runTenantRestore(array $job, callable $commandRunner): void
    {
        $parameters = $this->parameters($job);
        $this->runCommand($commandRunner, TenantRestoreCommand::class, [
            '--backup',
            $this->requiredString($parameters, 'backup_id'),
            '--mode',
            TenantRestoreService::MODE_SAME_TENANT,
            '--target-tenant',
            $this->tenantRestoreSlug($parameters),
            '--confirm-destructive',
            '--platform-job-id',
            (string)$job['id'],
        ]);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function tenantRestoreSlug(array $parameters): string
    {
        $tenantSlug = trim((string)($parameters['tenant_slug'] ?? $parameters['target_tenant_slug'] ?? ''));
        if ($tenantSlug === '') {
            throw new RuntimeException('Platform job parameter "tenant_slug" is required.');
        }

        return $tenantSlug;
    }

    /**
     * @param array<string, mixed> $job
     * @param callable $commandRunner
     */
    private function runPlatformBackup(array $job, callable $commandRunner): void
    {
        $parameters = $this->parameters($job);
        $this->runCommand($commandRunner, PlatformBackupCommand::class, [
            '--retention-days',
            (string)$this->retentionDays($parameters),
            '--platform-job-id',
            (string)$job['id'],
        ]);
    }

    /**
     * @param callable $commandRunner
     * @param class-string<\Cake\Command\Command> $command
     * @param list<string> $arguments
     */
    private function runCommand(callable $commandRunner, string $command, array $arguments): void
    {
        $result = $commandRunner($command, $arguments);
        if ($result !== null && (int)$result !== Command::CODE_SUCCESS) {
            throw new RuntimeException(sprintf('Platform job command exited with status %d.', (int)$result));
        }
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function requiredString(array $parameters, string $key): string
    {
        $value = trim((string)($parameters[$key] ?? ''));
        if ($value === '') {
            throw new RuntimeException(sprintf('Platform job parameter "%s" is required.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function retentionDays(array $parameters): int
    {
        $days = (int)($parameters['retention_days'] ?? 30);
        if ($days < 1 || $days > 365) {
            throw new RuntimeException('Platform job retention_days must be between 1 and 365.');
        }

        return $days;
    }

    /**
     * @param array<string, mixed> $job Platform job row
     * @return array<string, mixed>
     */
    private function parameters(array $job): array
    {
        $parameters = json_decode((string)($job['parameters'] ?? ''), true);
        if (!is_array($parameters)) {
            throw new RuntimeException('Platform job parameters are invalid JSON.');
        }

        return $parameters;
    }

    /**
     * @param array<string, mixed> $job Platform job row
     */
    private function markFailed(array $job, Throwable $exception): string
    {
        $persistedError = $this->connection()->execute(
            'SELECT last_error FROM platform_jobs WHERE id = :id LIMIT 1',
            ['id' => $job['id']],
        )->fetchColumn(0);
        $failureMessage = trim((string)$persistedError);
        if ($failureMessage === '') {
            $failureMessage = $exception->getMessage();
        }
        $failureMessage = PlatformScheduleRunner::scrubError($failureMessage);
        $now = $this->now();
        $this->connection()->update('platform_jobs', [
            'status' => 'failed',
            'last_error' => $failureMessage,
            'finished_at' => $now,
            'modified_at' => $now,
        ], ['id' => $job['id']]);

        return $failureMessage;
    }

    /**
     * @param array<string, mixed> $job
     */
    private function markCompleted(array $job): void
    {
        $now = $this->now();
        $this->connection()->update('platform_jobs', [
            'status' => 'completed',
            'last_error' => null,
            'finished_at' => $now,
            'modified_at' => $now,
        ], ['id' => $job['id']]);
    }

    /**
     * @param array<string, mixed> $job
     */
    private function recordEvent(array $job, string $level, string $code, string $message): void
    {
        try {
            (new PlatformJobEventService($this->connection()))->record(
                (string)$job['id'],
                $level,
                $code,
                $message,
            );
        } catch (Throwable $exception) {
            Log::warning(sprintf('Platform job event write failed: %s', $exception::class));
        }
    }

    /**
     * Return the platform connection.
     */
    private function connection(): Connection
    {
        if ($this->connection !== null) {
            return $this->connection;
        }

        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('platform');

        return $connection;
    }

    /**
     * Return current UTC database timestamp.
     */
    private function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}
