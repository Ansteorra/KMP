<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/** Transactional invalidation metadata; contains no rendered/private record data. */
class CreateUiUpdateOutbox extends BaseMigration
{
    public function change(): void
    {
        $this->table('ui_update_outbox', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'uuid')
            ->addColumn('tenant_id', 'uuid')
            ->addColumn('topics', 'json')
            ->addColumn('created', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('delivered', 'timestamp', ['null' => true])
            ->addColumn('attempts', 'integer', ['default' => 0])
            ->addColumn('available_after', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['delivered', 'available_after'], ['name' => 'idx_ui_outbox_pending'])
            ->create();
    }
}
