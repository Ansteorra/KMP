<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Add optimistic locking `version` column to workflow_approvals.
 *
 * Provides a belt-and-suspenders layer on top of the existing
 * FOR UPDATE + atomic increment pattern to prevent lost updates
 * under high concurrency.
 */
class AddVersionToWorkflowApprovals extends BaseMigration
{
    /**
     * Add version column with default 1 (safe for existing rows).
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('workflow_approvals');

        $table->addColumn('version', 'integer', [
            'default' => 1,
            'null' => false,
            'signed' => false,
            'comment' => 'Optimistic lock version for concurrent modification detection',
            'after' => 'escalation_config',
        ]);

        $table->update();
    }
}
