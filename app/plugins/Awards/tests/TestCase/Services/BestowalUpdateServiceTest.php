<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\Model\Entity\ActionItem;
use App\Services\ActionItems\ActionItemCompletionFormRegistry;
use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\BestowalTodoTemplateItem;
use Awards\Model\Entity\Recommendation;
use Awards\Services\BestowalCourtSlotService;
use Awards\Services\BestowalCreationService;
use Awards\Services\BestowalTodoCompletionFormProvider;
use Awards\Services\BestowalUpdateService;
use Cake\I18n\DateTime;
use Cake\ORM\Table;
use RuntimeException;

class BestowalUpdateServiceTest extends BaseTestCase
{
    private Table $recommendationsTable;
    private Table $bestowalsTable;
    private BestowalCreationService $creationService;
    private BestowalUpdateService $updateService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();

        Recommendation::clearCache();

        $this->recommendationsTable = $this->getTableLocator()->get('Awards.Recommendations');
        $this->bestowalsTable = $this->getTableLocator()->get('Awards.Bestowals');
        $this->creationService = new BestowalCreationService();
        $this->updateService = new BestowalUpdateService();
        ActionItemCompletionFormRegistry::clear();
        ActionItemCompletionFormRegistry::register('Awards.BestowalTodo', new BestowalTodoCompletionFormProvider());
    }

    protected function tearDown(): void
    {
        ActionItemCompletionFormRegistry::clear();
        Recommendation::clearCache();
        parent::tearDown();
    }

    public function testUpdateRequiresAwardId(): void
    {
        $bestowalId = $this->createBestowalFromRecommendation();

        $result = $this->updateService->update(
            $this->bestowalsTable,
            $bestowalId,
            [
                'award_id' => '',
            ],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Award to Bestow is required', (string)$result['error']);
    }

    public function testUpdateBestowalAwardDoesNotChangeLinkedRecommendationAward(): void
    {
        $recommendationId = $this->createRecommendation('Need to Schedule');
        $recommendation = $this->recommendationsTable->get($recommendationId);
        $originalRecommendationAwardId = (int)$recommendation->award_id;

        $createResult = $this->creationService->createFromRecommendation(
            $recommendationId,
            self::ADMIN_MEMBER_ID,
        );
        $this->assertTrue($createResult['success'], $createResult['error'] ?? json_encode($createResult));

        $bestowalId = (int)$createResult['data']['bestowalId'];
        $replacementAwardId = $this->getAlternateAwardId($originalRecommendationAwardId);

        $result = $this->updateService->update(
            $this->bestowalsTable,
            $bestowalId,
            [
                'award_id' => $replacementAwardId,
            ],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertTrue($result['success'], $result['error'] ?? json_encode($result));

        $updatedBestowal = $this->bestowalsTable->get($bestowalId);
        $this->assertSame($replacementAwardId, (int)$updatedBestowal->award_id);

        $updatedRecommendation = $this->recommendationsTable->get($recommendationId);
        $this->assertSame(
            $originalRecommendationAwardId,
            (int)$updatedRecommendation->award_id,
            'Bestowal award edits must not sync back to linked recommendations.',
        );
    }

    public function testUpdatePersistsSpecialty(): void
    {
        $bestowalId = $this->createBestowalFromRecommendation();
        $bestowal = $this->bestowalsTable->get($bestowalId);

        $result = $this->updateService->update(
            $this->bestowalsTable,
            $bestowalId,
            [
                'award_id' => (int)$bestowal->award_id,
                'specialty' => 'Scribal Arts',
            ],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertTrue($result['success'], $result['error'] ?? json_encode($result));
        $updatedBestowal = $this->bestowalsTable->get($bestowalId);
        $this->assertSame('Scribal Arts', $updatedBestowal->specialty);
    }

    public function testUpdateNormalizesRoamingCourtSelection(): void
    {
        $bestowalId = $this->createBestowalFromRecommendation();
        $bestowal = $this->bestowalsTable->get($bestowalId);

        $result = $this->updateService->update(
            $this->bestowalsTable,
            $bestowalId,
            [
                'award_id' => (int)$bestowal->award_id,
                'gathering_scheduled_activity_id' => BestowalCourtSlotService::ROAMING_COURT_VALUE,
            ],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertTrue($result['success'], $result['error'] ?? json_encode($result));
        $updatedBestowal = $this->bestowalsTable->get($bestowalId);
        $this->assertTrue((bool)$updatedBestowal->roaming_court);
        $this->assertNull($updatedBestowal->gathering_scheduled_activity_id);
    }

    public function testClearingGatheringClearsCourtAssignmentAndReopensRequiredTodos(): void
    {
        $bestowalId = $this->createBestowalFromRecommendation();
        $bestowal = $this->bestowalsTable->get($bestowalId);
        $scheduledActivityId = $this->createScheduledActivity();
        $scheduledActivity = $this->getTableLocator()->get('GatheringScheduledActivities')->get($scheduledActivityId);
        $this->bestowalsTable->patchEntity($bestowal, [
            'award_id' => (int)$bestowal->award_id,
            'gathering_id' => (int)$scheduledActivity->gathering_id,
            'gathering_scheduled_activity_id' => $scheduledActivityId,
            'roaming_court' => false,
        ]);
        $this->bestowalsTable->saveOrFail($bestowal);
        $eventTodo = $this->createCompletedRequiredTodo(
            $bestowalId,
            BestowalTodoTemplateItem::ITEM_KEY_EVENT_SCHEDULED,
            BestowalTodoTemplateItem::COMPLETION_PROVIDER_BESTOWAL_GATHERING,
            BestowalTodoTemplateItem::REQUIRED_FIELD_GATHERING,
        );
        $agendaTodo = $this->createCompletedRequiredTodo(
            $bestowalId,
            BestowalTodoTemplateItem::ITEM_KEY_ADDED_TO_AGENDA,
            BestowalTodoTemplateItem::COMPLETION_PROVIDER_BESTOWAL_COURT_SLOT,
            BestowalTodoTemplateItem::REQUIRED_FIELD_COURT_SLOT,
        );

        $result = $this->updateService->update(
            $this->bestowalsTable,
            $bestowalId,
            [
                'award_id' => (int)$bestowal->award_id,
                'gathering_id' => '',
            ],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertTrue($result['success'], $result['error'] ?? json_encode($result));
        $updatedBestowal = $this->bestowalsTable->get($bestowalId);
        $this->assertNull($updatedBestowal->gathering_id);
        $this->assertNull($updatedBestowal->gathering_scheduled_activity_id);
        $this->assertFalse((bool)$updatedBestowal->roaming_court);
        $actionItems = $this->getTableLocator()->get('ActionItems');
        $this->assertTrue($actionItems->get((int)$eventTodo->id)->isOpen());
        $this->assertTrue($actionItems->get((int)$agendaTodo->id)->isOpen());
    }

    private function createBestowalFromRecommendation(): int
    {
        $recommendationId = $this->createRecommendation('Need to Schedule');
        $createResult = $this->creationService->createFromRecommendation(
            $recommendationId,
            self::ADMIN_MEMBER_ID,
        );
        $this->assertTrue($createResult['success'], $createResult['error'] ?? json_encode($createResult));

        return (int)$createResult['data']['bestowalId'];
    }

    private function createRecommendation(string $state): int
    {
        $entity = $this->recommendationsTable->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'requester_id' => self::ADMIN_MEMBER_ID,
            'award_id' => $this->getFirstAwardId(),
            'reason' => 'Bestowal update service test',
            'requester_sca_name' => 'Admin von Admin',
            'member_sca_name' => 'Admin von Admin',
            'contact_email' => 'admin@test.com',
            'status' => $this->statusForState($state),
            'state' => $state,
            'state_date' => new DateTime('2024-01-01 00:00:00'),
            'call_into_court' => 'Not Set',
            'court_availability' => 'Not Set',
            'person_to_notify' => '',
            'branch_id' => self::KINGDOM_BRANCH_ID,
        ]);

        return (int)$this->recommendationsTable->saveOrFail($entity)->id;
    }

    private function createScheduledActivity(): int
    {
        $gathering = $this->getTableLocator()->get('Gatherings')->find()
            ->firstOrFail();
        $activity = $this->getTableLocator()->get('GatheringActivities')->find()
            ->select(['id'])
            ->firstOrFail();
        $scheduledActivities = $this->getTableLocator()->get('GatheringScheduledActivities');
        $scheduledActivity = $scheduledActivities->newEntity([
            'gathering_id' => (int)$gathering->id,
            'gathering_activity_id' => (int)$activity->id,
            'start_datetime' => (clone $gathering->start_date)->modify('+1 hour'),
            'has_end_time' => false,
            'display_title' => 'Bestowal Update Test Court',
            'description' => 'Court Session description.',
            'pre_register' => false,
            'is_other' => false,
            'created_by' => self::ADMIN_MEMBER_ID,
        ]);

        return (int)$scheduledActivities->saveOrFail($scheduledActivity)->id;
    }

    private function createCompletedRequiredTodo(
        int $bestowalId,
        string $sourceRef,
        string $provider,
        string $field,
    ): ActionItem {
        $actionItems = $this->getTableLocator()->get('ActionItems');

        return $actionItems->saveOrFail($actionItems->newEntity([
            'entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE,
            'entity_id' => $bestowalId,
            'title' => $sourceRef,
            'assignee_type' => ActionItem::ASSIGNEE_TYPE_MEMBER,
            'assignee_config' => ['member_id' => self::ADMIN_MEMBER_ID],
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'status' => ActionItem::STATUS_COMPLETED,
            'completed_by' => self::ADMIN_MEMBER_ID,
            'is_gating' => true,
            'sort_order' => $sourceRef === BestowalTodoTemplateItem::ITEM_KEY_EVENT_SCHEDULED ? 10 : 20,
            'source_ref' => $sourceRef,
            'completion_config' => [
                ActionItem::COMPLETION_CONFIG_AUTO_COMPLETE => true,
                'required_fields' => [
                    [
                        'provider' => $provider,
                        'field' => $field,
                        'conditional_complete_on_assign' => true,
                    ],
                ],
            ],
        ]));
    }

    private function getFirstAwardId(): int
    {
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->select(['id'])
            ->orderByAsc('id')
            ->firstOrFail();

        return (int)$award->id;
    }

    private function getAlternateAwardId(int $excludeAwardId): int
    {
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->select(['id'])
            ->where(['id !=' => $excludeAwardId])
            ->orderByAsc('id')
            ->firstOrFail();

        return (int)$award->id;
    }

    private function statusForState(string $state): string
    {
        $statusList = Recommendation::getStatuses();
        foreach ($statusList as $status => $states) {
            if (in_array($state, $states, true)) {
                return $status;
            }
        }

        throw new RuntimeException('Unknown recommendation state: ' . $state);
    }
}
