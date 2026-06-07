<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\Model\Entity\WorkflowInstance;
use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\ApprovalProcessStep;
use Awards\Model\Entity\Recommendation;
use Awards\Model\Entity\RecommendationApprovalRun;
use Awards\Services\RecommendationUpdateService;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;

class RecommendationUpdateServiceTest extends BaseTestCase
{
    protected RecommendationUpdateService $service;

    protected $Recommendations;

    protected $Members;

    protected $Awards;

    protected $Notes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();

        $locator = TableRegistry::getTableLocator();
        $this->Recommendations = $locator->get('Awards.Recommendations');
        $this->Members = $locator->get('Members');
        $this->Awards = $locator->get('Awards.Awards');
        $this->Notes = $this->Recommendations->Notes;
        $this->service = new RecommendationUpdateService();
    }

    public function testUpdateHydratesChangedMemberCreatesNoteAndIgnoresLegacyEditFields(): void
    {
        $originalGatheringIds = array_slice($this->getGatheringIds(), 0, 2);
        $existing = $this->createRecommendation(self::TEST_MEMBER_BRYCE_ID, $originalGatheringIds);
        $member = $this->Members->get(
            self::TEST_MEMBER_AGATHA_ID,
            select: ['id', 'sca_name', 'branch_id', 'public_id'],
        );
        $gatheringIds = $this->getGatheringIds();

        $result = $this->service->update(
            $this->Recommendations,
            $existing,
            [
                'member_sca_name' => $member->sca_name,
                'member_public_id' => $member->public_id,
                'specialty' => 'No specialties available',
                'gatherings' => ['_ids' => array_slice($gatheringIds, 2, 2)],
                'given' => '2026-02-03',
                'state' => 'No Action',
                'status' => 'Closed',
                'close_reason' => 'Legacy close reason should be ignored',
                'note' => 'Updated through service',
            ],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertTrue($result['success']);

        $saved = $this->Recommendations->get($existing->id, contain: ['Gatherings']);
        $this->assertSame(self::TEST_MEMBER_AGATHA_ID, (int)$saved->member_id);
        $this->assertSame($member->branch_id, (int)$saved->branch_id);
        $this->assertSame('With Notice', $saved->call_into_court);
        $this->assertSame('Evening', $saved->court_availability);
        $this->assertSame('Bryce Demoer', $saved->person_to_notify);
        $this->assertNull($saved->specialty);
        $this->assertNull($saved->given);
        $this->assertSame($originalGatheringIds, $result['output']['gatheringIds']);
        $this->assertNotSame('No Action', $saved->state);
        $this->assertNotSame('Closed', $saved->status);
        $this->assertNull($saved->close_reason);
        $this->assertSame(self::TEST_MEMBER_BRYCE_ID, $result['output']['previousMemberId']);
        $this->assertTrue($result['output']['memberChanged']);
        $this->assertNull($result['output']['given']);

        $note = $this->Notes->find()
            ->where([
                'entity_id' => $existing->id,
                'entity_type' => 'Awards.Recommendations',
                'subject' => 'Recommendation Updated',
            ])
            ->firstOrFail();
        $this->assertSame('Updated through service', $note->body);
        $this->assertSame(self::ADMIN_MEMBER_ID, (int)$note->author_id);
        $this->assertSame((int)$note->id, (int)$result['output']['noteId']);
        $this->assertSame('Updated through service', $result['output']['noteBody']);
    }

    public function testUpdateClearsMemberFieldsWhenRecommendationIsDetachedFromMember(): void
    {
        $existing = $this->createRecommendation(self::TEST_MEMBER_AGATHA_ID, array_slice($this->getGatheringIds(), 0, 2));

        $result = $this->service->update(
            $this->Recommendations,
            $existing,
            [
                'member_id' => 0,
                'member_sca_name' => 'Unknown Candidate',
                'branch_id' => self::TEST_BRANCH_STARGATE_ID,
            ],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertTrue($result['success']);

        $saved = $this->Recommendations->get($existing->id, contain: ['Gatherings']);
        $this->assertNull($saved->member_id);
        $this->assertSame(self::TEST_BRANCH_STARGATE_ID, (int)$saved->branch_id);
        $this->assertSame('Not Set', $saved->call_into_court);
        $this->assertSame('Not Set', $saved->court_availability);
        $this->assertSame('', $saved->person_to_notify);
        $this->assertNull($saved->given);
        $this->assertSame(array_slice($this->getGatheringIds(), 0, 2), $result['output']['gatheringIds']);
        $this->assertFalse($result['output']['notFound']);
    }

    public function testAwardChangeWithDifferentActiveApprovalProcessRequiresConfirmation(): void
    {
        $currentProcess = $this->createApprovalProcess('Current Process');
        $newProcess = $this->createApprovalProcess('New Process');
        $currentAward = $this->createAwardWithApprovalProcess((int)$currentProcess->id);
        $newAward = $this->createAwardWithApprovalProcess((int)$newProcess->id);
        $existing = $this->createRecommendation(
            self::TEST_MEMBER_AGATHA_ID,
            [],
            ['award_id' => (int)$currentAward->id],
        );
        $run = $this->createActiveApprovalRun((int)$existing->id, (int)$currentProcess->id);

        $result = $this->service->update(
            $this->Recommendations,
            $existing,
            ['award_id' => (int)$newAward->id],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertFalse($result['success']);
        $this->assertSame('approval_workflow_restart_confirmation_required', $result['errorCode']);
        $freshRun = $this->getTableLocator()
            ->get('Awards.RecommendationApprovalRuns')
            ->get((int)$run->id);
        $this->assertSame(RecommendationApprovalRun::STATUS_IN_PROGRESS, $freshRun->status);
        $this->assertSame((int)$currentAward->id, (int)$this->Recommendations->get((int)$existing->id)->award_id);
    }

    public function testConfirmedAwardChangeCancelsActiveRunAndReturnsRestartEvent(): void
    {
        $currentProcess = $this->createApprovalProcess('Current Process');
        $newProcess = $this->createApprovalProcess('New Process');
        $currentAward = $this->createAwardWithApprovalProcess((int)$currentProcess->id);
        $newAward = $this->createAwardWithApprovalProcess((int)$newProcess->id);
        $existing = $this->createRecommendation(
            self::TEST_MEMBER_AGATHA_ID,
            [],
            ['award_id' => (int)$currentAward->id],
        );
        $run = $this->createActiveApprovalRun((int)$existing->id, (int)$currentProcess->id);

        $result = $this->service->update(
            $this->Recommendations,
            $existing,
            [
                'award_id' => (int)$newAward->id,
                'approval_workflow_restart_confirmed' => '1',
            ],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertTrue($result['success'], $result['message'] ?? json_encode($result));
        $this->assertSame('Awards.ExistingRecommendationApprovalRequested', $result['eventName']);
        $this->assertSame((int)$existing->id, $result['eventPayload']['recommendationId']);
        $this->assertSame([0 => (int)$run->id], $result['eventPayload']['cancelledRunIds']);
        $this->assertSame(
            RecommendationApprovalRun::TERMINAL_REASON_AWARD_CHANGED,
            $result['eventPayload']['restartReason'],
        );

        $savedRun = $this->getTableLocator()
            ->get('Awards.RecommendationApprovalRuns')
            ->get((int)$run->id);
        $this->assertSame(RecommendationApprovalRun::STATUS_CANCELLED, $savedRun->status);
        $this->assertSame(RecommendationApprovalRun::TERMINAL_REASON_AWARD_CHANGED, $savedRun->terminal_reason);
        $this->assertSame((int)$newAward->id, (int)$this->Recommendations->get((int)$existing->id)->award_id);
    }

    public function testAwardChangeWithSameActiveApprovalProcessLeavesRunAlone(): void
    {
        $process = $this->createApprovalProcess('Shared Process');
        $currentAward = $this->createAwardWithApprovalProcess((int)$process->id);
        $newAward = $this->createAwardWithApprovalProcess((int)$process->id);
        $existing = $this->createRecommendation(
            self::TEST_MEMBER_AGATHA_ID,
            [],
            ['award_id' => (int)$currentAward->id],
        );
        $run = $this->createActiveApprovalRun((int)$existing->id, (int)$process->id);

        $result = $this->service->update(
            $this->Recommendations,
            $existing,
            ['award_id' => (int)$newAward->id],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertTrue($result['success'], $result['message'] ?? json_encode($result));
        $this->assertNull($result['eventName']);
        $freshRun = $this->getTableLocator()
            ->get('Awards.RecommendationApprovalRuns')
            ->get((int)$run->id);
        $this->assertSame(RecommendationApprovalRun::STATUS_IN_PROGRESS, $freshRun->status);
        $this->assertSame((int)$newAward->id, (int)$this->Recommendations->get((int)$existing->id)->award_id);
    }

    /**
     * @param array<int> $gatheringIds
     */
    private function createRecommendation(int $memberId, array $gatheringIds, array $overrides = []): Recommendation
    {
        $member = $this->Members->get($memberId, select: ['id', 'sca_name', 'branch_id']);
        $requester = $this->Members->get(
            self::ADMIN_MEMBER_ID,
            select: ['id', 'sca_name', 'email_address', 'phone_number'],
        );
        $award = $this->Awards->find()->select(['id'])->firstOrFail();
        $statuses = Recommendation::getStatuses();
        $status = array_key_first($statuses);
        $state = $statuses[$status][0];

        $recommendation = $this->Recommendations->newEntity(
            array_merge([
                'award_id' => $award->id,
                'requester_id' => $requester->id,
                'requester_sca_name' => $requester->sca_name,
                'member_id' => $member->id,
                'member_sca_name' => $member->sca_name,
                'branch_id' => $member->branch_id,
                'contact_email' => $requester->email_address,
                'contact_number' => $requester->phone_number,
                'reason' => 'Original recommendation body',
                'status' => $status,
                'state' => $state,
                'state_date' => DateTime::now(),
                'not_found' => false,
                'call_into_court' => 'Not Set',
                'court_availability' => 'Not Set',
                'person_to_notify' => '',
                'gatherings' => ['_ids' => $gatheringIds],
            ], $overrides),
            ['associated' => ['Gatherings']],
        );

        return $this->Recommendations->saveOrFail(
            $recommendation,
            ['associated' => ['Gatherings']],
        );
    }

    private function createApprovalProcess(string $name)
    {
        $processes = $this->getTableLocator()->get('Awards.ApprovalProcesses');

        return $processes->saveOrFail($processes->newEntity([
            'name' => $name . ' ' . uniqid('', true),
            'is_active' => true,
            'approval_process_steps' => [[
                'step_key' => 'approval',
                'label' => 'Approval',
                'sequence' => 1,
                'step_type' => ApprovalProcessStep::STEP_TYPE_APPROVAL,
                'approver_type' => ApprovalProcessStep::APPROVER_TYPE_MEMBER,
                'approver_source_id' => self::ADMIN_MEMBER_ID,
                'branch_mode' => ApprovalProcessStep::BRANCH_MODE_AWARD,
                'threshold_mode' => ApprovalProcessStep::THRESHOLD_ANY,
                'on_reject' => ApprovalProcessStep::ACTION_RETURN_PREVIOUS,
                'on_request_changes' => ApprovalProcessStep::ACTION_RETURN_PREVIOUS,
                'retain_read_visibility' => true,
            ]],
        ], ['associated' => ['ApprovalProcessSteps']]));
    }

    private function createAwardWithApprovalProcess(int $approvalProcessId)
    {
        return $this->Awards->saveOrFail($this->Awards->newEntity([
            'name' => 'Workflow Change Award ' . uniqid('', true),
            'abbreviation' => strtoupper(substr(md5(uniqid('', true)), 0, 8)),
            'domain_id' => 2,
            'level_id' => 1,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'approval_process_id' => $approvalProcessId,
            'is_active' => true,
        ]));
    }

    private function createActiveApprovalRun(int $recommendationId, int $approvalProcessId)
    {
        $runs = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns');

        return $runs->saveOrFail($runs->newEntity([
            'recommendation_id' => $recommendationId,
            'approval_process_id' => $approvalProcessId,
            'workflow_instance_id' => $this->createWorkflowInstance(),
            'status' => RecommendationApprovalRun::STATUS_IN_PROGRESS,
            'current_step_key' => 'approval',
            'current_step_label' => 'Approval',
            'started' => DateTime::now(),
        ]));
    }

    private function createWorkflowInstance(): int
    {
        $definitions = $this->getTableLocator()->get('WorkflowDefinitions');
        $versions = $this->getTableLocator()->get('WorkflowVersions');
        $instances = $this->getTableLocator()->get('WorkflowInstances');

        $definition = $definitions->saveOrFail($definitions->newEntity([
            'name' => 'Recommendation Update Workflow ' . uniqid('', true),
            'slug' => 'recommendation-update-workflow-' . uniqid(),
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
            'status' => 'published',
        ]));

        $definition->current_version_id = $version->id;
        $definitions->saveOrFail($definition);

        $instance = $instances->saveOrFail($instances->newEntity([
            'workflow_definition_id' => $definition->id,
            'workflow_version_id' => $version->id,
            'status' => WorkflowInstance::STATUS_WAITING,
        ]));

        return (int)$instance->id;
    }

    /**
     * @return array<int>
     */
    private function getGatheringIds(): array
    {
        return $this->getTableLocator()
            ->get('Gatherings')
            ->find()
            ->select(['id'])
            ->limit(4)
            ->all()
            ->extract('id')
            ->map(fn($id) => (int)$id)
            ->toList();
    }

    private function stateForStatus(string $status, array $exclude = []): string
    {
        $states = Recommendation::getStatuses()[$status] ?? [];
        foreach ($states as $state) {
            if (!in_array($state, $exclude, true)) {
                return $state;
            }
        }

        $this->markTestSkipped("No usable {$status} state available");
    }

    private function differentNonLinkedState(string $excludeState): string
    {
        foreach (Recommendation::getStates() as $state) {
            if (!in_array($state, ['Linked', 'Linked - Closed', $excludeState], true)) {
                return $state;
            }
        }

        $this->markTestSkipped('Need a non-linked state for grouping tests');
    }
}
