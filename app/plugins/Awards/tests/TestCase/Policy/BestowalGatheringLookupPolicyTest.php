<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Policy;

use App\Model\Entity\Member;
use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowExecutionLog;
use App\Model\Entity\WorkflowInstance;
use App\Model\Entity\WorkflowVersion;
use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\ApprovalProcessStep;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\RecommendationApprovalRun;
use Awards\Policy\BestowalPolicy;
use Cake\I18n\DateTime;

/**
 * Approver-context authorization for the bestowal gathering autocomplete.
 *
 * The approval Respond modal's Bestowal Gathering lookup must be usable by the
 * member the approval is waiting on (e.g. the Crown), even without bestowal
 * edit or court schedule permissions.
 */
class BestowalGatheringLookupPolicyTest extends BaseTestCase
{
    private const APPROVER_ID = self::TEST_MEMBER_AGATHA_ID;
    private const BYSTANDER_ID = self::TEST_MEMBER_BRYCE_ID;

    public function testPendingApproverCanUseGatheringLookupForRecommendation(): void
    {
        [$recommendationId, $instanceId] = $this->createRecommendationWithApprovalRun();
        $this->createPendingApprovalFor($instanceId, self::APPROVER_ID);

        $policy = new BestowalPolicy();
        $lookupContext = $this->lookupContextEntity($recommendationId);

        $this->assertTrue($policy->canGatheringsForBestowalAutoComplete(
            $this->syntheticMember(self::APPROVER_ID),
            $lookupContext,
        ));
        $this->assertFalse($policy->canGatheringsForBestowalAutoComplete(
            $this->syntheticMember(self::BYSTANDER_ID),
            $lookupContext,
        ));
    }

