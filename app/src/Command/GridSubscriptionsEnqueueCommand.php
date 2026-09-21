<?php
declare(strict_types=1);

namespace App\Command;

use App\Services\GridSubscriptionService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;

class GridSubscriptionsEnqueueCommand extends Command
{
    private int $queued = 0;

    /** Number of jobs claimed by the last run. */
    public function lastQueued(): int
    {
        return $this->queued;
    }

    /** @inheritDoc */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $this->queued = (new GridSubscriptionService())->enqueueDue();
        $io->out(sprintf('Queued %d grid summaries.', $this->queued));

        return self::CODE_SUCCESS;
    }
}
