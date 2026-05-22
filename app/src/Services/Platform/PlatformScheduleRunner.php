<?php
declare(strict_types=1);

namespace App\Services\Platform;

use App\KMP\TenantMetadata;
use App\Services\TenantConnectionManager;
use Cake\Datasource\ConnectionManager;
use Cake\I18n\DateTime;
use Cake\Utility\Text;
use Cron\CronExpression;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class PlatformScheduleRunner
{
    public const SCOPE_PLATFORM = 'platform';
    public const SCOPE_ALL_ACTIVE_TENANTS = 'all_active_tenants';
    public const SCOPE_SINGLE_TENANT = 'single_tenant';

    /**
     * @var list<string>
     */
    private const VALID_SCOPES = [
        self::SCOPE_PLATFORM,
        self::SCOPE_ALL_ACTIVE_TENANTS,
        self::SCOPE_SINGLE_TENANT,
    ];

    /**
     * Constructor.
     *
     * @param \App\Services\Platform\PlatformScheduleDispatcherInterface|null $dispatcher Optional dispatcher
     * @param \App\Services\TenantConnectionManager|null $tenantConnectionManager Optional tenant connection manager
     */
    public function __construct(
        private readonly ?PlatformScheduleDispatcherInterface $dispatcher = null,
        private readonly ?TenantConnectionManager $tenantConnectionManager = null,
    ) {
    }

    /**
     * Run an enabled platform schedule by name.
     *
     * @param string $name Schedule name
     * @return array<string, int|string>
     */
    public function run(string $name): array
    {
        $connection = ConnectionManager::get('platform');
        $schedule = $this->findSchedule($name);
        if ($schedule === null) {
            throw new RuntimeException(sprintf('Platform schedule "%s" was not found.', $name));
        }

        if (!$this->isTruthy($schedule['enabled'] ?? false)) {
            return [
                'status' => 'skipped',
                'completed' => 0,
                'failed' => 0,
                'jobsCreated' => 0,
            ];
        }

        $scope = (string)$schedule['tenant_scope'];
        if (!in_array($scope, self::VALID_SCOPES, true)) {
            throw new InvalidArgumentException(sprintf('Invalid platform schedule tenant scope "%s".', $scope));
        }
        if (!CronExpression::isValidExpression((string)$schedule['cron_expression'])) {
            throw new InvalidArgumentException(sprintf(
                'Invalid cron expression for platform schedule "%s".',
                $name,
            ));
        }

        $now = $this->now();
        $this->markScheduleStarted($schedule, $now);

        $targets = $this->resolveTargets($schedule);
        $completed = 0;
        $failed = 0;
        $jobsCreated = 0;
        $lastError = null;

        foreach ($targets as $tenant) {
            $jobsCreated++;
            $result = $this->runTarget($schedule, $tenant);
            if ($result['status'] === 'completed') {
                $completed++;
            } else {
                $failed++;
                $lastError = $result['lastError'];
                if ($this->failFast($schedule)) {
                    break;
                }
            }
        }

        $status = $failed > 0 ? 'failed' : 'completed';
        $finishedAt = $this->now();
        $scheduleUpdate = [
            'status' => $status,
            'last_error' => $lastError,
            'modified_at' => $finishedAt,
        ];
        if ($status === 'completed') {
            $scheduleUpdate['last_success_at'] = $finishedAt;
        } else {
            $scheduleUpdate['last_failure_at'] = $finishedAt;
        }
        $connection->update('platform_schedules', $scheduleUpdate, ['id' => $schedule['id']]);

        return [
            'status' => $status,
            'completed' => $completed,
            'failed' => $failed,
            'jobsCreated' => $jobsCreated,
        ];
    }

    /**
     * Redact sensitive values from an error before storing it in platform_jobs.
     *
     * @param string $message Error message
     * @return string
     */
    public static function scrubError(string $message): string
    {
        $message = (string)preg_replace(
            '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i',
            '[redacted-email]',
            $message,
        );
        $message = (string)preg_replace(
            '/\b(password|passwd|pwd|secret|token|api[_-]?key|access[_-]?key)\b\s*[:=]\s*[^\s,;]+/i',
            '$1=[redacted]',
            $message,
        );
        $message = (string)preg_replace(
            '/\b(Bearer|Basic)\s+[A-Za-z0-9._~+\/=-]+/i',
            '$1 [redacted]',
            $message,
        );

        return mb_substr($message, 0, 2000);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findSchedule(string $name): ?array
    {
        $row = ConnectionManager::get('platform')->execute(
            'SELECT * FROM platform_schedules WHERE name = :name LIMIT 1',
            ['name' => $name],
        )->fetch('assoc');

        return is_array($row) ? $this->normalizeSchedule($row) : null;
    }

    /**
     * @param array<string, mixed> $schedule Schedule row
     * @param \Cake\I18n\DateTime $now Start time
     * @return void
     */
    private function markScheduleStarted(array $schedule, DateTime $now): void
    {
        $cron = new CronExpression((string)$schedule['cron_expression']);
        $nextRun = $cron->getNextRunDate($now->format('Y-m-d H:i:s'));
        ConnectionManager::get('platform')->update('platform_schedules', [
            'status' => 'running',
            'last_run_at' => $now,
            'next_run_at' => new DateTime($nextRun->format('Y-m-d H:i:s')),
            'last_error' => null,
            'modified_at' => $now,
        ], ['id' => $schedule['id']]);
    }

    /**
     * @param array<string, mixed> $schedule Schedule row
     * @return list<\App\KMP\TenantMetadata|null>
     */
    private function resolveTargets(array $schedule): array
    {
        return match ((string)$schedule['tenant_scope']) {
            self::SCOPE_PLATFORM => [null],
            self::SCOPE_ALL_ACTIVE_TENANTS => $this->activeTenants(),
            self::SCOPE_SINGLE_TENANT => [$this->singleTenant($schedule)],
            default => throw new InvalidArgumentException('Invalid platform schedule tenant scope.'),
        };
    }

    /**
     * @return list<\App\KMP\TenantMetadata>
     */
    private function activeTenants(): array
    {
        $rows = ConnectionManager::get('platform')->execute(
            'SELECT * FROM tenants WHERE status = :status ORDER BY slug',
            ['status' => 'active'],
        )->fetchAll('assoc');
        if ($rows === []) {
            throw new RuntimeException('No active tenants are available for the platform schedule.');
        }

        return array_map(
            static fn(array $row): TenantMetadata => TenantMetadata::fromPlatformRow($row),
            $rows,
        );
    }

    /**
     * @param array<string, mixed> $schedule Schedule row
     * @return \App\KMP\TenantMetadata
     */
    private function singleTenant(array $schedule): TenantMetadata
    {
        if (empty($schedule['tenant_id'])) {
            throw new InvalidArgumentException('Single-tenant platform schedules require tenant_id.');
        }
        $row = ConnectionManager::get('platform')->execute(
            'SELECT * FROM tenants WHERE id = :tenantId LIMIT 1',
            ['tenantId' => $schedule['tenant_id']],
        )->fetch('assoc');
        if (!is_array($row)) {
            throw new RuntimeException('Platform schedule tenant was not found.');
        }

        return TenantMetadata::fromPlatformRow($row);
    }

    /**
     * @param array<string, mixed> $schedule Schedule row
     * @param \App\KMP\TenantMetadata|null $tenant Tenant target
     * @return array{status: string, lastError: string|null}
     */
    private function runTarget(array $schedule, ?TenantMetadata $tenant): array
    {
        $connection = ConnectionManager::get('platform');
        $jobId = Text::uuid();
        $startedAt = $this->now();
        $connection->insert('platform_jobs', [
            'id' => $jobId,
            'tenant_id' => $tenant?->id,
            'requested_by_platform_user_id' => null,
            'job_type' => 'platform_schedule',
            'status' => 'running',
            'idempotency_key' => null,
            'parameters' => json_encode($this->jobParameters($schedule), JSON_THROW_ON_ERROR),
            'log_uri' => null,
            'last_error' => null,
            'created_at' => $startedAt,
            'started_at' => $startedAt,
            'finished_at' => null,
            'modified_at' => $startedAt,
        ]);

        try {
            $this->dispatchTarget($schedule, $tenant);
            $finishedAt = $this->now();
            $connection->update('platform_jobs', [
                'status' => 'completed',
                'finished_at' => $finishedAt,
                'modified_at' => $finishedAt,
            ], ['id' => $jobId]);

            return ['status' => 'completed', 'lastError' => null];
        } catch (Throwable $e) {
            $error = self::scrubError($e->getMessage());
            $finishedAt = $this->now();
            $connection->update('platform_jobs', [
                'status' => 'failed',
                'last_error' => $error,
                'finished_at' => $finishedAt,
                'modified_at' => $finishedAt,
            ], ['id' => $jobId]);

            return ['status' => 'failed', 'lastError' => $error];
        }
    }

    /**
     * @param array<string, mixed> $schedule Schedule row
     * @param \App\KMP\TenantMetadata|null $tenant Tenant target
     * @return void
     */
    private function dispatchTarget(array $schedule, ?TenantMetadata $tenant): void
    {
        $options = (array)($schedule['options'] ?? []);
        if ($tenant !== null && ($options['requires_tenant_connection'] ?? false)) {
            if ($this->tenantConnectionManager === null) {
                throw new RuntimeException('Tenant connection manager is not configured.');
            }
            $this->tenantConnectionManager->withTenant($tenant, function () use ($schedule, $tenant): void {
                $this->getDispatcher()->dispatch($schedule, $tenant);
            });

            return;
        }

        $this->getDispatcher()->dispatch($schedule, $tenant);
    }

    /**
     * @param array<string, mixed> $schedule Schedule row
     * @return array<string, string>
     */
    private function jobParameters(array $schedule): array
    {
        return [
            'schedule_id' => (string)$schedule['id'],
            'schedule_name' => (string)$schedule['name'],
            'tenant_scope' => (string)$schedule['tenant_scope'],
            'command' => (string)$schedule['command'],
        ];
    }

    /**
     * @param array<string, mixed> $row Schedule row
     * @return array<string, mixed>
     */
    private function normalizeSchedule(array $row): array
    {
        $row['payload'] = $this->normalizeJson($row['payload'] ?? null);
        $row['options'] = $this->normalizeJson($row['options'] ?? null);

        return $row;
    }

    /**
     * @return list<mixed>|array<string, mixed>
     */
    private function normalizeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if ($value === null || $value === '') {
            return [];
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * Normalize database truthy values across PostgreSQL/SQLite test doubles.
     *
     * @param mixed $value Value to check
     * @return bool
     */
    private function isTruthy(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1';
    }

    /**
     * Whether a tenant fan-out schedule should stop after the first target failure.
     *
     * @param array<string, mixed> $schedule Schedule row
     * @return bool
     */
    private function failFast(array $schedule): bool
    {
        $options = (array)($schedule['options'] ?? []);

        return $this->isTruthy($options['fail_fast'] ?? false);
    }

    /**
     * Resolve the command dispatcher.
     *
     * @return \App\Services\Platform\PlatformScheduleDispatcherInterface
     */
    private function getDispatcher(): PlatformScheduleDispatcherInterface
    {
        return $this->dispatcher ?? new AllowlistedPlatformScheduleDispatcher();
    }

    /**
     * Return the current timestamp.
     *
     * @return \Cake\I18n\DateTime
     */
    private function now(): DateTime
    {
        return new DateTime('now');
    }
}
