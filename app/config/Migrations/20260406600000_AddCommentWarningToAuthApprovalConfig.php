<?php

declare(strict_types=1);

use Cake\ORM\TableRegistry;
use Migrations\AbstractMigration;

/**
 * Add commentWarning to the authorization approval workflow definition
 * and backfill existing pending approvals with the warning in approver_config.
 */
class AddCommentWarningToAuthApprovalConfig extends AbstractMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        $jsonPath = ROOT . '/config/Seeds/WorkflowDefinitions/activities-authorization-request.json';
        if (!file_exists($jsonPath)) {
            return;
        }

        $definitionData = json_decode(file_get_contents($jsonPath), true);

        // Update workflow versions
        $versionsTable = TableRegistry::getTableLocator()->get('WorkflowVersions');
        $versions = $versionsTable->find()
            ->select(['id', 'definition'])
            ->where(['workflow_definition_id' => 4])
            ->all();

        foreach ($versions as $version) {
            $versionsTable->updateAll(
                ['definition' => $definitionData],
                ['id' => $version->id],
            );
        }

        // Backfill existing pending approvals with comment_warning
        $approvalsTable = TableRegistry::getTableLocator()->get('WorkflowApprovals');
        $instancesTable = TableRegistry::getTableLocator()->get('WorkflowInstances');

        // Find instances belonging to the authorization workflow (definition_id=4)
        $instanceIds = $instancesTable->find()
            ->select(['WorkflowInstances.id'])
            ->innerJoinWith('WorkflowVersions', function ($q) {
                return $q->where(['WorkflowVersions.workflow_definition_id' => 4]);
            })
            ->all()
            ->extract('id')
            ->toArray();

        if (!empty($instanceIds)) {
            $pendingApprovals = $approvalsTable->find()
                ->select(['id', 'approver_config'])
                ->where([
                    'workflow_instance_id IN' => $instanceIds,
                    'status' => 'pending',
                ])
                ->all();

            $warning = 'Comments may be visible to the person who submitted this request.';
            foreach ($pendingApprovals as $approval) {
                $config = $approval->approver_config ?? [];
                if (empty($config['comment_warning'])) {
                    $config['comment_warning'] = $warning;
                    $approvalsTable->updateAll(
                        ['approver_config' => $config],
                        ['id' => $approval->id],
                    );
                }
            }
        }
    }

    /**
     * @return void
     */
    public function down(): void
    {
        // Not reversible
    }
}
