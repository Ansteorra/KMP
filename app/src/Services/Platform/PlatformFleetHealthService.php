<?php
declare(strict_types=1);

namespace App\Services\Platform;

use Cake\Database\Connection;
use Cake\Log\Log;
use DateTimeImmutable;
use DateTimeInterface;
use Throwable;

/**
 * Builds a privacy-safe operational snapshot of the tenant fleet.
 */
final class PlatformFleetHealthService
{
    /**
     * Constructor.
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(DateTimeInterface|string|null $now = null, int $tenantLimit = 500): array
    {
        $now = $this->dateTime($now);
        $since = $now->modify('-24 hours')->format('Y-m-d H:i:s');
        $tenants = $this->tenantRows(max(1, min(1000, $tenantLimit)));
        try {
            $metrics = $this->metricsByTenant($since);
            $telemetryAvailable = true;
        } catch (Throwable $exception) {
            Log::warning(sprintf('Fleet telemetry query failed: %s', $exception::class));
            $metrics = [];
            $telemetryAvailable = false;
        }
        $backups = $this->latestBackupsByTenant();
        $activeJobs = $this->activeJobsByTenant($now->modify('-15 minutes')->format('Y-m-d H:i:s'));
        $tenantHealth = [];

        foreach ($tenants as $tenant) {
            $tenantId = (string)$tenant['id'];
            $tenantHealth[] = $this->tenantHealth(
                $tenant,
                $metrics[$tenantId] ?? [],
                $backups[$tenantId] ?? null,
                $activeJobs[$tenantId] ?? null,
                $now,
            );
        }
        usort($tenantHealth, fn(array $left, array $right): int => $this->riskWeight($right['risk_level'])
            <=> $this->riskWeight($left['risk_level'])
            ?: strcasecmp((string)$left['display_name'], (string)$right['display_name']));

        $statusCounts = [];
        $summary = [
            'tenant_count' => count($tenantHealth),
            'active_tenants' => 0,
            'attention_tenants' => 0,
            'critical_tenants' => 0,
            'fresh_backups' => 0,
            'requests_24h' => 0,
            'errors_24h' => 0,
            'server_errors_24h' => 0,
            'slow_requests_24h' => 0,
            'duration_total_ms_24h' => 0,
            'max_duration_ms_24h' => 0,
        ];
        foreach ($tenantHealth as $tenant) {
            $status = (string)$tenant['status'];
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
            $summary['active_tenants'] += $status === 'active' ? 1 : 0;
            $summary['attention_tenants'] += in_array($tenant['risk_level'], ['warning', 'critical'], true) ? 1 : 0;
            $summary['critical_tenants'] += $tenant['risk_level'] === 'critical' ? 1 : 0;
            $summary['fresh_backups'] += !empty($tenant['backup_fresh']) ? 1 : 0;
            $summary['requests_24h'] += (int)$tenant['request_count'];
            $summary['errors_24h'] += (int)$tenant['error_count'];
            $summary['server_errors_24h'] += (int)$tenant['server_error_count'];
            $summary['slow_requests_24h'] += (int)$tenant['slow_request_count'];
            $summary['duration_total_ms_24h'] += (int)$tenant['duration_total_ms'];
            $summary['max_duration_ms_24h'] = max(
                $summary['max_duration_ms_24h'],
                (int)$tenant['duration_max_ms'],
            );
        }
        $summary['error_rate_24h'] = $summary['requests_24h'] > 0
            ? round($summary['errors_24h'] / $summary['requests_24h'] * 100, 2)
            : 0.0;
        $summary['average_duration_ms_24h'] = $summary['requests_24h'] > 0
            ? (int)round($summary['duration_total_ms_24h'] / $summary['requests_24h'])
            : 0;
        $summary['backup_coverage_percent'] = $summary['active_tenants'] > 0
            ? round($summary['fresh_backups'] / $summary['active_tenants'] * 100, 1)
            : 100.0;

        return [
            'generated_at' => $now->format('Y-m-d H:i:s'),
            'telemetry_available' => $telemetryAvailable,
            'summary' => $summary,
            'status_counts' => $statusCounts,
            'tenants' => $tenantHealth,
            'operation_issues' => $this->operationIssues($now),
            'schedule_issues' => $this->scheduleIssues($now),
            'platform_backup' => $this->latestPlatformBackup(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tenantRows(int $limit): array
    {
        return $this->connection->execute(
            'SELECT id, slug, display_name, status, region, primary_host, schema_version,
                    created_at, activated_at
               FROM tenants
           ORDER BY display_name ASC, slug ASC
              LIMIT :limit',
            ['limit' => $limit],
            ['limit' => 'integer'],
        )->fetchAll('assoc');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function metricsByTenant(string $since): array
    {
        $rows = $this->connection->execute(
            'SELECT tenant_id,
                    SUM(request_count) AS request_count,
                    SUM(error_count) AS error_count,
                    SUM(server_error_count) AS server_error_count,
                    SUM(slow_request_count) AS slow_request_count,
                    SUM(duration_total_ms) AS duration_total_ms,
                    MAX(duration_max_ms) AS duration_max_ms
               FROM tenant_request_metrics_hourly
              WHERE metric_hour >= :since
           GROUP BY tenant_id',
            ['since' => $since],
        )->fetchAll('assoc');

        return $this->keyBy($rows, 'tenant_id');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function latestBackupsByTenant(): array
    {
        $rows = $this->connection->execute(
            'SELECT b.tenant_id, b.status, b.completed_at, b.retention_until
               FROM tenant_backups b
              WHERE b.id = (
                    SELECT latest.id
                      FROM tenant_backups latest
                     WHERE latest.tenant_id = b.tenant_id
                  ORDER BY latest.created_at DESC, latest.id DESC
                     LIMIT 1
              )',
        )->fetchAll('assoc');

        return $this->keyBy($rows, 'tenant_id');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function activeJobsByTenant(string $staleBefore): array
    {
        $rows = $this->connection->execute(
            'SELECT j.tenant_id, j.id, j.job_type, j.status, j.created_at,
                    CASE WHEN j.created_at <= :staleBefore THEN 1 ELSE 0 END AS is_stale
               FROM platform_jobs j
              WHERE j.tenant_id IS NOT NULL
                AND j.status IN (:queued, :running)
                AND j.id = (
                    SELECT active.id
                      FROM platform_jobs active
                     WHERE active.tenant_id = j.tenant_id
                       AND active.status IN (:queuedAgain, :runningAgain)
                  ORDER BY active.created_at ASC, active.id ASC
                     LIMIT 1
                )',
            [
                'staleBefore' => $staleBefore,
                'queued' => 'queued',
                'running' => 'running',
                'queuedAgain' => 'queued',
                'runningAgain' => 'running',
            ],
        )->fetchAll('assoc');

        return $this->keyBy($rows, 'tenant_id');
    }

    /**
     * @param array<string, mixed> $tenant
     * @param array<string, mixed> $metrics
     * @param array<string, mixed>|null $backup
     * @param array<string, mixed>|null $activeJob
     * @return array<string, mixed>
     */
    private function tenantHealth(
        array $tenant,
        array $metrics,
        ?array $backup,
        ?array $activeJob,
        DateTimeImmutable $now,
    ): array {
        $requests = (int)($metrics['request_count'] ?? 0);
        $errors = (int)($metrics['error_count'] ?? 0);
        $serverErrors = (int)($metrics['server_error_count'] ?? 0);
        $durationTotal = (int)($metrics['duration_total_ms'] ?? 0);
        $averageDuration = $requests > 0 ? (int)round($durationTotal / $requests) : 0;
        $errorRate = $requests > 0 ? round($errors / $requests * 100, 2) : 0.0;
        $backupAgeHours = $this->ageHours($backup['completed_at'] ?? null, $now);
        $backupFresh = (string)($tenant['status'] ?? '') === 'active'
            && (string)($backup['status'] ?? '') === 'completed'
            && $backupAgeHours !== null
            && $backupAgeHours <= 24;
        $riskLevel = 'healthy';
        $attention = [];
        $status = (string)($tenant['status'] ?? '');

        if ($status !== 'active') {
            $riskLevel = in_array($status, ['failed', 'suspended'], true) ? 'critical' : 'inactive';
            if ($status === 'provisioning') {
                $riskLevel = 'warning';
                $attention[] = 'Onboarding is not complete.';
            } elseif ($status !== 'archived') {
                $attention[] = sprintf('Tenant status is %s.', $status ?: 'unknown');
            }
        } else {
            if ($backup === null || (string)($backup['status'] ?? '') === 'expired') {
                $riskLevel = 'critical';
                $attention[] = 'No retained tenant backup is available.';
            } elseif ((string)($backup['status'] ?? '') !== 'completed') {
                $riskLevel = 'critical';
                $attention[] = 'The latest tenant backup did not complete.';
            } elseif ($backupAgeHours === null || $backupAgeHours > 24) {
                $riskLevel = $backupAgeHours !== null && $backupAgeHours > 72 ? 'critical' : 'warning';
                $attention[] = 'The latest tenant backup is older than 24 hours.';
            }
            if ($requests >= 20 && $errorRate >= 5) {
                $riskLevel = $this->higherRisk($riskLevel, $errorRate >= 15 ? 'critical' : 'warning');
                $attention[] = sprintf('Request error rate is %.1f%%.', $errorRate);
            } elseif ($serverErrors > 0) {
                $riskLevel = $this->higherRisk($riskLevel, 'warning');
                $attention[] = sprintf('%d server errors occurred in 24 hours.', $serverErrors);
            }
            if ($requests >= 10 && $averageDuration >= 1000) {
                $riskLevel = $this->higherRisk($riskLevel, 'warning');
                $attention[] = sprintf('Average response time is %d ms.', $averageDuration);
            }
        }
        if ($activeJob !== null && !empty($activeJob['is_stale'])) {
            $riskLevel = $this->higherRisk($riskLevel, 'critical');
            $attention[] = 'A lifecycle operation appears stuck.';
        }

        return $tenant + [
            'request_count' => $requests,
            'error_count' => $errors,
            'server_error_count' => $serverErrors,
            'slow_request_count' => (int)($metrics['slow_request_count'] ?? 0),
            'duration_total_ms' => $durationTotal,
            'duration_max_ms' => (int)($metrics['duration_max_ms'] ?? 0),
            'average_duration_ms' => $averageDuration,
            'error_rate' => $errorRate,
            'backup_status' => $backup['status'] ?? null,
            'backup_completed_at' => $backup['completed_at'] ?? null,
            'backup_age_hours' => $backupAgeHours,
            'backup_fresh' => $backupFresh,
            'active_job' => $activeJob,
            'risk_level' => $riskLevel,
            'attention' => $attention,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function operationIssues(DateTimeImmutable $now): array
    {
        return $this->connection->execute(
            'SELECT j.id, j.job_type, j.status, j.created_at, j.started_at, j.finished_at,
                    CASE WHEN j.last_error IS NULL OR j.last_error = :empty THEN 0 ELSE 1 END AS has_error,
                    t.slug AS tenant_slug
               FROM platform_jobs j
          LEFT JOIN tenants t ON t.id = j.tenant_id
              WHERE j.status = :failed
                 OR (
                    j.status IN (:queued, :running)
                    AND COALESCE(j.started_at, j.created_at) <= :staleBefore
                 )
           ORDER BY j.created_at DESC
              LIMIT 20',
            [
                'empty' => '',
                'failed' => 'failed',
                'queued' => 'queued',
                'running' => 'running',
                'staleBefore' => $now->modify('-15 minutes')->format('Y-m-d H:i:s'),
            ],
        )->fetchAll('assoc');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function scheduleIssues(DateTimeImmutable $now): array
    {
        return $this->connection->execute(
            'SELECT name, status, last_run_at, next_run_at, last_success_at, last_failure_at,
                    CASE WHEN last_error IS NULL OR last_error = :empty THEN 0 ELSE 1 END AS has_error
               FROM platform_schedules
              WHERE enabled = :enabled
                AND (
                    status = :failed
                    OR (last_failure_at IS NOT NULL AND (
                        last_success_at IS NULL OR last_failure_at > last_success_at
                    ))
                    OR (next_run_at IS NOT NULL AND next_run_at < :staleBefore)
                )
           ORDER BY COALESCE(last_failure_at, next_run_at) DESC
              LIMIT 20',
            [
                'empty' => '',
                'enabled' => true,
                'failed' => 'failed',
                'staleBefore' => $now->modify('-10 minutes')->format('Y-m-d H:i:s'),
            ],
        )->fetchAll('assoc');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function latestPlatformBackup(): ?array
    {
        $row = $this->connection->execute(
            'SELECT id, status, completed_at, retention_until
               FROM platform_database_backups
           ORDER BY created_at DESC
              LIMIT 1',
        )->fetch('assoc');

        return is_array($row) ? $row : null;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, array<string, mixed>>
     */
    private function keyBy(array $rows, string $key): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(string)$row[$key]] = $row;
        }

        return $indexed;
    }

    /**
     * Return age in whole hours for a database timestamp.
     */
    private function ageHours(mixed $value, DateTimeImmutable $now): ?int
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return max(0, (int)floor(($now->getTimestamp() - $timestamp) / 3600));
    }

    /**
     * Return the more severe risk level.
     */
    private function higherRisk(string $current, string $candidate): string
    {
        return $this->riskWeight($candidate) > $this->riskWeight($current) ? $candidate : $current;
    }

    /**
     * Map risk labels to sort weight.
     */
    private function riskWeight(string $risk): int
    {
        return match ($risk) {
            'critical' => 4,
            'warning' => 3,
            'inactive' => 1,
            default => 2,
        };
    }

    /**
     * Normalize an optional snapshot time.
     */
    private function dateTime(DateTimeInterface|string|null $value): DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return new DateTimeImmutable($value->format(DateTimeInterface::ATOM));
        }
        if (is_string($value) && trim($value) !== '') {
            return new DateTimeImmutable($value);
        }

        return new DateTimeImmutable('now');
    }
}
