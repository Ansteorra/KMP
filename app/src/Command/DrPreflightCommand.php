<?php
declare(strict_types=1);

namespace App\Command;

use App\Services\Platform\PlatformHealthService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

/**
 * Non-destructive disaster-recovery prerequisite report for operators.
 */
class DrPreflightCommand extends Command
{
    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'dr_preflight';
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->setDescription('Print DR/failover preflight health and backup freshness checks without changing data.')
            ->addOption('freshness-hours', [
                'help' => 'Maximum acceptable age for completed platform and tenant backups.',
                'default' => '24',
            ])
            ->addOption('tenant', [
                'help' => 'Optional tenant slug to include tenant-specific backup details.',
            ])
            ->addOption('json', [
                'help' => 'Emit diagnostics-safe JSON.',
                'boolean' => true,
            ]);
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        try {
            $freshnessHours = $this->freshnessHours((string)$args->getOption('freshness-hours'));
            $tenantSlug = $args->getOption('tenant');
            if ($tenantSlug !== null) {
                $tenantSlug = $this->validatedTenantSlug((string)$tenantSlug);
            }

            $report = $this->buildReport($freshnessHours, $tenantSlug);
            if ($args->getOption('json')) {
                $io->out((string)json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } else {
                $this->printHumanReport($io, $report);
            }

            return $report['pass'] ? self::CODE_SUCCESS : self::CODE_ERROR;
        } catch (RuntimeException $exception) {
            $io->err($exception->getMessage());

            return self::CODE_ERROR;
        }
    }

    /**
     * Build a diagnostics-safe DR preflight report.
     *
     * @param int $freshnessHours Backup freshness threshold
     * @param string|null $tenantSlug Optional tenant slug
     * @return array<string, mixed>
     */
    private function buildReport(int $freshnessHours, ?string $tenantSlug): array
    {
        $health = new PlatformHealthService(
            'platform',
            (int)Configure::read('Platform.health.retryAttempts', 0),
            (int)Configure::read('Platform.health.retryDelayMs', 0),
        );
        $healthStatus = $health->check();
        $report = [
            'pass' => false,
            'checked_at' => (new DateTimeImmutable())->format(DATE_ATOM),
            'freshness_hours' => $freshnessHours,
            'platform_health' => $healthStatus->toSafeArray(),
            'platform_backup' => ['status' => 'not_checked'],
            'tenant_backups' => ['status' => 'not_checked'],
            'jobs' => ['status' => 'not_checked'],
            'worm_audit' => $this->wormAuditConfig(),
        ];

        if (!$healthStatus->isHealthy()) {
            return $report;
        }

        $cutoff = (new DateTimeImmutable(sprintf('-%d hours', $freshnessHours)))->format('Y-m-d H:i:s');
        try {
            $connection = ConnectionManager::get('platform');
            if (!$connection instanceof Connection) {
                throw new RuntimeException('Platform connection does not support SQL diagnostics.');
            }
            $report['platform_backup'] = $this->platformBackupStatus($connection, $cutoff);
            $report['tenant_backups'] = $this->tenantBackupStatus($connection, $cutoff, $tenantSlug);
            $report['jobs'] = $this->jobStatus($connection);
        } catch (Throwable $exception) {
            $report['metadata_error'] = $exception::class;

            return $report;
        }

        $platformBackup = (array)$report['platform_backup'];
        $tenantBackups = (array)$report['tenant_backups'];
        $jobs = (array)$report['jobs'];
        $report['pass'] = ($platformBackup['fresh'] ?? false)
            && ($tenantBackups['fresh'] ?? false)
            && (($jobs['blocking_count'] ?? 1) === 0);

        return $report;
    }