    public function testLookupWithoutRecommendationContextStaysDenied(): void
    {
        [, $instanceId] = $this->createRecommendationWithApprovalRun();
        $this->createPendingApprovalFor($instanceId, self::APPROVER_ID);

        $policy = new BestowalPolicy();

        $this->assertFalse($policy->canGatheringsForBestowalAutoComplete(
            $this->syntheticMember(self::APPROVER_ID),
            $this->lookupContextEntity(null),
        ));
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function createRecommendationWithApprovalRun(): array
    {
        $processes = $this->getTableLocator()->get('Awards.ApprovalProcesses');
        $process = $processes->saveOrFail($processes->newEntity([
            'name' => 'Gathering Lookup Process ' . uniqid('', true),
            'is_active' => true,
            'approval_process_steps' => [[
                'step_key' => 'approval',
                'label' => 'Approval',
                'sequence' => 1,
                'step_type' => ApprovalProcessStep::STEP_TYPE_APPROVAL,
                'approver_type' => ApprovalProcessStep::APPROVER_TYPE_MEMBER,
                'approver_source_id' => self::APPROVER_ID,
                'branch_mode' => ApprovalProcessStep::BRANCH_MODE_AWARD,
                'threshold_mode' => ApprovalProcessStep::THRESHOLD_ANY,
                'on_reject' => ApprovalProcessStep::ACTION_RETURN_PREVIOUS,
                'on_request_changes' => ApprovalProcessStep::ACTION_RETURN_PREVIOUS,
                'retain_read_visibility' => true,
            ]],
        ], ['associated' => ['ApprovalProcessSteps']]));

        $awards = $this->getTableLocator()->get('Awards.Awards');
        $award = $awards->saveOrFail($awards->newEntity([
            'name' => 'Gathering Lookup Award ' . uniqid('', true),
            'abbreviation' => strtoupper(substr(md5(uniqid('', true)), 0, 8)),
            'domain_id' => 2,
            'level_id' => 1,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'approval_process_id' => $process->id,
            'is_active' => true,
        ]));

        $recommendations = $this->getTableLocator()->get('Awards.Recommendations');
        $recommendation = $recommendations->saveOrFail($recommendations->newEntity([
            'requester_id' => self::ADMIN_MEMBER_ID,
            'requester_sca_name' => 'Lookup Test Requester',
            'member_id' => self::BYSTANDER_ID,
            'member_sca_name' => 'Lookup Test Member',
            'award_id' => (int)$award->id,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'reason' => 'Gathering lookup policy test.',
            'contact_email' => 'lookup@test.example',
            'contact_number' => '555-555-0100',
            'status' => 'In Progress',
            'state' => 'Submitted',
            'state_date' => DateTime::now(),
            'call_into_court' => 'No',
            'court_availability' => 'Anytime',
        ]));

        $instanceId = $this->createWorkflowInstance();

        $runs = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns');
        $runs->saveOrFail($runs->newEntity([
            'recommendation_id' => (int)$recommendation->id,
            'approval_process_id' => (int)$process->id,
            'workflow_instance_id' => $instanceId,
            'status' => RecommendationApprovalRun::STATUS_IN_PROGRESS,
            'current_step_key' => 'approval',
            'current_step_label' => 'Approval',
            'started' => DateTime::now(),
        ]));

        return [(int)$recommendation->id, $instanceId];
    }

    private function createWorkflowInstance(): int
    {
        $definitions = $this->getTableLocator()->get('WorkflowDefinitions');
        $versions = $this->getTableLocator()->get('WorkflowVersions');
        $instances = $this->getTableLocator()->get('WorkflowInstances');

        $definition = $definitions->saveOrFail($definitions->newEntity([
            'name' => 'Gathering Lookup Workflow ' . uniqid('', true),
            'slug' => 'gathering-lookup-workflow-' . uniqid(),
            'trigger_type' => 'manual',
            'is_active' => true,
        ]));
        $version = $versions->saveOrFail($versions->newEntity([
            'workflow_definition_id' => $definition->id,
            'version_number' => 1,
            'definition' => [
                'nodes' => [
                    'trigger' => ['type' => 'trigger', 'outputs' => [['target' => 'end']]],
                    'end' => ['type' => 'end', 'outputs' => []],
                ],
            ],
            'status' => WorkflowVersion::STATUS_PUBLISHED,
        ]));
        $definition->current_version_id = $version->id;
        $definitions->saveOrFail($definition);

        $instance = $instances->saveOrFail($instances->newEntity([
            'workflow_definition_id' => (int)$definition->id,
            'workflow_version_id' => (int)$version->id,
            'status' => WorkflowInstance::STATUS_WAITING,
            'context_data' => [],
        ]));

        return (int)$instance->id;
    }

    private function createPendingApprovalFor(int $instanceId, int $approverMemberId): void
    {
        $logs = $this->getTableLocator()->get('WorkflowExecutionLogs');
        $log = $logs->saveOrFail($logs->newEntity([
            'workflow_instance_id' => $instanceId,
            'node_id' => 'approval',
            'node_type' => 'approval',
            'attempt_number' => 1,
            'status' => WorkflowExecutionLog::STATUS_WAITING,
        ]));

        $approvals = $this->getTableLocator()->get('WorkflowApprovals');
        $approvals->saveOrFail($approvals->newEntity([
            'workflow_instance_id' => $instanceId,
            'node_id' => 'approval',
            'execution_log_id' => (int)$log->id,
            'approver_type' => WorkflowApproval::APPROVER_TYPE_MEMBER,
            'approver_config' => ['member_id' => $approverMemberId],
            'required_count' => 1,
            'approved_count' => 0,
            'rejected_count' => 0,
            'status' => WorkflowApproval::STATUS_PENDING,
            'allow_parallel' => false,
            'version' => 1,
        ]));
    }

    private function lookupContextEntity(?int $recommendationId): Bestowal
    {
        $bestowal = $this->getTableLocator()->get('Awards.Bestowals')->newEmptyEntity();
        $bestowal->set('approval_context_recommendation_id', $recommendationId, ['guard' => false]);

        return $bestowal;
    }

    private function syntheticMember(int $id): Member
    {
        $member = new Member();
        $member->id = $id;
        $member->sca_name = 'Lookup Approver ' . $id;
        $member->status = Member::STATUS_ACTIVE;

        return $member;
    }
}
