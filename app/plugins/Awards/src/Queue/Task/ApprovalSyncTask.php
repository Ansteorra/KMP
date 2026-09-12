<?php
declare(strict_types=1);

namespace Awards\Queue\Task;

use App\Services\WorkflowEngine\WorkflowEngineInterface;
use Awards\Services\ApprovalSyncJobService;
use Awards\Services\RecommendationApprovalWorkflowSyncService;
use Queue\Queue\ServicesTrait;
use Queue\Queue\Task;

/** Tenant-local, bounded approval synchronization queue task. */
class ApprovalSyncTask extends Task
{
    use ServicesTrait;

    public ?int $timeout = 120;
    public ?int $retries = 3;

    /** Execute one recoverable tenant-local delivery. */
    public function run(array $data, int $jobId): void
    {
        $sync = new RecommendationApprovalWorkflowSyncService($this->getService(WorkflowEngineInterface::class));
        (new ApprovalSyncJobService($sync))->work($data);
    }
}
