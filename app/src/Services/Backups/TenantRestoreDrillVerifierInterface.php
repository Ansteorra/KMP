<?php
declare(strict_types=1);

// phpcs:disable CakePHP.Commenting.FunctionComment.Missing

namespace App\Services\Backups;

interface TenantRestoreDrillVerifierInterface
{
    public function verify(TenantRestoreDrillPlan $plan): void;
}
