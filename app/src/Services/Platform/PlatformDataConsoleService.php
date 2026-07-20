<?php
declare(strict_types=1);

namespace App\Services\Platform;

use Cake\Database\Connection;
use InvalidArgumentException;

/**
 * Executes the platform admin data console's allowlisted read-only queries.
 */
final class PlatformDataConsoleService
{
    private const DEFAULT_LIMIT = 25;
    private const MAX_LIMIT = 100;

    /**
     * @var array<string, array{label: string, description: string, sql: string, columns: list<string>}>
     */
    private const QUERIES = [
        'tenants' => [
            'label' => 'Tenants',
            'description' => 'Tenant registry metadata without database credentials or feature configuration.',
            'columns' => [
                'id',
                'slug',
                'display_name',
                'status',
                'region',
                'primary_host',
                'schema_version',
                'created_at',
                'activated_at',
                'suspended_at',
                'archived_at',
            ],
            'sql' => 'SELECT id, slug, display_name, status, region, primary_host, schema_version,
                            created_at, activated_at, suspended_at, archived_at
                       FROM tenants
                   ORDER BY display_name ASC, slug ASC',
        ],
        'jobs' => [
            'label' => 'Jobs',
            'description' => 'Platform job status with sensitive parameters and error text reduced to indicators.',
            'columns' => [
                'id',
                'job_type',
                'status',
                'tenant_slug',
                'created_at',
                'started_at',
                'finished_at',
                'has_error',
            ],
            'sql' => 'SELECT j.id, j.job_type, j.status, t.slug AS tenant_slug, j.created_at,
                            j.started_at, j.finished_at,
                            CASE WHEN j.last_error IS NULL OR j.last_error = :empty THEN 0 ELSE 1 END AS has_error
                       FROM platform_jobs j
                  LEFT JOIN tenants t ON t.id = j.tenant_id
                   ORDER BY j.created_at DESC, j.id ASC',
        ],
        'schedules' => [
            'label' => 'Schedules',
            'description' => 'Schedule cadence and status without raw failure details.',
            'columns' => [
                'name',
                'cron_expression',
                'command',
                'enabled',
                'tenant_scope',
                'tenant_slug',
                'status',
                'last_run_at',
                'next_run_at',
                'last_success_at',
                'last_failure_at',
                'has_error',
            ],
            'sql' => 'SELECT s.name, s.cron_expression, s.command, s.enabled, s.tenant_scope,
                            t.slug AS tenant_slug, s.status, s.last_run_at, s.next_run_at,
                            s.last_success_at, s.last_failure_at,
                            CASE WHEN s.last_error IS NULL OR s.last_error = :empty THEN 0 ELSE 1 END AS has_error
                       FROM platform_schedules s
                  LEFT JOIN tenants t ON t.id = s.tenant_id
                   ORDER BY s.enabled DESC, s.next_run_at ASC, s.name ASC',
        ],
        'backups' => [
            'label' => 'Backups',
            'description' => 'Tenant and platform backup status without object URIs or encryption material.',
            'columns' => [
                'backup_scope',
                'id',
                'tenant_slug',
                'resource_name',
                'backup_type',
                'status',
                'object_size_bytes',
                'created_at',
                'completed_at',
                'retention_until',
            ],
            'sql' => "SELECT 'tenant' AS backup_scope, b.id, t.slug AS tenant_slug, NULL AS resource_name, "
                . "b.backup_type, b.status, b.object_size_bytes, b.created_at, b.completed_at, b.retention_until
                         FROM tenant_backups b
                         JOIN tenants t ON t.id = b.tenant_id
                        UNION ALL
                       SELECT 'platform' AS backup_scope, id, NULL AS tenant_slug, database_name AS resource_name,
                              backup_type, status, object_size_bytes, created_at, completed_at, retention_until
                         FROM platform_database_backups
                     ORDER BY created_at DESC, id ASC",
        ],
        'audit_summaries' => [
            'label' => 'Audit summaries',
            'description' => 'Grouped platform audit activity for operations review.',
            'columns' => [
                'action',
                'subject_type',
                'event_count',
                'latest_at',
            ],
            'sql' => 'SELECT action, subject_type, COUNT(*) AS event_count, MAX(created_at) AS latest_at
                       FROM audit_events
                   GROUP BY action, subject_type
                   ORDER BY latest_at DESC, action ASC',
        ],
    ];

    /**
     * Constructor.
     *
     * @param \Cake\Database\Connection $connection Platform metadata connection
     */
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * Return query metadata for UI selection.
     *
     * @return array<string, array{label: string, description: string}>
     */
    public function queryList(): array
    {
        $queries = [];
        foreach (self::QUERIES as $name => $query) {
            $queries[$name] = [
                'label' => $query['label'],
                'description' => $query['description'],
            ];
        }

        return $queries;
    }

    /**
     * @return array{query: string, label: string, description: string, columns: list<string>, rows: list<array<string, mixed>>, page: int, limit: int, hasNext: bool}
     */
    public function run(string $queryName, int $page = 1, int $limit = self::DEFAULT_LIMIT): array
    {
        if (!isset(self::QUERIES[$queryName])) {
            throw new InvalidArgumentException('Data console query is not allowlisted.');
        }

        $page = max(1, $page);
        $limit = min(max(1, $limit), self::MAX_LIMIT);
        $offset = ($page - 1) * $limit;
        $query = self::QUERIES[$queryName];
        $params = [
            'limit' => $limit + 1,
            'offset' => $offset,
        ];
        if (str_contains($query['sql'], ':empty')) {
            $params['empty'] = '';
        }
        $types = [
            'limit' => 'integer',
            'offset' => 'integer',
        ];
        $rows = $this->connection
            ->execute($query['sql'] . ' LIMIT :limit OFFSET :offset', $params, $types)
            ->fetchAll('assoc');
        $hasNext = count($rows) > $limit;
        $rows = array_slice($rows, 0, $limit);

        return [
            'query' => $queryName,
            'label' => $query['label'],
            'description' => $query['description'],
            'columns' => $query['columns'],
            'rows' => $this->scrubRows($rows),
            'page' => $page,
            'limit' => $limit,
            'hasNext' => $hasNext,
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function scrubRows(array $rows): array
    {
        return array_map(function (array $row): array {
            $scrubbed = [];
            foreach ($row as $key => $value) {
                $scrubbed[$key] = $this->scrubValue((string)$key, $value);
            }

            return $scrubbed;
        }, $rows);
    }

    /**
     * Scrub one value by key name and secret-like scalar content.
     */
    private function scrubValue(string $key, mixed $value): mixed
    {
        if ($this->isSensitiveKey($key)) {
            return '[redacted]';
        }

        if (is_array($value)) {
            $scrubbed = [];
            foreach ($value as $childKey => $childValue) {
                $scrubbed[$childKey] = $this->scrubValue((string)$childKey, $childValue);
            }

            return $scrubbed;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return json_encode($this->scrubValue($key, $decoded), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            return $this->scrubString($value);
        }

        return $value;
    }

    /**
     * Return whether a column or JSON field name should never be displayed.
     */
    private function isSensitiveKey(string $key): bool
    {
        return (bool)preg_match(
            '/(?:password|passwd|secret|token|credential|api[_-]?key|private[_-]?key|wrapped[_-]?dek)/i',
            $key,
        );
    }

    /**
     * Redact scalar strings that look like they contain secrets.
     */
    private function scrubString(string $value): string
    {
        if (preg_match('/(?:password|passwd|secret|token|credential|bearer\s+|wrapped-dek)/i', $value)) {
            return '[redacted]';
        }

        return $value;
    }
}
