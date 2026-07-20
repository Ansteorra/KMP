<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Adds denormalized current_approver_id FK to workflow_approvals
 * for sortable/filterable "Assigned To" column in the approvals grid.
 */
class AddCurrentApproverIdToWorkflowApprovals extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('workflow_approvals');
        $table->addColumn('current_approver_id', 'integer', [
            'null' => true,
            'default' => null,
            'after' => 'approver_config',
        ]);
        $table->addIndex(['current_approver_id'], ['name' => 'idx_wa_current_approver']);
        $table->update();

        // Backfill in PHP so JSON extraction remains portable across engines.
        $rows = $this->fetchAll(
            "SELECT id, approver_config
             FROM workflow_approvals
             WHERE approver_config IS NOT NULL
               AND status = 'pending'"
        );

        foreach ($rows as $row) {
            $config = json_decode((string)$row['approver_config'], true);
            if (!is_array($config) || !array_key_exists('current_approver_id', $config)) {
                continue;
            }

            $approverId = $config['current_approver_id'];
            if (is_string($approverId)) {
                $approverId = trim($approverId);
            }

            if (
                !(is_int($approverId) || (is_string($approverId) && ctype_digit($approverId)))
                || (int)$approverId < 0
            ) {
                continue;
            }

            $this->execute(sprintf(
                'UPDATE workflow_approvals SET current_approver_id = %d WHERE id = %d',
                (int)$approverId,
                (int)$row['id']
            ));
        }
    }

    public function down(): void
    {
        $table = $this->table('workflow_approvals');
        $table->removeColumn('current_approver_id');
        $table->update();
    }
}
