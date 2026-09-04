<?php
declare(strict_types=1);

use App\Model\Entity\WarrantRoster;
use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowApprovalResponse;
use App\Model\Table\WorkflowApprovalResponsesTable;
use App\Model\Table\WorkflowApprovalsTable;
use Cake\Core\Configure;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Migrations\BaseSeed;

/**
 * Repairs warrant-roster workflow history restored by development snapshots.
 *
 * Historical snapshots can predate workflow lookup columns or lose approval
 * rows while production identities are pruned. This seed is intentionally
 * development-only and idempotently restores the synthetic history needed by
 * the local approval UI.
 */
class DevRepairWarrantRosterApprovalsSeed extends BaseSeed
{
    private const APPROVAL_COMMENT = 'Reconstructed from the approved development seed roster.';

    /**
     * Repair migrated warrant-roster approvals in the development database.
     *
     * @return void
     */
    public function run(): void
    {
        if (!(bool)Configure::read('debug')) {
            throw new RuntimeException('Development warrant approval repair requires debug mode.');
        }

        $locator = TableRegistry::getTableLocator();
        $members = $locator->get('Members');
        $rosters = $locator->get('WarrantRosters');
        $instances = $locator->get('WorkflowInstances');
        $approvals = $locator->get('WorkflowApprovals');
        $responses = $locator->get('WorkflowApprovalResponses');
        $admin = $members->find()
            ->select(['id'])
            ->where(['email_address' => 'admin@amp.ansteorra.org'])
            ->first();
        if ($admin === null) {
            throw new RuntimeException('Development warrant approval repair requires the seeded admin member.');
        }
        $adminId = (int)$admin->id;

        $rosters->getConnection()->transactional(function () use (
            $rosters,
            $instances,
            $approvals,
            $responses,
            $adminId,
        ): void {
            $migratedInstances = $instances->find()
                ->where(['entity_type' => 'WarrantRosters'])
                ->all();

            foreach ($migratedInstances as $instance) {
                $context = $instance->context;
                if (!is_array($context) || ($context['migrated'] ?? false) !== true) {
                    continue;
                }

                $roster = $rosters->find()
                    ->where(['id' => $instance->entity_id])
                    ->first();
                if ($roster === null) {
                    throw new RuntimeException(sprintf(
                        'Migrated warrant workflow %d references missing roster %d.',
                        (int)$instance->id,
                        (int)$instance->entity_id,
                    ));
                }

                $trigger = is_array($context['trigger'] ?? null) ? $context['trigger'] : [];
                $trigger['rosterName'] = (string)$roster->name;
                $trigger['approvalsRequired'] = (int)$roster->approvals_required;
                $context['trigger'] = $trigger;
                $instance->context = $context;
                $instances->saveOrFail($instance);

                $workflowApprovals = $approvals->find()
                    ->where(['workflow_instance_id' => $instance->id])
                    ->all()
                    ->toList();
                if ($workflowApprovals === []) {
                    throw new RuntimeException(sprintf(
                        'Migrated warrant workflow %d has no approval gate.',
                        (int)$instance->id,
                    ));
                }

                foreach ($workflowApprovals as $approval) {
                    $this->repairApproval(
                        $approval,
                        $roster,
                        $approvals,
                        $responses,
                        $adminId,
                    );
                }
            }
        });
    }

    /**
     * Repair one migrated workflow approval from its authoritative seed roster.
     *
     * @param \App\Model\Entity\WorkflowApproval $approval Workflow approval.
     * @param \App\Model\Entity\WarrantRoster $roster Seeded warrant roster.
     * @param \App\Model\Table\WorkflowApprovalsTable $approvals Approval table.
     * @param \App\Model\Table\WorkflowApprovalResponsesTable $responses Response table.
     * @param int $adminId Synthetic approver member ID.
     * @return void
     */
    private function repairApproval(
        WorkflowApproval $approval,
        WarrantRoster $roster,
        WorkflowApprovalsTable $approvals,
        WorkflowApprovalResponsesTable $responses,
        int $adminId,
    ): void {
        if ($approval->approver_type !== WorkflowApproval::APPROVER_TYPE_POLICY) {
            throw new RuntimeException(sprintf(
                'Migrated warrant approval %d has unsupported approver type "%s".',
                (int)$approval->id,
                (string)$approval->approver_type,
            ));
        }

        $config = is_array($approval->approver_config) ? $approval->approver_config : [];
        $policyClass = trim((string)($config['policyClass'] ?? ''));
        if ($policyClass === '') {
            throw new RuntimeException(sprintf(
                'Migrated warrant approval %d has no policy class.',
                (int)$approval->id,
            ));
        }

        $approval->patch([
            'approver_lookup_type' => WorkflowApproval::APPROVER_TYPE_POLICY,
            'approver_lookup_name' => $policyClass,
            'request_title' => sprintf('Warrant Roster: %s', (string)$roster->name),
        ]);

        if ($roster->status === WarrantRoster::STATUS_APPROVED) {
            $this->repairApprovedResponse($approval, $roster, $responses, $adminId);
            $approval->approved_count = (int)$roster->approval_count;
            $approval->status = WorkflowApproval::STATUS_APPROVED;
        }

        $approvals->saveOrFail($approval);
    }

    /**
     * Reconcile an approved roster with its migrated approval responses.
     *
     * Complete response history, including multiple distinct approvers, is
     * preserved. The seed reconstructs only a single wholly missing response;
     * absent counts and incomplete or conflicting histories are rejected rather
     * than fabricating additional approver identities.
     *
     * @param \App\Model\Entity\WorkflowApproval $approval Workflow approval.
     * @param \App\Model\Entity\WarrantRoster $roster Seeded warrant roster.
     * @param \App\Model\Table\WorkflowApprovalResponsesTable $responses Response table.
     * @param int $adminId Synthetic approver member ID.
     * @return void
     */
    private function repairApprovedResponse(
        WorkflowApproval $approval,
        WarrantRoster $roster,
        WorkflowApprovalResponsesTable $responses,
        int $adminId,
    ): void {
        $expectedCount = (int)$roster->approval_count;
        if ($expectedCount < 1) {
            throw new RuntimeException(sprintf(
                'Approved development roster %d has no recorded approval count.',
                (int)$roster->id,
            ));
        }

        $allResponses = $responses->find()
            ->where(['workflow_approval_id' => $approval->id])
            ->all()
            ->toList();
        $approvedResponses = array_filter(
            $allResponses,
            static fn($response): bool => $response->decision === WorkflowApprovalResponse::DECISION_APPROVE,
        );

        if (count($allResponses) !== count($approvedResponses)) {
            throw new RuntimeException(sprintf(
                'Approved development roster %d has conflicting workflow responses.',
                (int)$roster->id,
            ));
        }
        if (count($approvedResponses) === $expectedCount) {
            return;
        }
        if ($expectedCount !== 1 || $approvedResponses !== []) {
            throw new RuntimeException(sprintf(
                'Approved development roster %d requires %d responses but has %d.',
                (int)$roster->id,
                $expectedCount,
                count($approvedResponses),
            ));
        }

        $respondedAt = $roster->modified ?? $roster->created ?? DateTime::now();
        $response = $responses->newEntity([
            'workflow_approval_id' => $approval->id,
            'member_id' => $adminId,
            'decision' => WorkflowApprovalResponse::DECISION_APPROVE,
            'comment' => self::APPROVAL_COMMENT,
            'responded_at' => $respondedAt,
        ]);
        $responses->saveOrFail($response);
    }
}
