<?php
declare(strict_types=1);

namespace Awards\Model\Table;

use App\Model\Table\BaseTable;

/** Durable progress for tenant-local approval synchronization. */
class ApprovalSyncItemsTable extends BaseTable
{
    /** Configure persistence for durable synchronization records. */
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('awards_approval_sync_items');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
        $this->setJsonColumnTypesIfPresent(['expected_run_ids']);
    }
}
