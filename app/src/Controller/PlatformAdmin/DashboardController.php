<?php
declare(strict_types=1);

namespace App\Controller\PlatformAdmin;

use App\Services\Platform\PlatformHealthService;
use App\Services\Platform\ReleaseCompatibilityChecker;
use App\Services\Platform\ReleaseManifest;
use Cake\Core\Configure;
use Throwable;

class DashboardController extends PlatformAdminAppController
{
    /**
     * Display a read-only platform operations dashboard.
     *
     * @return void
     */
    public function index(): void
    {
        $health = (new PlatformHealthService(
            'platform',
            (int)Configure::read('Platform.health.retryAttempts', 0),
            (int)Configure::read('Platform.health.retryDelayMs', 0),
        ))->check()->toSafeArray();

        $this->set([
            'health' => $health,
            'tenantCounts' => $this->tenantCounts(),
            'recentTenants' => $this->recentTenants(8),
            'failedOperations' => $this->failedOperations(8),
            'backupIssues' => $this->backupIssues(8),
            'releaseStatus' => $this->releaseStatus(),
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function tenantCounts(): array
    {
        try {
            $rows = $this->platform()->execute(
                'SELECT status, COUNT(*) AS count FROM tenants GROUP BY status ORDER BY status',
            )->fetchAll('assoc');
        } catch (Throwable) {
            return [];
        }

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string)$row['status']] = (int)$row['count'];
        }

        return $counts;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentTenants(int $limit): array
    {
        try {
            return $this->platform()->execute(
                'SELECT slug, display_name, status, region, primary_host, schema_version
                   FROM tenants
               ORDER BY created_at DESC, slug ASC
                  LIMIT :limit',
                ['limit' => $limit],
                ['limit' => 'integer'],
            )->fetchAll('assoc');
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function failedOperations(int $limit): array
    {
        try {
            return $this->platform()->execute(
                'SELECT j.id, j.job_type, j.status, j.created_at, j.started_at, j.finished_at,
                        CASE WHEN j.last_error IS NULL OR j.last_error = :empty THEN 0 ELSE 1 END AS has_error,
                        t.slug AS tenant_slug
                   FROM platform_jobs j
              LEFT JOIN tenants t ON t.id = j.tenant_id
                  WHERE j.status IN (:failed, :queued, :running)
                    AND (
                        j.status = :failed
                        OR j.created_at <= :staleBefore
                    )
               ORDER BY j.created_at DESC
                  LIMIT :limit',
                [
                    'empty' => '',
                    'failed' => 'failed',
                    'queued' => 'queued',
                    'running' => 'running',
                    'staleBefore' => gmdate('Y-m-d H:i:s', time() - 60 * 60),
                    'limit' => $limit,
                ],
                ['limit' => 'integer'],
            )->fetchAll('assoc');
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function backupIssues(int $limit): array
    {
        try {
            return $this->platform()->execute(
                'SELECT b.id, b.backup_type, b.status, b.object_size_bytes, b.created_at, b.completed_at,
                        b.retention_until, t.slug AS tenant_slug
                   FROM tenant_backups b
                   JOIN tenants t ON t.id = b.tenant_id
                  WHERE b.status != :completed
                     OR b.completed_at IS NULL
                     OR b.completed_at <= :staleBefore
               ORDER BY b.created_at DESC
                  LIMIT :limit',
                [
                    'completed' => 'completed',
                    'staleBefore' => gmdate('Y-m-d H:i:s', time() - 24 * 60 * 60),
                    'limit' => $limit,
                ],
                ['limit' => 'integer'],
            )->fetchAll('assoc');
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function releaseStatus(): array
    {
        try {
            $manifest = ReleaseManifest::fromFile(ROOT . DS . 'config' . DS . 'release_manifest.json');
            $tenantRows = $this->platform()->execute(
                'SELECT slug, schema_version FROM tenants WHERE status = :status ORDER BY slug',
                ['status' => 'active'],
            )->fetchAll('assoc');
            $errors = (new ReleaseCompatibilityChecker())->incompatibleTenants($tenantRows, $manifest);

            return [
                'available' => true,
                'appVersion' => $manifest->appVersion,
                'minTenantSchema' => $manifest->minTenantSchema,
                'maxTenantSchema' => $manifest->maxTenantSchema,
                'activeTenantCount' => count($tenantRows),
                'incompatibleCount' => count($errors),
                'errors' => $errors,
            ];
        } catch (Throwable) {
            return [
                'available' => false,
                'message' => 'Release manifest compatibility is unavailable.',
            ];
        }
    }
}
