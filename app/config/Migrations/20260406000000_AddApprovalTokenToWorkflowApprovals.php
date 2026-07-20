<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Add approval_token column to workflow_approvals for email deep-link support.
 *
 * Replaces Activities' authorization_token concept with a unified token
 * on workflow approvals. Tokens are generated when approvals are created
 * and used for email-based approval deep links.
 */
class AddApprovalTokenToWorkflowApprovals extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('workflow_approvals');
        $table->addColumn('approval_token', 'string', [
            'limit' => 64,
            'null' => true,
            'default' => null,
            'after' => 'version',
        ]);
        $table->addIndex(['approval_token'], [
            'unique' => true,
            'name' => 'idx_workflow_approvals_token',
        ]);
        $table->update();
    }

    public function down(): void
    {
        $table = $this->table('workflow_approvals');
        $table->removeIndex(['approval_token']);
        $table->removeColumn('approval_token');
        $table->update();
    }
}
