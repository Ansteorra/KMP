<?php

declare(strict_types=1);

namespace App\Test\TestCase\Core\Unit\Config\Seeds;

use App\Model\Entity\WarrantRoster;
use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowApprovalResponse;
use App\Model\Entity\WorkflowExecutionLog;
use App\Model\Entity\WorkflowInstance;
use App\Test\TestCase\BaseTestCase;
use Cake\I18n\DateTime;
use DevLoadBestowalTodoUsersSeed;
use DevRepairWarrantRosterApprovalsSeed;
use RuntimeException;

/**
 * Regression coverage for development warrant-roster seeds.
 */
final class DevWarrantRosterSeedsTest extends BaseTestCase
{
    /**
     * Migrated seed workflows regain response history, lookup data, and current titles.
     *
     * @return void
     */
    public function testRepairSeedRestoresMigratedApprovalAndIsIdempotent(): void
    {
        $ids = $this->createBrokenMigratedApproval();
        require_once ROOT . '/config/Seeds/DevRepairWarrantRosterApprovalsSeed.php';

        (new DevRepairWarrantRosterApprovalsSeed())->run();
        (new DevRepairWarrantRosterApprovalsSeed())->run();

        $approval = $this->getTableLocator()->get('WorkflowApprovals')->get($ids['approvalId']);
        $this->assertSame(WorkflowApproval::STATUS_APPROVED, $approval->status);
        $this->assertSame(1, $approval->approved_count);
        $this->assertSame(WorkflowApproval::APPROVER_TYPE_POLICY, $approval->approver_lookup_type);
        $this->assertSame('App\\Policy\\WarrantRosterPolicy', $approval->approver_lookup_name);
        $this->assertSame('Warrant Roster: Dev seed repair regression roster', $approval->request_title);

        $responses = $this->getTableLocator()->get('WorkflowApprovalResponses')
            ->find()
            ->where(['workflow_approval_id' => $ids['approvalId']])
            ->all()
            ->toList();
        $this->assertCount(1, $responses);
        $this->assertSame(WorkflowApprovalResponse::DECISION_APPROVE, $responses[0]->decision);
        $this->assertSame(self::ADMIN_MEMBER_ID, $responses[0]->member_id);
        $this->assertSame(
            'Reconstructed from the approved development seed roster.',
            $responses[0]->comment,
        );

        $instance = $this->getTableLocator()->get('WorkflowInstances')->get($ids['instanceId']);
        $this->assertSame('Dev seed repair regression roster', $instance->context['trigger']['rosterName']);
        $this->assertSame(1, $instance->context['trigger']['approvalsRequired']);
    }

    /**
     * Pending migrated approvals become discoverable without gaining a response.
     *
     * @return void
     */
    public function testRepairSeedPopulatesPendingPolicyLookupWithoutResponse(): void
    {
        $ids = $this->createBrokenMigratedApproval();
        $locator = $this->getTableLocator();
        $locator->get('WarrantRosters')->updateAll([
            'approval_count' => null,
            'status' => WarrantRoster::STATUS_PENDING,
        ], ['id' => $ids['rosterId']]);
        $locator->get('WorkflowInstances')->updateAll([
            'status' => WorkflowInstance::STATUS_WAITING,
        ], ['id' => $ids['instanceId']]);
        $locator->get('WorkflowApprovals')->updateAll([
            'status' => WorkflowApproval::STATUS_PENDING,
        ], ['id' => $ids['approvalId']]);
        require_once ROOT . '/config/Seeds/DevRepairWarrantRosterApprovalsSeed.php';

        (new DevRepairWarrantRosterApprovalsSeed())->run();

        $approval = $locator->get('WorkflowApprovals')->get($ids['approvalId']);
        $this->assertSame(WorkflowApproval::STATUS_PENDING, $approval->status);
        $this->assertSame(0, $approval->approved_count);
        $this->assertSame(WorkflowApproval::APPROVER_TYPE_POLICY, $approval->approver_lookup_type);
        $this->assertSame('App\\Policy\\WarrantRosterPolicy', $approval->approver_lookup_name);
        $this->assertSame(
            0,
            $locator->get('WorkflowApprovalResponses')
                ->find()
                ->where(['workflow_approval_id' => $ids['approvalId']])
                ->count(),
        );
    }

