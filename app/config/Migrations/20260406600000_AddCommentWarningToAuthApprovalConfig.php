<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Add commentWarning to the authorization approval workflow definition
 * and backfill existing pending approvals with the warning in approver_config.
 */
class AddCommentWarningToAuthApprovalConfig extends AbstractMigration
{
    public function up(): void
    {
        $jsonPath = ROOT . '/config/Seeds/WorkflowDefinitions/activities-authorization-request.json';
        if (!file_exists($jsonPath)) {
            return;
        }

        $definitionData = json_decode(file_get_contents($jsonPath), true);

        // Update workflow versions
        $versionsTable = \Cake\ORM\TableRegistry::getTableLocator()->get('WorkflowVersions');
        $versions = $versionsTable->find()
            ->where(['workflow_definition_id' => 4])
            ->all();

        foreach ($versions as $version) {
            $version->definition = $definitionData;
            $versionsTable->save($version);
        }

        // Backfill existing pending approvals with comment_warning
        $approvalsTable = \Cake\ORM\TableRegistry::getTableLocator()->get('WorkflowApprovals');
        $instancesTable = \Cake\ORM\TableRegistry::getTableLocator()->get('WorkflowInstances');

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
                    $approval->approver_config = $config;
                    $approvalsTable->save($approval);
                }
            }
        }
    }

    public function down(): void
    {
        // Not reversible
    }
}
