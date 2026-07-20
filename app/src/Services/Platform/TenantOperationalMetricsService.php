<?php
declare(strict_types=1);

namespace App\Services\Platform;

use Cake\Database\Connection;
use Cake\Database\Driver\Postgres;
use Cake\I18n\DateTime;
use Cake\Utility\Text;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Stores bounded, privacy-safe hourly tenant request aggregates.
 */
final class TenantOperationalMetricsService
{
    public const DEFAULT_RETENTION_DAYS = 90;
    public const DEFAULT_SLOW_REQUEST_MS = 1000;

    /**
     * Constructor.
     */
    public function __construct(
        private readonly Connection $platform,
        private readonly int $slowRequestMs = self::DEFAULT_SLOW_REQUEST_MS,
    ) {
        if ($this->slowRequestMs < 1) {
            throw new InvalidArgumentException('Slow request threshold must be positive.');
        }
    }

    /**
     * Add a request to its privacy-safe hourly aggregate.
     *
     * @param \DateTimeInterface|string|null $recordedAt Request timestamp override
     */
    public function record(
        string $tenantId,
        string $routeName,
        int $statusCode,
        int $durationMs,
        DateTimeInterface|string|null $recordedAt = null,
    ): void {
        $routeName = $this->normalizeRouteName($routeName);
        $statusCode = max(100, min(599, $statusCode));
        $durationMs = max(0, min(3_600_000, $durationMs));
        $metricHour = $this->metricHour($recordedAt);
        $isError = $statusCode >= 400 ? 1 : 0;
        $isServerError = $statusCode >= 500 ? 1 : 0;
        $isSlow = $durationMs >= $this->slowRequestMs ? 1 : 0;
        $now = DateTime::now('UTC')->format('Y-m-d H:i:s');

        $maximumFunction = $this->platform->getDriver() instanceof Postgres ? 'GREATEST' : 'MAX';
        $this->platform->execute(
            sprintf(
                'INSERT INTO tenant_request_metrics_hourly (
                id, tenant_id, metric_hour, route_name, request_count, error_count,
                server_error_count, slow_request_count, duration_total_ms, duration_max_ms,
                created_at, modified_at
             ) VALUES (
                :id, :tenantId, :metricHour, :routeName, 1, :isError,
                :isServerError, :isSlow, :durationTotalMs, :durationMaxMs, :createdAt, :modifiedAt
             )
             ON CONFLICT (tenant_id, metric_hour, route_name)
             DO UPDATE SET
                request_count = tenant_request_metrics_hourly.request_count + 1,
                error_count = tenant_request_metrics_hourly.error_count + EXCLUDED.error_count,
                server_error_count = tenant_request_metrics_hourly.server_error_count
                    + EXCLUDED.server_error_count,
                slow_request_count = tenant_request_metrics_hourly.slow_request_count
                    + EXCLUDED.slow_request_count,
                duration_total_ms = tenant_request_metrics_hourly.duration_total_ms
                    + EXCLUDED.duration_total_ms,
                duration_max_ms = %s(
                    tenant_request_metrics_hourly.duration_max_ms,
                    EXCLUDED.duration_max_ms
                ),
                modified_at = EXCLUDED.modified_at',
                $maximumFunction,
            ),
            [
                'id' => Text::uuid(),
                'tenantId' => $tenantId,
                'metricHour' => $metricHour,
                'routeName' => $routeName,
                'isError' => $isError,
                'isServerError' => $isServerError,
                'isSlow' => $isSlow,
                'durationTotalMs' => $durationMs,
                'durationMaxMs' => $durationMs,
                'createdAt' => $now,
                'modifiedAt' => $now,
            ],
        );
    }

    /**
     * Remove aggregates older than the retention window.
     */
    public function prune(int $retentionDays = self::DEFAULT_RETENTION_DAYS): int
    {
        if ($retentionDays < 1 || $retentionDays > 730) {
            throw new InvalidArgumentException('Tenant metrics retention must be between 1 and 730 days.');
        }
        $cutoff = DateTime::now('UTC')
            ->subDays($retentionDays)
            ->format('Y-m-d H:i:s');

        return $this->platform->execute(
            'DELETE FROM tenant_request_metrics_hourly WHERE metric_hour < :cutoff',
            ['cutoff' => $cutoff],
        )->rowCount();
    }

    /**
     * Reduce a route to a bounded, non-parameterized identifier.
     */
    private function normalizeRouteName(string $routeName): string
    {
        $routeName = trim($routeName);
        if ($routeName === '' || !preg_match('/\A[a-zA-Z0-9_.:\/-]+\z/', $routeName)) {
            return 'unrouted';
        }

        return mb_substr($routeName, 0, 160);
    }

    /**
     * Normalize a timestamp to its UTC hour bucket.
     */
    private function metricHour(DateTimeInterface|string|null $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:00:00');
        }
        if (is_string($value) && trim($value) !== '') {
            return (new DateTime($value))->format('Y-m-d H:00:00');
        }

        return DateTime::now('UTC')->format('Y-m-d H:00:00');
    }
}
