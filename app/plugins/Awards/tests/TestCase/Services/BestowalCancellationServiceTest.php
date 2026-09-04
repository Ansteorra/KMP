<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\Model\Entity\ActionItem;
use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\Recommendation;
use Awards\Model\Entity\RecommendationApprovalRun;
use Awards\Services\BestowalCancellationService;
use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Cake\I18n\DateTime;
use Cake\ORM\Table;
use Closure;

/**
 * Covers the atomic cancellation, cleanup, and reconsideration handoff.
 */
class BestowalCancellationServiceTest extends BaseTestCase
{
    private Table $bestowals;
    private Table $recommendations;
    private Table $actionItems;
    private ?Closure $workflowListener = null;

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $workflowEvents = [];

    /**
     * Register the workflow event collector used by each cancellation test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->bestowals = $this->getTableLocator()->get('Awards.Bestowals');
        $this->recommendations = $this->getTableLocator()->get('Awards.Recommendations');
        $this->actionItems = $this->getTableLocator()->get('ActionItems');
        $this->workflowListener = function (EventInterface $event): void {
            $this->workflowEvents[] = $event->getData();
        };
        EventManager::instance()->on('Workflow.trigger', $this->workflowListener);
    }

    /**
     * Detach only this test case's workflow event collector.
     */
    protected function tearDown(): void
    {
        if ($this->workflowListener !== null) {
            EventManager::instance()->off('Workflow.trigger', $this->workflowListener);
            $this->workflowListener = null;
        }

        parent::tearDown();
    }

    /**
     * Cancellation clears operational work and restarts recommendation approval.
     */
    public function testCancelResetsRecommendationCancelsOpenTodosAndRequestsFreshApproval(): void
    {
        $recommendation = $this->makeRecommendation();
        $bestowal = $this->makeBestowal($recommendation);
        $openTodo = $this->makeTodo((int)$bestowal->id, 'Prepare scroll', ActionItem::STATUS_OPEN);
        $completedTodo = $this->makeTodo(
            (int)$bestowal->id,
            'Confirm insignia',
            ActionItem::STATUS_COMPLETED,
        );

        $result = (new BestowalCancellationService())->cancel(
            (int)$bestowal->id,
            self::ADMIN_MEMBER_ID,
            'The award requires complete reconsideration.',
        );

        $this->assertTrue($result['success'], $result['error'] ?? json_encode($result));
        $this->assertSame([(int)$openTodo->id], $result['data']['cancelledTodoIds']);
        $this->assertSame([(int)$recommendation->id], $result['data']['approvalScopeRecommendationIds']);
        $this->assertSame(BestowalCancellationService::RECONSIDERATION_STATE, $result['data']['unwindState']);

        $savedBestowal = $this->bestowals->get($bestowal->id);
        $this->assertSame(Bestowal::LIFECYCLE_CANCELLED, $savedBestowal->lifecycle_status);
        $this->assertSame('The award requires complete reconsideration.', $savedBestowal->close_reason);

        $savedRecommendation = $this->recommendations->get($recommendation->id);
        $this->assertSame('Submitted', $savedRecommendation->state);
        $this->assertSame('In Progress', $savedRecommendation->status);
        $this->assertNull($savedRecommendation->bestowal_id);
        $this->assertNull($savedRecommendation->gathering_id);
        $this->assertNull($savedRecommendation->given);
        $this->assertNull($savedRecommendation->close_reason);

        $this->assertSame(ActionItem::STATUS_CANCELLED, $this->actionItems->get($openTodo->id)->status);
        $this->assertSame(ActionItem::STATUS_COMPLETED, $this->actionItems->get($completedTodo->id)->status);
        $todoLog = $this->getTableLocator()->get('ActionItemLogs')->find()
            ->where(['action_item_id' => (int)$openTodo->id])
            ->orderByDesc('id')
            ->firstOrFail();
        $this->assertSame(BestowalCancellationService::TODO_CANCELLATION_NOTE, $todoLog->note);

        $stateLog = $this->getTableLocator()->get('Awards.RecommendationsStatesLogs')->find()
            ->where(['recommendation_id' => (int)$recommendation->id])
            ->orderByDesc('id')
            ->firstOrFail();
        $this->assertSame('Scheduled', $stateLog->from_state);
        $this->assertSame('Submitted', $stateLog->to_state);
        $this->assertSame('To Give', $stateLog->from_status);
        $this->assertSame('In Progress', $stateLog->to_status);

        $restartEvents = array_values(array_filter(
            $this->workflowEvents,
            static fn(array $event): bool => ($event['eventName'] ?? null)
                === 'Awards.ExistingRecommendationApprovalRequested',
        ));
        $this->assertCount(1, $restartEvents);
        $this->assertSame((int)$recommendation->id, $restartEvents[0]['eventData']['recommendationId']);
        $this->assertSame(self::ADMIN_MEMBER_ID, $restartEvents[0]['eventData']['actorId']);
        $this->assertSame(
            RecommendationApprovalRun::TERMINAL_REASON_BESTOWAL_CANCELLED,
            $restartEvents[0]['eventData']['rehydrationReason'],
        );
        $this->assertArrayNotHasKey('rehydratedFromRunId', $restartEvents[0]['eventData']);
    }

