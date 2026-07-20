<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Create workflow_tasks table for human task nodes in the workflow engine.
 *
 * Each row represents a pending or completed human task that pauses a
 * workflow until a user submits a configured form.
 */
class CreateWorkflowTasks extends BaseMigration
{
    /**
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('workflow_tasks');

        $table
            ->addColumn('workflow_instance_id', 'integer', [
                'null' => false,
                'comment' => 'FK to workflow_instances',
            ])
            ->addColumn('node_id', 'string', [
                'limit' => 100,
                'null' => false,
                'comment' => 'Node ID within the workflow definition',
            ])
            ->addColumn('assigned_to', 'integer', [
                'null' => true,
                'default' => null,
                'comment' => 'FK to members — specific assignee',
            ])
            ->addColumn('assigned_by_role', 'string', [
                'limit' => 255,
                'null' => true,
                'default' => null,
                'comment' => 'Permission name — anyone with this permission can complete',
            ])
            ->addColumn('task_title', 'string', [
                'limit' => 255,
                'null' => true,
                'default' => null,
                'comment' => 'Human-readable task title',
            ])
            ->addColumn('form_definition', 'json', [
                'null' => true,
                'default' => null,
                'comment' => 'JSON array of form field definitions',
            ])
            ->addColumn('form_data', 'json', [
                'null' => true,
                'default' => null,
                'comment' => 'JSON object of submitted form values (null until completed)',
            ])
            ->addColumn('status', 'string', [
                'limit' => 20,
                'null' => false,
                'default' => 'pending',
                'comment' => 'pending, completed, cancelled, expired',
            ])
            ->addColumn('due_date', 'datetime', [
                'null' => true,
                'default' => null,
                'comment' => 'Optional deadline for task completion',
            ])
            ->addColumn('completed_at', 'datetime', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('completed_by', 'integer', [
                'null' => true,
                'default' => null,
                'comment' => 'FK to members — who submitted the form',
            ])
            ->addColumn('created', 'datetime', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('modified', 'datetime', [
                'null' => true,
                'default' => null,
            ])
            ->addIndex(['workflow_instance_id'])
            ->addIndex(['assigned_to'])
            ->addIndex(['status'])
            ->addIndex(['due_date'])
            ->addIndex(['workflow_instance_id', 'node_id'])
            ->addForeignKey('workflow_instance_id', 'workflow_instances', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
            ])
            ->create();
    }
}
