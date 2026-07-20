<?php
declare(strict_types=1);

namespace App\Services\Platform;

use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Cake\I18n\DateTime;

/**
 * Builds diagnostics-safe alert findings from platform schedule/job metadata.
 */
class PlatformJobAlertService
{
    public const TYPE_STALE_RUNNING_JOB = 'stale_running_job';
    public const TYPE_REPEATED_FAILURES = 'repeated_failures';
    public const TYPE_MISSING_SCHEDULE_SUCCESS = 'missing_schedule_success';

    /**
     * Constructor.
     *
     * @param int $staleRunningMinutes Minutes before a running job is stale
     * @param int $missingSuccessMinutes Default schedule success freshness threshold
     * @param int $failureThreshold Failed jobs needed to alert
     * @param int $failureWindowMinutes Failed job lookback window
     * @param \Cake\I18n\DateTime|null $now Clock override for tests
     */
    public function __construct(
        private readonly int $staleRunningMinutes = 60,
        private readonly int $missingSuccessMinutes = 1440,
        private readonly int $failureThreshold = 3,
        private readonly int $failureWindowMinutes = 60,
        private readonly ?DateTime $now = null,
    ) {
    }

    /**
     * Check platform_jobs and platform_schedules for monitorable alert conditions.
     *
     * @return array{healthy: bool, checked_at: string, alerts: list<array<string, mixed>>}
     */
    public function check(): array
    {
        $alerts = [
            ...$this->staleRunningJobAlerts(),
            ...$this->repeatedFailureAlerts(),
            ...$this->missingScheduleSuccessAlerts(),
        ];

        return [
            'healthy' => $alerts === [],
            'checked_at' => $this->clock()->format(DATE_ATOM),
            'alerts' => $alerts,
        ];
    }

