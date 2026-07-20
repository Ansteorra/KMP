<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\Recommendation;
use Awards\Services\RecommendationStateLogService;
use Cake\ORM\Table;

class RecommendationStateLogServiceTest extends BaseTestCase
{
    private RecommendationStateLogService $service;
    private Table $recommendationsTable;
    private Table $stateLogsTable;
    private int $awardId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();

        $this->recommendationsTable = $this->getTableLocator()->get('Awards.Recommendations');
        $this->stateLogsTable = $this->getTableLocator()->get('Awards.RecommendationsStatesLogs');
        $this->service = new RecommendationStateLogService($this->stateLogsTable);
        $this->awardId = $this->getFirstAwardId();
    }

    public function testCreateLogPersistsExplicitTransition(): void
    {
        $recommendation = $this->createTestRecommendation();

        $log = $this->service->createLog(
            (int)$recommendation->id,
            'Submitted',
            'Linked',
            'In Progress',
            'In Progress',
            self::ADMIN_MEMBER_ID,
        );

        $stored = $this->stateLogsTable->get((int)$log->id);
        $this->assertSame((int)$recommendation->id, (int)$stored->recommendation_id);
        $this->assertSame('Submitted', $stored->from_state);
        $this->assertSame('Linked', $stored->to_state);
        $this->assertSame('In Progress', $stored->from_status);
        $this->assertSame('In Progress', $stored->to_status);
        $this->assertSame(self::ADMIN_MEMBER_ID, (int)$stored->created_by);
    }

    public function testLogEntityStateChangeUsesEntityHistoryAndInferredStatuses(): void
    {
        $originState = $this->stateForStatus('In Progress', ['Linked']);
        $targetState = $this->stateForStatus('Closed', ['Linked - Closed']);
        $recommendation = $this->createTestRecommendation(['state' => $originState]);

        $recommendation->modified_by = self::ADMIN_MEMBER_ID;
        $recommendation->state = $targetState;

        $log = $this->service->logEntityStateChange($recommendation);

        $this->assertNotNull($log);
        $stored = $this->stateLogsTable->get((int)$log->id);
        $this->assertSame($originState, $stored->from_state);
        $this->assertSame($targetState, $stored->to_state);
        $this->assertSame($this->statusForState($originState), $stored->from_status);
        $this->assertSame($this->statusForState($targetState), $stored->to_status);
        $this->assertSame(self::ADMIN_MEMBER_ID, (int)$stored->created_by);
    }

    private function createTestRecommendation(array $overrides = []): Recommendation
    {
        $state = (string)($overrides['state'] ?? $this->stateForStatus('In Progress', ['Linked']));
        $status = (string)($overrides['status'] ?? $this->statusForState($state));

        $data = array_merge([
            'award_id' => $this->awardId,
            'requester_id' => self::ADMIN_MEMBER_ID,
            'member_id' => self::ADMIN_MEMBER_ID,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'requester_sca_name' => 'Test Requester',
            'member_sca_name' => 'Test Member',
            'contact_email' => 'test@example.com',
            'reason' => 'Testing recommendation state logs',
            'call_into_court' => 'No preference',
            'court_availability' => 'Available anytime',
        ], $overrides);

        unset($data['state'], $data['status']);

        /** @var \Awards\Model\Entity\Recommendation $entity */
        $entity = $this->recommendationsTable->newEmptyEntity();
        foreach ($data as $field => $value) {
            $entity->$field = $value;
        }
        $entity->status = $status;
        $entity->state = $state;

        return $this->recommendationsTable->saveOrFail($entity);
    }

    private function getFirstAwardId(): int
    {
        $awardsTable = $this->getTableLocator()->get('Awards.Awards');
        $award = $awardsTable->find()->select(['id'])->first();
        if ($award === null) {
            $this->markTestSkipped('No awards in test database');
        }

        return (int)$award->id;
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

    private function statusForState(string $state): string
    {
        foreach (Recommendation::getStatuses() as $status => $states) {
            if (in_array($state, $states, true)) {
                return (string)$status;
            }
        }

        $this->fail("Unknown status for state {$state}");
    }
}
