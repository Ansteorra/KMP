<?php
declare(strict_types=1);

namespace App\Controller\PlatformAdmin;

use App\Services\Backups\BackupReadinessService;
use App\Services\Platform\PlatformFleetHealthService;
use App\Services\Platform\PlatformHealthService;
use App\Services\Platform\ReleaseCompatibilityChecker;
use App\Services\Platform\ReleaseManifest;
use Cake\Core\Configure;
use Cake\Log\Log;
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

        $fleet = [
            'generated_at' => null,
            'summary' => [],
            'status_counts' => [],
            'tenants' => [],
            'operation_issues' => [],
            'schedule_issues' => [],
            'platform_backup' => null,
        ];
        $operationalDataAvailable = true;
        try {
            $fleet = (new PlatformFleetHealthService($this->platform()))->snapshot();
        } catch (Throwable $exception) {
            $operationalDataAvailable = false;
            Log::warning(sprintf('Platform fleet snapshot failed: %s', $exception::class));
        }

        $this->set([
            'health' => $health,
            'fleet' => $fleet,
            'operationalDataAvailable' => $operationalDataAvailable,
            'backupReadiness' => (new BackupReadinessService())->check(),
            'releaseStatus' => $this->releaseStatus(),
        ]);
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