    /**
     * Redact secrets and PII from alert output.
     *
     * @param string|null $message Raw diagnostic text
     * @return string|null Safe diagnostic text
     */
    public static function scrub(?string $message): ?string
    {
        if ($message === null || $message === '') {
            return $message;
        }

        $message = PlatformScheduleRunner::scrubError($message);
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
     * @return list<array<string, mixed>>
     */
    private function staleRunningJobAlerts(): array
    {
        $threshold = max(1, $this->staleRunningMinutes);
        $cutoff = $this->clock()->modify(sprintf('-%d minutes', $threshold));
        $rows = $this->platform()->execute(
            'SELECT id, tenant_id, job_type, status, started_at, created_at, last_error
               FROM platform_jobs
              WHERE status = :status
                AND COALESCE(started_at, created_at) <= :cutoff
              ORDER BY COALESCE(started_at, created_at) ASC',
            ['status' => 'running', 'cutoff' => $cutoff->format('Y-m-d H:i:s')],
        )->fetchAll('assoc');

        return array_map(function (array $row) use ($threshold): array {
            $startedAt = $this->timestamp($row['started_at'] ?? $row['created_at'] ?? null);

            return [
                'type' => self::TYPE_STALE_RUNNING_JOB,
                'severity' => 'critical',
                'job_id' => (string)$row['id'],
                'tenant_id' => $row['tenant_id'] === null ? null : (string)$row['tenant_id'],
                'job_type' => (string)$row['job_type'],
                'started_at' => $startedAt?->format(DATE_ATOM),
                'age_minutes' => $startedAt === null ? null : $this->ageMinutes($startedAt),
                'threshold_minutes' => $threshold,
                'last_error' => self::scrub($row['last_error'] === null ? null : (string)$row['last_error']),
            ];
        }, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function repeatedFailureAlerts(): array
    {
        $threshold = max(1, $this->failureThreshold);
        $window = max(1, $this->failureWindowMinutes);
        $cutoff = $this->clock()->modify(sprintf('-%d minutes', $window));
        $rows = $this->platform()->execute(
            sprintf(
                'SELECT tenant_id,
                        job_type,
                        COUNT(*) AS failure_count,
                        MAX(COALESCE(finished_at, modified_at, created_at)) AS last_failed_at
               FROM platform_jobs
              WHERE status = :status
                AND COALESCE(finished_at, modified_at, created_at) >= :cutoff
              GROUP BY tenant_id, job_type
             HAVING COUNT(*) >= %d
              ORDER BY failure_count DESC, last_failed_at DESC',
                $threshold,
            ),
            [
                'status' => 'failed',
                'cutoff' => $cutoff->format('Y-m-d H:i:s'),
            ],
        )->fetchAll('assoc');

        return array_map(function (array $row) use ($threshold, $window): array {
            $lastError = $this->latestFailureError($row['tenant_id'], (string)$row['job_type']);

            return [
                'type' => self::TYPE_REPEATED_FAILURES,
                'severity' => 'warning',
                'tenant_id' => $row['tenant_id'] === null ? null : (string)$row['tenant_id'],
                'job_type' => (string)$row['job_type'],
                'failure_count' => (int)$row['failure_count'],
                'threshold_count' => $threshold,
                'window_minutes' => $window,
                'last_failed_at' => $this->timestamp($row['last_failed_at'] ?? null)?->format(DATE_ATOM),
                'last_error' => self::scrub($lastError),
            ];
        }, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function missingScheduleSuccessAlerts(): array
    {
        $rows = $this->platform()->execute(
            'SELECT id,
                    name,
                    tenant_id,
                    tenant_scope,
                    options,
                    last_success_at,
                    last_run_at,
                    last_failure_at,
                    last_error
               FROM platform_schedules
              WHERE enabled = :enabled
              ORDER BY name ASC',
            ['enabled' => 1],
        )->fetchAll('assoc');

        $alerts = [];
        foreach ($rows as $row) {
            $threshold = $this->scheduleSuccessThresholdMinutes($row);
            if ($threshold <= 0) {
                continue;
            }
            $lastSuccess = $this->timestamp($row['last_success_at'] ?? null);
            if ($lastSuccess !== null && $this->ageMinutes($lastSuccess) <= $threshold) {
                continue;
            }
            $alerts[] = [
                'type' => self::TYPE_MISSING_SCHEDULE_SUCCESS,
                'severity' => 'warning',
                'schedule_id' => (string)$row['id'],
                'schedule_name' => (string)$row['name'],
                'tenant_id' => $row['tenant_id'] === null ? null : (string)$row['tenant_id'],
                'tenant_scope' => (string)$row['tenant_scope'],
                'last_success_at' => $lastSuccess?->format(DATE_ATOM),
                'age_minutes' => $lastSuccess === null ? null : $this->ageMinutes($lastSuccess),
                'threshold_minutes' => $threshold,
                'last_run_at' => $this->timestamp($row['last_run_at'] ?? null)?->format(DATE_ATOM),
                'last_failure_at' => $this->timestamp($row['last_failure_at'] ?? null)?->format(DATE_ATOM),
                'last_error' => self::scrub($row['last_error'] === null ? null : (string)$row['last_error']),
            ];
        }

        return $alerts;
    }

    /**
     * Return the most recent failure error for a failed job group.
     *
     * @param mixed $tenantId Tenant identifier, or null for platform jobs
     * @param string $jobType Platform job type
     * @return string|null Last failure error
     */
    private function latestFailureError(mixed $tenantId, string $jobType): ?string
    {
        $conditions = 'tenant_id IS NULL';
        $params = ['jobType' => $jobType, 'status' => 'failed'];
        if ($tenantId !== null) {
            $conditions = 'tenant_id = :tenantId';
            $params['tenantId'] = $tenantId;
        }
        $row = $this->platform()->execute(
            sprintf(
                'SELECT last_error FROM platform_jobs
                  WHERE status = :status AND job_type = :jobType AND %s
                  ORDER BY COALESCE(finished_at, modified_at, created_at) DESC
                  LIMIT 1',
                $conditions,
            ),
            $params,
        )->fetch('assoc');

        return is_array($row) && $row['last_error'] !== null ? (string)$row['last_error'] : null;
    }

    /**
     * @param array<string, mixed> $schedule Schedule row
     * @return int Threshold in minutes
     */
    private function scheduleSuccessThresholdMinutes(array $schedule): int
    {
        $options = $this->decodeOptions($schedule['options'] ?? null);
        foreach (['max_age_critical_minutes', 'max_age_warning_minutes', 'missing_success_minutes'] as $key) {
            if (isset($options[$key]) && is_numeric($options[$key])) {
                return (int)$options[$key];
            }
        }
        foreach (['max_age_critical', 'max_age_warning'] as $key) {
            if (isset($options[$key]) && is_numeric($options[$key])) {
                return (int)ceil(((int)$options[$key]) / 60);
            }
        }

        return max(0, $this->missingSuccessMinutes);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeOptions(mixed $options): array
    {
        if (is_array($options)) {
            return $options;
        }
        if (!is_string($options) || $options === '') {
            return [];
        }
        $decoded = json_decode($options, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Convert a database timestamp into a DateTime instance.
     *
     * @param mixed $value Raw timestamp value
     * @return \Cake\I18n\DateTime|null Parsed timestamp
     */
    private function timestamp(mixed $value): ?DateTime
    {
        if ($value instanceof DateTime) {
            return $value;
        }
        if ($value === null || $value === '') {
            return null;
        }

        return new DateTime((string)$value);
    }

    /**
     * Return the age of a timestamp in whole minutes.
     *
     * @param \Cake\I18n\DateTime $timestamp Timestamp to compare
     * @return int Age in minutes
     */
    private function ageMinutes(DateTime $timestamp): int
    {
        return (int)floor(max(0, $this->clock()->getTimestamp() - $timestamp->getTimestamp()) / 60);
    }

    /**
     * Return the current time.
     *
     * @return \Cake\I18n\DateTime Current time
     */
    private function clock(): DateTime
    {
        return $this->now ?? new DateTime('now');
    }

    /**
     * Return the platform metadata connection.
     *
     * @return \Cake\Database\Connection Platform connection
     */
    private function platform(): Connection
    {
        return ConnectionManager::get('platform');
    }
}
