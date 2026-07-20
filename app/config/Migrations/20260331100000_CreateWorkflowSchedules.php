<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Create workflow_schedules table for tracking scheduled workflow execution times.
 *
 * Each row links to a workflow_definition with trigger_type='scheduled' and
 * records when it last ran and when it's next due.
 */
class CreateWorkflowSchedules extends BaseMigration
{
    /**
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('workflow_schedules');

        $table
            ->addColumn('workflow_definition_id', 'integer', [
                'null' => false,
                'comment' => 'FK to workflow_definitions',
            ])
            ->addColumn('last_run_at', 'datetime', [
                'null' => true,
                'default' => null,
                'comment' => 'When this schedule last executed',
            ])
            ->addColumn('next_run_at', 'datetime', [
                'null' => true,
                'default' => null,
                'comment' => 'Pre-computed next execution time',
            ])
            ->addColumn('is_enabled', 'boolean', [
                'null' => false,
                'default' => true,
                'comment' => 'Whether this schedule is active',
            ])
            ->addColumn('created', 'datetime', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('modified', 'datetime', [
                'null' => true,
                'default' => null,
            ])
            ->addIndex(['workflow_definition_id'], ['unique' => true])
            ->addIndex(['is_enabled'])
            ->addIndex(['next_run_at'])
            ->addForeignKey('workflow_definition_id', 'workflow_definitions', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
            ])
            ->create();
    }
}
