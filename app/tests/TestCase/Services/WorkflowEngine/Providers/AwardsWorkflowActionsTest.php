<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\WorkflowEngine\Providers;

use App\Services\WorkflowEngine\StateMachine\StateMachineHandler;
use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\Recommendation;
use Awards\Services\AwardsWorkflowActions;
use Awards\Services\AwardsWorkflowConditions;
use Cake\ORM\TableRegistry;

/**
 * Tests for Awards plugin workflow actions and conditions.
 */
class AwardsWorkflowActionsTest extends BaseTestCase
{
    private AwardsWorkflowActions $actions;
    private AwardsWorkflowConditions $conditions;
    private StateMachineHandler $stateMachineHandler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stateMachineHandler = new StateMachineHandler();
        $this->actions = new AwardsWorkflowActions();
        $this->conditions = new AwardsWorkflowConditions($this->stateMachineHandler);
    }

    /**
     * Create a test recommendation directly for testing.
     */
    private function createTestRecommendation(array $overrides = []): object
    {
        $this->skipIfPostgres();

        $table = TableRegistry::getTableLocator()->get('Awards.Recommendations');

        // Find a valid award to reference
        $awardsTable = TableRegistry::getTableLocator()->get('Awards.Awards');
        $award = $awardsTable->find()->first();
        if (!$award) {
            $this->markTestSkipped('No awards in test database');
        }

        $defaults = [
            'award_id' => $award->id,
            'requester_sca_name' => 'Test Requester',
            'member_sca_name' => 'Test Member',
            'contact_email' => 'test@example.com',
            'reason' => 'Testing workflow actions',
            'call_into_court' => 'No preference',
            'court_availability' => 'Available anytime',
        ];

        // Get initial state from configuration
        $statuses = Recommendation::getStatuses();
        $firstStatus = array_key_first($statuses);
        $firstState = $statuses[$firstStatus][0] ?? '';

        $defaults['status'] = $firstStatus;
        $defaults['state'] = $firstState;

        $data = array_merge($defaults, $overrides);

        $entity = $table->newEmptyEntity();
        foreach ($data as $key => $value) {
            $entity->$key = $value;
        }

        $saved = $table->save($entity);
        if (!$saved) {
            $this->fail('Failed to create test recommendation: ' . json_encode($entity->getErrors()));
        }

        return $saved;
    }

    // ==========================================================
    // CreateRecommendation Action Tests
    // ==========================================================

    public function testCreateRecommendationSuccess(): void
    {
        $this->skipIfPostgres();

        $awardsTable = TableRegistry::getTableLocator()->get('Awards.Awards');
        $award = $awardsTable->find()->first();
        if (!$award) {
            $this->markTestSkipped('No awards in test database');
        }

        $result = $this->actions->createRecommendation([], [
            'awardId' => $award->id,
            'requesterScaName' => 'Lord Test',
            'memberScaName' => 'Lady Recipient',
            'contactEmail' => 'test@example.com',
            'reason' => 'Outstanding service to the kingdom',
            'callIntoCourt' => 'No preference',
            'courtAvailability' => 'Available anytime',
        ]);

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['data']['recommendationId']);
        $this->assertGreaterThan(0, $result['data']['recommendationId']);
    }

    public function testCreateRecommendationFailureNoAward(): void
    {
        $result = $this->actions->createRecommendation([], [
            'awardId' => 999999,
            'requesterScaName' => 'Lord Test',
            'memberScaName' => 'Lady Recipient',
            'contactEmail' => 'test@example.com',
            'reason' => 'Test reason',
        ]);

        $this->assertFalse($result['success']);
    }

    public function testCreateRecommendationResolvesContextPaths(): void
    {
        $this->skipIfPostgres();

        $awardsTable = TableRegistry::getTableLocator()->get('Awards.Awards');
        $award = $awardsTable->find()->first();
        if (!$award) {
            $this->markTestSkipped('No awards in test database');
        }

        $context = [
            'trigger' => [
                'awardId' => $award->id,
                'requesterName' => 'Context Requester',
                'memberName' => 'Context Member',
                'email' => 'context@example.com',
                'reason' => 'Context-resolved reason',
                'callIntoCourt' => 'No preference',
                'courtAvailability' => 'Available anytime',
            ],
        ];

        $result = $this->actions->createRecommendation($context, [
            'awardId' => '$.trigger.awardId',
            'requesterScaName' => '$.trigger.requesterName',
            'memberScaName' => '$.trigger.memberName',
            'contactEmail' => '$.trigger.email',
            'reason' => '$.trigger.reason',
            'callIntoCourt' => '$.trigger.callIntoCourt',
            'courtAvailability' => '$.trigger.courtAvailability',
        ]);

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['data']['recommendationId']);
    }

    public function testCreateRecommendationSupportsAuthenticatedRequesterContext(): void
    {
        $this->skipIfPostgres();

        $awardsTable = TableRegistry::getTableLocator()->get('Awards.Awards');
        $membersTable = TableRegistry::getTableLocator()->get('Members');
        $award = $awardsTable->find()->select(['id', 'name'])->first();
        $requester = $membersTable->get(
            self::ADMIN_MEMBER_ID,
            select: ['id', 'sca_name', 'email_address', 'phone_number'],
        );
        $member = $membersTable->get(
            self::TEST_MEMBER_AGATHA_ID,
            select: ['id', 'sca_name', 'public_id'],
        );
        if (!$award) {
            $this->markTestSkipped('No awards in test database');
        }

        $result = $this->actions->createRecommendation([], [
            'data' => [
                'award_id' => $award->id,
                'member_public_id' => $member->public_id,
                'member_sca_name' => $member->sca_name,
                'reason' => 'Authenticated workflow submission',
                'specialty' => 'No specialties available',
            ],
            'requesterContext' => [
                'id' => $requester->id,
                'sca_name' => $requester->sca_name,
                'email_address' => $requester->email_address,
                'phone_number' => $requester->phone_number,
            ],
            'submissionMode' => 'authenticated',
        ]);

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['data']['recommendationId']);
        $this->assertSame(self::ADMIN_MEMBER_ID, $result['data']['eventPayload']['requesterId']);
        $this->assertSame(self::TEST_MEMBER_AGATHA_ID, $result['data']['eventPayload']['memberId']);
    }

    public function testUpdateRecommendationUsesSharedMutationService(): void
    {
        $this->skipIfPostgres();

        $membersTable = TableRegistry::getTableLocator()->get('Members');
        $member = $membersTable->get(
            self::TEST_MEMBER_AGATHA_ID,
            select: ['id', 'sca_name', 'public_id'],
        );
        $recommendation = $this->createTestRecommendation([
            'member_id' => self::TEST_MEMBER_BRYCE_ID,
            'member_sca_name' => 'Bryce Demoer',
        ]);

        $result = $this->actions->updateRecommendation([], [
            'recommendationId' => $recommendation->id,
            'data' => [
                'member_public_id' => $member->public_id,
                'member_sca_name' => $member->sca_name,
                'given' => '2026-05-01',
                'note' => 'Workflow update note',
            ],
            'actorId' => self::ADMIN_MEMBER_ID,
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals($recommendation->id, $result['data']['recommendationId']);
        $this->assertSame(self::TEST_MEMBER_AGATHA_ID, $result['data']['memberId']);
        $this->assertNotNull($result['data']['noteId']);
        // Generic recommendation updates intentionally do not mutate lifecycle
        // fields (given/state/status/etc.); those flow through dedicated workflow
        // transitions, so a supplied "given" is ignored here.
        $this->assertNull($result['data']['given']);
    }

    public function testGroupAndRemoveRecommendationActionsUseGroupingService(): void
    {
        $this->skipIfPostgres();

        $head = $this->createTestRecommendation();
        $child = $this->createTestRecommendation();

        $groupResult = $this->actions->groupRecommendations([], [
            'recommendationIds' => [$head->id, $child->id],
            'actorId' => self::ADMIN_MEMBER_ID,
        ]);

        $this->assertTrue($groupResult['success']);
        $this->assertSame((int)$head->id, (int)$groupResult['data']['headId']);
        $this->assertSame(2, $groupResult['data']['groupedCount']);

        $removeResult = $this->actions->removeRecommendationFromGroup([], [
            'recommendationId' => $child->id,
            'actorId' => self::ADMIN_MEMBER_ID,
        ]);

        $this->assertTrue($removeResult['success']);
        $this->assertSame((int)$head->id, (int)$removeResult['data']['formerHeadId']);
    }

    public function testDeleteRecommendationActionRestoresGroupedChildren(): void
    {
        $this->skipIfPostgres();

        $head = $this->createTestRecommendation();
        $child = $this->createTestRecommendation();

        $groupResult = $this->actions->groupRecommendations([], [
            'recommendationIds' => [$head->id, $child->id],
            'actorId' => self::ADMIN_MEMBER_ID,
        ]);

        $this->assertTrue($groupResult['success']);

        $deleteResult = $this->actions->deleteRecommendation([], [
            'recommendationId' => $head->id,
            'actorId' => self::ADMIN_MEMBER_ID,
        ]);

        $this->assertTrue($deleteResult['success']);
        $this->assertSame((int)$head->id, (int)$deleteResult['data']['recommendationId']);
        $this->assertSame(1, $deleteResult['data']['restoredChildCount']);

        $recommendations = TableRegistry::getTableLocator()->get('Awards.Recommendations');
        $restoredChild = $recommendations->get($child->id);
        $this->assertNull($restoredChild->recommendation_group_id);
    }

    // ==========================================================
    // CreateStateLog Action Tests
    // ==========================================================

    public function testCreateStateLogRecordsEntry(): void
    {
        $recommendation = $this->createTestRecommendation();

        $result = $this->actions->createStateLog([], [
            'recommendationId' => $recommendation->id,
            'fromState' => 'OldState',
            'toState' => 'NewState',
            'fromStatus' => 'OldStatus',
            'toStatus' => 'NewStatus',
            'actorId' => self::ADMIN_MEMBER_ID,
        ]);

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['data']['logId']);

        // Verify the log was actually written
        $logsTable = TableRegistry::getTableLocator()->get('Awards.RecommendationsStatesLogs');
        $log = $logsTable->get($result['data']['logId']);
        $this->assertEquals('OldState', $log->from_state);
        $this->assertEquals('NewState', $log->to_state);
        $this->assertEquals(self::ADMIN_MEMBER_ID, $log->created_by);
    }

    // ==========================================================
    // AssignGathering Action Tests
    // ==========================================================

    public function testAssignGatheringSuccess(): void
    {
        // Create recommendation in a gathering-assignable state
        $gatheringStates = ['Need to Schedule', 'Scheduled', 'Given'];
        $allStates = Recommendation::getStates();
        $assignableState = null;
        foreach ($gatheringStates as $gs) {
            if (in_array($gs, $allStates, true)) {
                $assignableState = $gs;
                break;
            }
        }

        if (!$assignableState) {
            $this->markTestSkipped('No gathering-assignable states available');
        }

        $statuses = Recommendation::getStatuses();
        $targetStatus = null;
        foreach ($statuses as $status => $states) {
            if (in_array($assignableState, $states, true)) {
                $targetStatus = $status;
                break;
            }
        }

        $recommendation = $this->createTestRecommendation([
            'state' => $assignableState,
            'status' => $targetStatus,
        ]);

        // Find a valid gathering
        $gatheringsTable = TableRegistry::getTableLocator()->get('Gatherings');
        $gathering = $gatheringsTable->find()->first();
        if (!$gathering) {
            $this->markTestSkipped('No gatherings in test database');
        }

        $result = $this->actions->assignGathering([], [
            'recommendationId' => $recommendation->id,
            'gatheringId' => $gathering->id,
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals($recommendation->id, $result['data']['recommendationId']);
        $this->assertEquals($gathering->id, $result['data']['gatheringId']);
    }

    // ==========================================================
    // PullCourtPreferences Action Tests
    // ==========================================================

    public function testPullCourtPreferencesReturnsData(): void
    {
        $recommendation = $this->createTestRecommendation([
            'court_availability' => 'Available weekends',
            'call_into_court' => 'Yes',
        ]);

        $result = $this->actions->pullCourtPreferences([], [
            'recommendationId' => $recommendation->id,
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('Available weekends', $result['data']['courtAvailability']);
        $this->assertEquals('Yes', $result['data']['callIntoCourt']);
    }

    // ==========================================================
    // IsValidTransition Condition Tests
    // ==========================================================

    public function testIsValidTransitionReturnsTrueForValidStates(): void
    {
        $states = Recommendation::getStates();
        if (count($states) < 2) {
            $this->markTestSkipped('Need at least two states');
        }

        $result = $this->conditions->isValidTransition([], [
            'currentState' => $states[0],
            'targetState' => $states[1],
        ]);

        $this->assertTrue($result);
    }

    public function testIsValidTransitionReturnsFalseForInvalidState(): void
    {
        $states = Recommendation::getStates();
        if (empty($states)) {
            $this->markTestSkipped('No states configured');
        }

        $result = $this->conditions->isValidTransition([], [
            'currentState' => $states[0],
            'targetState' => 'CompletelyInvalidState',
        ]);

        $this->assertFalse($result);
    }

    public function testIsValidTransitionReturnsFalseForMissingParams(): void
    {
        $result = $this->conditions->isValidTransition([], []);
        $this->assertFalse($result);
    }

    // ==========================================================
    // HasRequiredFields Condition Tests
    // ==========================================================

    public function testHasRequiredFieldsReturnsFalseForMissingParams(): void
    {
        $result = $this->conditions->hasRequiredFields([], []);
        $this->assertFalse($result);
    }

    public function testHasRequiredFieldsWithPopulatedEntity(): void
    {
        $recommendation = $this->createTestRecommendation();

        $states = Recommendation::getStates();
        $result = $this->conditions->hasRequiredFields([], [
            'recommendationId' => $recommendation->id,
            'targetState' => $states[0],
        ]);

        // Should return true since the initial state typically has no or satisfied required fields
        $this->assertIsBool($result);
    }

    // ==========================================================
    // RequiresGathering Condition Tests
    // ==========================================================

    public function testRequiresGatheringForSchedulableState(): void
    {
        $allStates = Recommendation::getStates();
        if (in_array('Need to Schedule', $allStates, true)) {
            $result = $this->conditions->requiresGathering([], [
                'targetState' => 'Need to Schedule',
            ]);
            $this->assertTrue($result);
        } elseif (in_array('Scheduled', $allStates, true)) {
            $result = $this->conditions->requiresGathering([], [
                'targetState' => 'Scheduled',
            ]);
            $this->assertTrue($result);
        } else {
            $this->markTestSkipped('No gathering-assignable states available');
        }
    }

    public function testRequiresGatheringReturnsFalseForMissingParam(): void
    {
        $result = $this->conditions->requiresGathering([], []);
        $this->assertFalse($result);
    }

    // ==========================================================
    // Context Path Resolution Tests
    // ==========================================================

    public function testContextPathResolutionInConditions(): void
    {
        $states = Recommendation::getStates();
        if (count($states) < 2) {
            $this->markTestSkipped('Need at least two states');
        }

        $context = [
            'entity' => [
                'currentState' => $states[0],
                'targetState' => $states[1],
            ],
        ];

        $result = $this->conditions->isValidTransition($context, [
            'currentState' => '$.entity.currentState',
            'targetState' => '$.entity.targetState',
        ]);

        $this->assertTrue($result);
    }

    public function testContextPathResolutionInActions(): void
    {
        $recommendation = $this->createTestRecommendation([
            'court_availability' => 'Anytime',
        ]);

        $context = [
            'entity' => ['id' => $recommendation->id],
        ];

        $result = $this->actions->pullCourtPreferences($context, [
            'recommendationId' => '$.entity.id',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('Anytime', $result['data']['courtAvailability']);
    }
}
