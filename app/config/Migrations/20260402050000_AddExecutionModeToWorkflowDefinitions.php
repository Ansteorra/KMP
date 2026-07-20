<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Add execution_mode column to workflow_definitions.
 *
 * - 'durable' (default): Full persistence — instances, logs, approvals stored in DB.
 *   Required for workflows with async nodes (approvals, human tasks, delays).
 * - 'ephemeral': In-memory execution with zero DB footprint. For short-running
 *   synchronous orchestration where domain data changes self-document.
 */
class AddExecutionModeToWorkflowDefinitions extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('workflow_definitions');
        $table->addColumn('execution_mode', 'string', [
            'default' => 'durable',
            'limit' => 20,
            'null' => false,
            'after' => 'is_active',
        ]);
        $table->update();
    }
}
