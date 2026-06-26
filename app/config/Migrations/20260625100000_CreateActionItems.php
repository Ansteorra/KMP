<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Create the reusable, app-wide Action Item ("To-Do") subsystem tables.
 *
 * Action items are polymorphic, parallel to-dos attached to any entity. Each
 * item declares who may complete it using the same assignee vocabulary as the
 * workflow approval subsystem (permission, role, member, dynamic, policy), so
 * the existing "who can act" resolution can be reused. Items can be marked as
 * gating, meaning they count toward "all required to-dos complete" for their
 * parent entity.
 */
class CreateActionItems extends BaseMigration
{
    /**
     * Create action_items and action_item_logs tables.
     *
     * @return void
     */
    public function up(): void
    {
        $this->table('action_items')
            ->addColumn('entity_type', 'string', [
                'limit' => 100,
                'null' => false,
                'comment' => 'Polymorphic owner type, e.g. Awards.Bestowals',
            ])
            ->addColumn('entity_id', 'integer', [
                'null' => false,
                'comment' => 'Polymorphic owner primary key',
            ])
            ->addColumn('title', 'string', [
                'limit' => 255,
                'null' => false,
                'comment' => 'Short label shown in the to-do list',
            ])
            ->addColumn('description', 'text', [
                'default' => null,
                'null' => true,
            ])
            ->addColumn('assignee_type', 'string', [
                'limit' => 30,
                'null' => false,
                'comment' => 'permission | role | member | dynamic | policy',
            ])
            ->addColumn('assignee_config', 'json', [
                'default' => null,
                'null' => true,
                'comment' => 'Assignee resolution config (permission name, role, member_id, dynamic service/method, etc.)',
            ])
            ->addColumn('branch_id', 'integer', [
                'default' => null,
                'null' => true,
                'comment' => 'Resolved branch scope used for assignee eligibility lookups',
            ])
            ->addColumn('status', 'string', [
                'limit' => 20,
                'default' => 'open',
                'null' => false,
                'comment' => 'open | completed | cancelled',
            ])
            ->addColumn('is_gating', 'boolean', [
                'default' => true,
                'null' => false,
                'comment' => 'Whether this item counts toward all-required-complete',
            ])
            ->addColumn('sort_order', 'integer', [
                'default' => 0,
                'null' => false,
            ])
            ->addColumn('source_ref', 'string', [
                'limit' => 100,
                'default' => null,
                'null' => true,
                'comment' => 'Template item key this item was materialized from',
            ])
            ->addColumn('completed_at', 'datetime', [
                'default' => null,
                'null' => true,
            ])
            ->addColumn('completed_by', 'integer', [
                'default' => null,
                'null' => true,
            ])
            ->addColumn('created', 'datetime', [
                'null' => false,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'null' => true,
            ])
            ->addColumn('created_by', 'integer', [
                'default' => null,
                'null' => true,
            ])
            ->addColumn('modified_by', 'integer', [
                'default' => null,
                'null' => true,
            ])
            ->addColumn('deleted', 'datetime', [
                'default' => null,
                'null' => true,
            ])
            ->addIndex(['entity_type', 'entity_id'], [
                'name' => 'idx_action_items_entity',
            ])
            ->addIndex(['status'], [
                'name' => 'idx_action_items_status',
            ])
            ->addIndex(['assignee_type'], [
                'name' => 'idx_action_items_assignee_type',
            ])
            ->addIndex(['branch_id'], [
                'name' => 'idx_action_items_branch_id',
            ])
            ->addIndex(['deleted'], [
                'name' => 'idx_action_items_deleted',
            ])
            ->addForeignKey('branch_id', 'branches', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_action_items_branch',
            ])
            ->addForeignKey('completed_by', 'members', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_action_items_completed_by',
            ])
            ->create();

        $this->table('action_item_logs')
            ->addColumn('action_item_id', 'integer', [
                'null' => false,
            ])
            ->addColumn('from_status', 'string', [
                'limit' => 20,
                'null' => false,
            ])
            ->addColumn('to_status', 'string', [
                'limit' => 20,
                'null' => false,
            ])
            ->addColumn('note', 'text', [
                'default' => null,
                'null' => true,
            ])
            ->addColumn('created', 'datetime', [
                'null' => false,
            ])
            ->addColumn('created_by', 'integer', [
                'default' => null,
                'null' => true,
            ])
            ->addIndex(['action_item_id'], [
                'name' => 'idx_action_item_logs_item',
            ])
            ->addForeignKey('action_item_id', 'action_items', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_action_item_logs_item',
            ])
            ->create();
    }

    /**
     * Drop the Action Item subsystem tables.
     *
     * @return void
     */
    public function down(): void
    {
        $this->table('action_item_logs')->drop()->save();
        $this->table('action_items')->drop()->save();
    }
}
