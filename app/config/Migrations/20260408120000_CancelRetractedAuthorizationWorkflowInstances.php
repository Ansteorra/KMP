<?php

declare(strict_types=1);

use App\Migrations\CrossEngineMigrationTrait;
use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowInstance;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Migrations\AbstractMigration;

/**
 * Cancel any authorization-request workflow instances whose authorization is already retracted.
 */
class CancelRetractedAuthorizationWorkflowInstances extends AbstractMigration
{
    use CrossEngineMigrationTrait;

    public function up(): void
    {
        // Skip if the Activities plugin table doesn't exist (fresh install).
        if (!$this->tableExistsInDb('activities_authorizations')) {
            return;
        }

        $authorizationsTable = TableRegistry::getTableLocator()->get('Activities.Authorizations');
        $instancesTable = TableRegistry::getTableLocator()->get('WorkflowInstances');
        $approvalsTable = TableRegistry::getTableLocator()->get('WorkflowApprovals');

        $retractedAuthorizationIds = $authorizationsTable->find()
            ->select(['id'])
            ->where(['status' => 'Retracted'])
            ->all()
            ->extract('id')
            ->map(fn($id) => (int)$id)
            ->toList();

        if ($retractedAuthorizationIds === []) {
            return;
        }

        $instances = $instancesTable->find()
            ->where([
                'entity_type IN' => ['Activities', 'Activities.Authorizations'],
                'status IN' => [
                    WorkflowInstance::STATUS_PENDING,
                    WorkflowInstance::STATUS_RUNNING,
                    WorkflowInstance::STATUS_WAITING,
                ],
            ])
            ->all();

        foreach ($instances as $instance) {
            $authorizationId = $this->resolveAuthorizationId($instance);
            if ($authorizationId === null || !in_array($authorizationId, $retractedAuthorizationIds, true)) {
                continue;
            }

            $pendingApprovals = $approvalsTable->find()
                ->where([
                    'workflow_instance_id' => $instance->id,
                    'status' => WorkflowApproval::STATUS_PENDING,
                ])
                ->all();

            foreach ($pendingApprovals as $approval) {
                $approval->status = WorkflowApproval::STATUS_CANCELLED;
                $approvalsTable->saveOrFail($approval);
            }

            $errorInfo = $instance->error_info ?? [];
            $errorInfo['cancellation_reason'] = 'Authorization request retracted';
            $instance->status = WorkflowInstance::STATUS_CANCELLED;
            $instance->completed_at = DateTime::now();
            $instance->error_info = $errorInfo;
            $instancesTable->saveOrFail($instance);
        }
    }

    public function down(): void
    {
        // Not reversible.
    }

    /**
     * Resolve the authorization ID linked to a workflow instance.
     *
     * @param \App\Model\Entity\WorkflowInstance $instance Workflow instance candidate
     * @return int|null
     */
    private function resolveAuthorizationId(WorkflowInstance $instance): ?int
    {
        if ($instance->entity_id !== null) {
            return (int)$instance->entity_id;
        }

        $context = $instance->context ?? [];
        $authorizationId = $context['trigger']['authorizationId']
            ?? $context['nodes']['validate-request']['result']['authorizationId']
            ?? null;

        return is_numeric($authorizationId) ? (int)$authorizationId : null;
    }
}