    /**
     * @return array<string, mixed>
     */
    private function platformBackupStatus(Connection $connection, string $cutoff): array
    {
        $row = $connection->execute(
            'SELECT id, object_uri, completed_at, created_at FROM platform_database_backups ' .
            'WHERE status = :status ORDER BY completed_at DESC, created_at DESC LIMIT 1',
            ['status' => 'completed'],
        )->fetch('assoc');
        $latestAt = is_array($row) ? (string)($row['completed_at'] ?? $row['created_at'] ?? '') : null;

        return [
            'status' => is_array($row) ? 'found' : 'missing',
            'fresh' => $latestAt !== null && $latestAt >= $cutoff,
            'latest_completed_at' => $latestAt,
            'latest_backup_id' => is_array($row) ? (string)$row['id'] : null,
            'latest_object_uri' => is_array($row) ? (string)$row['object_uri'] : null,
            'cutoff' => $cutoff,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tenantBackupStatus(Connection $connection, string $cutoff, ?string $tenantSlug): array
    {
        $activeTenants = (int)$connection->execute(
            'SELECT COUNT(*) FROM tenants WHERE status = :status',
            ['status' => 'active'],
        )->fetchColumn(0);
        $freshTenants = (int)$connection->execute(
            'SELECT COUNT(DISTINCT tenant_id) FROM tenant_backups ' .
            'WHERE status = :status AND completed_at >= :cutoff',
            ['status' => 'completed', 'cutoff' => $cutoff],
        )->fetchColumn(0);
        $result = [
            'status' => 'checked',
            'fresh' => $freshTenants >= $activeTenants,
            'active_tenant_count' => $activeTenants,
            'tenants_with_fresh_completed_backup_count' => $freshTenants,
            'cutoff' => $cutoff,
        ];

        if ($tenantSlug === null) {
            return $result;
        }

        $tenant = $connection->execute(
            'SELECT id, slug, status, primary_host FROM tenants WHERE slug = :slug LIMIT 1',
            ['slug' => $tenantSlug],
        )->fetch('assoc');
        if (!is_array($tenant)) {
            $result['tenant'] = ['slug' => $tenantSlug, 'status' => 'missing', 'fresh' => false];
            $result['fresh'] = false;

            return $result;
        }

        $backup = $connection->execute(
            'SELECT id, object_uri, completed_at, created_at FROM tenant_backups ' .
            'WHERE tenant_id = :tenantId AND status = :status ' .
            'ORDER BY completed_at DESC, created_at DESC LIMIT 1',
            ['tenantId' => $tenant['id'], 'status' => 'completed'],
        )->fetch('assoc');
        $latestAt = is_array($backup) ? (string)($backup['completed_at'] ?? $backup['created_at'] ?? '') : null;
        $tenantFresh = $latestAt !== null && $latestAt >= $cutoff;
        $result['tenant'] = [
            'slug' => (string)$tenant['slug'],
            'status' => (string)$tenant['status'],
            'primary_host' => (string)($tenant['primary_host'] ?? ''),
            'fresh' => $tenantFresh,
            'latest_completed_at' => $latestAt,
            'latest_backup_id' => is_array($backup) ? (string)$backup['id'] : null,
            'latest_object_uri' => is_array($backup) ? (string)$backup['object_uri'] : null,
        ];
        $result['fresh'] = $result['fresh'] && $tenantFresh;

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function jobStatus(Connection $connection): array
    {
        $blockingCount = (int)$connection->execute(
            "SELECT COUNT(*) FROM platform_jobs WHERE status IN ('queued', 'running')",
        )->fetchColumn(0);
        $failedCount = (int)$connection->execute(
            "SELECT COUNT(*) FROM platform_jobs WHERE status = 'failed'",
        )->fetchColumn(0);

        return [
            'status' => 'checked',
            'blocking_count' => $blockingCount,
            'failed_count' => $failedCount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function wormAuditConfig(): array
    {
        $configured = (array)Configure::read('PlatformAudit.worm', []);
        $envSink = getenv('PLATFORM_AUDIT_WORM_SINK');
        $envFailClosed = getenv('PLATFORM_AUDIT_WORM_FAIL_CLOSED');
        $sink = (string)($configured['sink'] ?? ($envSink === false ? 'disabled' : $envSink));
        $failClosed = $configured['failClosed'] ?? ($envFailClosed === false ? false : $envFailClosed);

        return [
            'sink' => $sink,
            'fail_closed' => filter_var($failClosed, FILTER_VALIDATE_BOOLEAN),
            'operator_note' => 'Verify immutable retention/legal hold directly in the storage control plane.',
        ];
    }

    /**
     * Parse the positive backup freshness threshold.
     *
     * @param string $value Raw option value
     * @return int
     */
    private function freshnessHours(string $value): int
    {
        if (!preg_match('/^[1-9][0-9]{0,3}$/', $value)) {
            throw new RuntimeException('Invalid --freshness-hours. Use a positive integer from 1 to 9999.');
        }

        return (int)$value;
    }

    /**
     * Validate tenant slug before using it in lookup queries.
     *
     * @param string $slug Tenant slug
     * @return string
     */
    private function validatedTenantSlug(string $slug): string
    {
        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,78}[a-z0-9])?$/', $slug)) {
            throw new RuntimeException(
                'Invalid --tenant. Use 1-80 lowercase letters, numbers, and hyphens; no edge hyphens.',
            );
        }

        return $slug;
    }

    /**
     * @param array<string, mixed> $report
     */
    private function printHumanReport(ConsoleIo $io, array $report): void
    {
        $io->out(sprintf('DR preflight: %s', $report['pass'] ? 'PASS' : 'FAIL'));
        $health = (array)$report['platform_health'];
        $io->out(sprintf('Platform metadata: %s (%s)', $health['state'], $health['message']));

        $platformBackup = (array)$report['platform_backup'];
        $platformBackupFresh = $platformBackup['fresh'] ?? false;
        $io->out(sprintf(
            'Platform backup: %s latest=%s cutoff=%s',
            $platformBackupFresh ? 'fresh' : 'stale/missing',
            (string)($platformBackup['latest_completed_at'] ?? 'n/a'),
            (string)($platformBackup['cutoff'] ?? 'n/a'),
        ));

        $tenantBackups = (array)$report['tenant_backups'];
        $tenantBackupsFresh = $tenantBackups['fresh'] ?? false;
        $io->out(sprintf(
            'Tenant backups: %s fresh=%s/%s cutoff=%s',
            $tenantBackupsFresh ? 'fresh' : 'stale/missing',
            (string)($tenantBackups['tenants_with_fresh_completed_backup_count'] ?? 'n/a'),
            (string)($tenantBackups['active_tenant_count'] ?? 'n/a'),
            (string)($tenantBackups['cutoff'] ?? 'n/a'),
        ));
        if (isset($tenantBackups['tenant']) && is_array($tenantBackups['tenant'])) {
            $tenant = $tenantBackups['tenant'];
            $tenantFresh = $tenant['fresh'] ?? false;
            $io->out(sprintf(
                'Tenant %s: %s latest=%s host=%s',
                (string)$tenant['slug'],
                $tenantFresh ? 'fresh' : 'stale/missing',
                (string)($tenant['latest_completed_at'] ?? 'n/a'),
                (string)($tenant['primary_host'] ?? 'n/a'),
            ));
        }

        $jobs = (array)$report['jobs'];
        $io->out(sprintf(
            'Platform jobs: blocking=%s failed=%s',
            (string)($jobs['blocking_count'] ?? 'n/a'),
            (string)($jobs['failed_count'] ?? 'n/a'),
        ));
        $worm = (array)$report['worm_audit'];
        $wormFailClosed = $worm['fail_closed'] ?? false;
        $io->out(sprintf(
            'WORM audit: sink=%s fail_closed=%s; %s',
            (string)$worm['sink'],
            $wormFailClosed ? 'true' : 'false',
            (string)$worm['operator_note'],
        ));
    }
}
