<?php
declare(strict_types=1);

namespace App\Queue\Task;

use App\Services\GridSubscriptionService;
use Queue\Queue\ServicesTrait;
use Queue\Queue\Task;

class GridSubscriptionTask extends Task
{
    use ServicesTrait;

    public ?int $timeout = 300;
    public ?int $retries = 3;

    /** @inheritDoc */
    public function run(array $data, int $jobId): void
    {
        $this->getService(GridSubscriptionService::class)->deliver(
            (int)$data['subscriptionId'],
            (string)$data['claimToken'],
        );
    }
}
