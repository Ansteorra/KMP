<?php
declare(strict_types=1);

namespace App\Services\Platform;

use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use RuntimeException;
use Throwable;

/**
 * Drains executable Platform Admin jobs.
 */
class PlatformJobRunner
{
    public const JOB_TENANT_PROVISION = 'tenant_provision';

    /**
     * @var list<string>
     */
    private const EXECUTABLE_JOB_TYPES = [
        self::JOB_TENANT_PROVISION,
    ];

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
                $this->runJob($job, $commandRunner);
                $completed++;
            } catch (Throwable $exception) {
                $this->markFailed($job, $exception);
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
                'modified_at' => $now,
            ], [
                'id' => $row['id'],
                'status' => 'queued',
            ]);
            if ($statement->rowCount() > 0) {
                $row['status'] = 'running';
                $row['started_at'] = $row['started_at'] ?: $now;
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
        );

        $now = $this->now();
        $this->connection()->update('platform_jobs', [
            'tenant_id' => $result->tenantId(),
            'status' => 'completed',
            'last_error' => null,
            'finished_at' => $now,
            'modified_at' => $now,
        ], ['id' => $job['id']]);
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
    private function markFailed(array $job, Throwable $exception): void
    {
        $now = $this->now();
        $this->connection()->update('platform_jobs', [
            'status' => 'failed',
            'last_error' => PlatformScheduleRunner::scrubError($exception->getMessage()),
            'finished_at' => $now,
            'modified_at' => $now,
        ], ['id' => $job['id']]);
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