    /**
     * Create a bestowal-managed recommendation with stale projection fields.
     */
    private function makeRecommendation(): Recommendation
    {
        $award = $this->getTableLocator()->get('Awards.Awards')->find()->select(['id'])->firstOrFail();
        $gathering = $this->getTableLocator()->get('Gatherings')->find()->select(['id'])->firstOrFail();
        $recommendation = $this->recommendations->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'requester_id' => self::ADMIN_MEMBER_ID,
            'award_id' => (int)$award->id,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'reason' => 'Cancellation reconsideration coverage',
            'requester_sca_name' => 'Admin von Admin',
            'member_sca_name' => 'Admin von Admin',
            'contact_email' => 'admin@test.com',
            'status' => 'To Give',
            'state' => 'Scheduled',
            'state_date' => DateTime::now(),
            'gathering_id' => (int)$gathering->id,
            'given' => DateTime::now(),
            'close_reason' => 'Stale recommendation closure',
            'call_into_court' => 'Not Set',
            'court_availability' => 'Not Set',
        ]);

        return $this->recommendations->saveOrFail($recommendation);
    }

    /**
     * Link a recommendation to a new open bestowal.
     */
    private function makeBestowal(Recommendation $recommendation): Bestowal
    {
        $bestowal = $this->bestowals->saveOrFail($this->bestowals->newEntity([
            'member_id' => $recommendation->member_id,
            'member_sca_name' => $recommendation->member_sca_name,
            'award_id' => $recommendation->award_id,
            'primary_recommendation_id' => $recommendation->id,
            'lifecycle_status' => Bestowal::LIFECYCLE_OPEN,
            'source' => Bestowal::SOURCE_RECOMMENDATION,
            'stack_rank' => 0,
        ]));
        $joins = $this->getTableLocator()->get('Awards.BestowalRecommendations');
        $joins->saveOrFail($joins->newEntity([
            'bestowal_id' => $bestowal->id,
            'recommendation_id' => $recommendation->id,
        ]));
        $recommendation->bestowal_id = (int)$bestowal->id;
        $this->recommendations->saveOrFail($recommendation, ['systemSync' => true]);

        return $bestowal;
    }

    /**
     * Create a bestowal action item in the requested status.
     */
    private function makeTodo(int $bestowalId, string $title, string $status): ActionItem
    {
        return $this->actionItems->saveOrFail($this->actionItems->newEntity([
            'entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE,
            'entity_id' => $bestowalId,
            'title' => $title,
            'assignee_type' => ActionItem::ASSIGNEE_TYPE_MEMBER,
            'assignee_config' => ['member_id' => self::ADMIN_MEMBER_ID],
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'status' => $status,
            'is_gating' => true,
            'sort_order' => 1,
        ]));
    }
}
