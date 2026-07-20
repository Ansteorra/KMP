<?php
declare(strict_types=1);

// phpcs:disable CakePHP.Commenting.FunctionComment.Missing

namespace App\Services\Backups;

class TenantRestoreServiceDrillVerifier implements TenantRestoreDrillVerifierInterface
{
    public function __construct(private readonly TenantRestoreService $restoreService)
    {
    }

    public function verify(TenantRestoreDrillPlan $plan): void
    {
        $this->restoreService->restoreTenantBackup(
            $plan->backupId,
            TenantRestoreService::MODE_SAME_TENANT,
            null,
            $plan->destructiveExecution,
            $plan->dryRun,
        );
    }
}
