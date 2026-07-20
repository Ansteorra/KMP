<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Backups;

use App\Services\Backups\TenantRestoreDrillPlan;
use App\Services\Backups\TenantRestoreDrillVerifierInterface;
use RuntimeException;

class RecordingRestoreDrillVerifier implements TenantRestoreDrillVerifierInterface
{
    /**
     * @var list<\App\Services\Backups\TenantRestoreDrillPlan>
     */
    public array $plans = [];

    public function __construct(private readonly ?string $failureMessage = null)
    {
    }

    public function verify(TenantRestoreDrillPlan $plan): void
    {
        $this->plans[] = $plan;
        if ($this->failureMessage !== null) {
            throw new RuntimeException($this->failureMessage);
        }
    }
}
