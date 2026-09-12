<?php
declare(strict_types=1);

namespace Awards\Model\Table;

use App\Model\Table\BaseTable;

/** Durable progress for tenant-local approval synchronization. */
class ApprovalSyncRunsTable extends BaseTable
{
    /** Configure persistence for durable synchronization records. */
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('awards_approval_sync_runs');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
    }
}
