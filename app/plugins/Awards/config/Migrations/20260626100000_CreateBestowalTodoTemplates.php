<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Create bestowal to-do template configuration tables.
 *
 * Mirrors the award approval-process pattern: reusable templates assigned to
 * awards, each owning a set of parallel checklist items. Unlike approval steps,
 * to-do items have no sequence/threshold semantics — they are worked in
 * parallel. Each item declares who may complete it (assignee_*) and whether it
 * gates the bestowal's "ready to give" state (is_gating).
 */
class CreateBestowalTodoTemplates extends BaseMigration
{
    /**
     * Create template and template-item tables; add award FK.
     *
     * @return void
     */
    public function up(): void
    {
        $this->table('awards_bestowal_todo_templates')
            ->addColumn('name', 'string', [
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('description', 'text', [
                'default' => null,
                'null' => true,
            ])
            ->addColumn('is_active', 'boolean', [
                'default' => true,
                'null' => false,
            ])
            ->addColumn('branch_id', 'integer', [
                'default' => null,
                'null' => true,
                'comment' => 'Optional owning branch (future per-branch templates); null = system-wide',
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
            ->addIndex(['name'], [
                'unique' => true,
                'name' => 'idx_awards_todo_templates_name',
            ])
            ->addIndex(['is_active'], [
                'name' => 'idx_awards_todo_templates_is_active',
            ])
            ->addIndex(['branch_id'], [
                'name' => 'idx_awards_todo_templates_branch',
            ])
            ->addIndex(['deleted'], [
                'name' => 'idx_awards_todo_templates_deleted',
            ])
            ->addForeignKey('branch_id', 'branches', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_awards_todo_templates_branch',
            ])
            ->create();

        $this->table('awards_bestowal_todo_template_items')
            ->addColumn('template_id', 'integer', [
                'null' => false,
            ])
            ->addColumn('item_key', 'string', [
                'limit' => 100,
                'null' => false,
            ])
            ->addColumn('label', 'string', [
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('description', 'text', [
                'default' => null,
                'null' => true,
            ])
            ->addColumn('assignee_type', 'string', [
                'limit' => 30,
                'null' => false,
                'comment' => 'role | permission | office | member | dynamic',
            ])
            ->addColumn('assignee_source_id', 'integer', [
                'default' => null,
                'null' => true,
                'comment' => 'FK-like id of the role/permission/office/member source',
            ])
            ->addColumn('assignee_source_key', 'string', [
                'default' => null,
                'limit' => 100,
                'null' => true,
                'comment' => 'Optional textual source key (e.g. dynamic resolver key)',
            ])
            ->addColumn('branch_mode', 'string', [
                'default' => 'award_branch',
                'limit' => 50,
                'null' => false,
            ])
            ->addColumn('branch_type', 'string', [
                'default' => null,
                'limit' => 50,
                'null' => true,
            ])
            ->addColumn('is_gating', 'boolean', [
                'default' => true,
                'null' => false,
            ])
            ->addColumn('sort_order', 'integer', [
                'default' => 0,
                'null' => false,
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
            ->addForeignKey('template_id', 'awards_bestowal_todo_templates', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_awards_todo_items_template',
            ])
            ->addIndex(['template_id', 'item_key'], [
                'unique' => true,
                'name' => 'idx_awards_todo_items_key',
            ])
            ->addIndex(['template_id', 'sort_order'], [
                'name' => 'idx_awards_todo_items_sort',
            ])
            ->create();

        $this->table('awards_awards')
            ->addColumn('bestowal_todo_template_id', 'integer', [
                'after' => 'branch_id',
                'default' => null,
                'null' => true,
            ])
            ->addForeignKey('bestowal_todo_template_id', 'awards_bestowal_todo_templates', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_awards_awards_todo_template',
            ])
            ->addIndex(['bestowal_todo_template_id'], [
                'name' => 'idx_awards_awards_todo_template',
            ])
            ->update();
    }

    /**
     * Drop bestowal to-do template tables and the award FK.
     *
     * @return void
     */
    public function down(): void
    {
        $this->table('awards_awards')
            ->dropForeignKey('bestowal_todo_template_id')
            ->removeIndexByName('idx_awards_awards_todo_template')
            ->removeColumn('bestowal_todo_template_id')
            ->update();

        $this->table('awards_bestowal_todo_template_items')->drop()->save();
        $this->table('awards_bestowal_todo_templates')->drop()->save();
    }
}