    /**
     * The pre-approved Bestowal fixture overrides guarded workflow status.
     *
     * @return void
     */
    public function testBestowalSeedPersistsApprovedRosterStatusAndIsIdempotent(): void
    {
        $rosters = $this->getTableLocator()->get('WarrantRosters');
        $existing = $rosters->find()
            ->where(['name' => 'Bestowal To-Do Demo Warrants'])
            ->first();
        if ($existing === null) {
            $existing = $rosters->newEmptyEntity();
            $existing->patch([
                'name' => 'Bestowal To-Do Demo Warrants',
                'approvals_required' => 1,
                'approval_count' => 0,
                'status' => WarrantRoster::STATUS_PENDING,
                'created_by' => self::ADMIN_MEMBER_ID,
            ], ['guard' => false]);
            $rosters->saveOrFail($existing);
        } else {
            $rosters->getConnection()->update('warrant_rosters', [
                'approval_count' => 0,
                'status' => WarrantRoster::STATUS_PENDING,
            ], ['id' => $existing->id]);
        }

        require_once ROOT . '/config/Seeds/DevLoadBestowalTodoUsersSeed.php';
        (new DevLoadBestowalTodoUsersSeed())->run();
        (new DevLoadBestowalTodoUsersSeed())->run();

        $matches = $rosters->find()
            ->where(['name' => 'Bestowal To-Do Demo Warrants'])
            ->all()
            ->toList();
        $this->assertCount(1, $matches);
        $this->assertSame((int)$existing->id, (int)$matches[0]->id);
        $this->assertSame(WarrantRoster::STATUS_APPROVED, $matches[0]->status);
        $this->assertSame(1, $matches[0]->approvals_required);
        $this->assertSame(1, $matches[0]->approval_count);
    }

    /**
     * Create the exact post-migration inconsistency found in the old dev seed.
     *
     * @return array{rosterId:int,instanceId:int,approvalId:int}
     */
    private function createBrokenMigratedApproval(): array
    {
        $locator = $this->getTableLocator();
        $definition = $locator->get('WorkflowDefinitions')
            ->find()
            ->where(['slug' => 'warrants-roster-approval'])
            ->first();
        if ($definition === null) {
            throw new RuntimeException('The warrant roster workflow definition must be seeded for this test.');
        }
        $version = $locator->get('WorkflowVersions')
            ->find()
            ->where(['workflow_definition_id' => $definition->id])
            ->orderByDesc('version_number')
            ->first();
        if ($version === null) {
            throw new RuntimeException('The warrant roster workflow version must be seeded for this test.');
        }

        $now = DateTime::now();
        $rosters = $locator->get('WarrantRosters');
        $roster = $rosters->newEmptyEntity();
        $roster->patch([
            'name' => 'Dev seed repair regression roster',
            'approvals_required' => 1,
            'approval_count' => 1,
            'status' => WarrantRoster::STATUS_APPROVED,
            'created_by' => self::ADMIN_MEMBER_ID,
            'created' => $now,
            'modified' => $now,
        ], ['guard' => false]);
        $rosters->saveOrFail($roster);

        $instances = $locator->get('WorkflowInstances');
        $instance = $instances->newEntity([
            'workflow_definition_id' => $definition->id,
            'workflow_version_id' => $version->id,
            'entity_type' => 'WarrantRosters',
            'entity_id' => $roster->id,
            'status' => WorkflowInstance::STATUS_COMPLETED,
            'context' => [
                'trigger' => [
                    'rosterId' => $roster->id,
                    'rosterName' => 'Stale roster name',
                    'approvalsRequired' => 99,
                ],
                'migrated' => true,
            ],
            'active_nodes' => [],
            'started_by' => self::ADMIN_MEMBER_ID,
            'started_at' => $now,
            'completed_at' => $now,
        ]);
        $instances->saveOrFail($instance);

        $logs = $locator->get('WorkflowExecutionLogs');
        $log = $logs->newEntity([
            'workflow_instance_id' => $instance->id,
            'node_id' => 'approval-gate',
            'node_type' => 'approval',
            'attempt_number' => 1,
            'status' => WorkflowExecutionLog::STATUS_COMPLETED,
            'started_at' => $now,
            'completed_at' => $now,
        ]);
        $logs->saveOrFail($log);

        $approvals = $locator->get('WorkflowApprovals');
        $approval = $approvals->newEntity([
            'workflow_instance_id' => $instance->id,
            'node_id' => 'approval-gate',
            'execution_log_id' => $log->id,
            'approver_type' => WorkflowApproval::APPROVER_TYPE_POLICY,
            'approver_config' => [
                'permission' => 'Can Approve Warrant Rosters',
                'policyClass' => 'App\\Policy\\WarrantRosterPolicy',
                'policyAction' => 'canApprove',
                'entityTable' => 'WarrantRosters',
                'entityIdKey' => 'trigger.rosterId',
            ],
            'request_title' => 'Warrant Roster: Stale roster name',
            'requester_member_id' => self::ADMIN_MEMBER_ID,
            'required_count' => 1,
            'approved_count' => 0,
            'rejected_count' => 0,
            'status' => WorkflowApproval::STATUS_APPROVED,
            'allow_parallel' => true,
            'version' => 1,
            'approval_token' => bin2hex(random_bytes(16)),
        ]);
        $approvals->saveOrFail($approval);
        $approvals->updateAll([
            'approver_lookup_type' => null,
            'approver_lookup_name' => null,
        ], ['id' => $approval->id]);

        return [
            'rosterId' => (int)$roster->id,
            'instanceId' => (int)$instance->id,
            'approvalId' => (int)$approval->id,
        ];
    }
}
