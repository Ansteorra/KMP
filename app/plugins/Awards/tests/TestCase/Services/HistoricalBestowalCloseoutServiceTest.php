<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\Model\Entity\ActionItem;
use App\Test\TestCase\BaseTestCase;
use Awards\Event\BestowalTodoCompletionListener;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\Recommendation;
use Awards\Services\HistoricalBestowalCloseoutService;
use Cake\Core\Configure;
use Cake\Event\EventManager;
use Cake\I18n\DateTime;
use Cake\ORM\Table;
use ReflectionMethod;
use RuntimeException;

/**
 * Focused integration coverage for the one-time historical closeout service.
 *
 * The public command is deliberately pinned to the 273-row production
 * manifest. These tests exercise the private per-record machinery with fresh
 * transactional fixtures so the production pin remains impossible to weaken.
 */
class HistoricalBestowalCloseoutServiceTest extends BaseTestCase
{
    private HistoricalBestowalCloseoutService $service;

    private Table $recommendations;

    private Table $bestowals;

    private Table $bestowalRecommendations;

    private Table $actionItems;

    private Table $actionItemLogs;

    private Table $stateLogs;

    private BestowalTodoCompletionListener $listener;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Recommendation::clearCache();
        Configure::write('KMP.KingdomName', 'Ansteorra');

        $locator = $this->getTableLocator();
        $this->recommendations = $locator->get('Awards.Recommendations');
        $this->bestowals = $locator->get('Awards.Bestowals');
        $this->bestowalRecommendations = $locator->get('Awards.BestowalRecommendations');
        $this->actionItems = $locator->get('ActionItems');
        $this->actionItemLogs = $locator->get('ActionItemLogs');
        $this->stateLogs = $locator->get('Awards.RecommendationsStatesLogs');
        $this->service = new HistoricalBestowalCloseoutService();

        $this->listener = new BestowalTodoCompletionListener();
        EventManager::instance()->on($this->listener);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        EventManager::instance()->off($this->listener);
        Configure::delete('KMP.KingdomName');
        Recommendation::clearCache();

        parent::tearDown();
    }

    public function testDryRunInspectionFindsActionableRecordWithoutWrites(): void
    {
        $fixture = $this->makeEligibleFixture();
        $before = $this->capturedState($fixture);

        $inspection = $this->invoke('inspectRecord', $fixture['record'], false);

        $this->assertSame('actionable', $inspection['result']);
        $this->assertSame('', $inspection['reason']);
        $this->assertSame($before, $this->capturedState($fixture));
        $this->assertSame(0, $this->actionLogCount($fixture['actionItemId']));
        $this->assertSame(0, $this->stateLogCount($fixture['recommendationId']));
    }

    public function testApplyUsesHistoricalDateAndWritesBothAuditTrails(): void
    {
        $fixture = $this->makeEligibleFixture();

        $this->invoke(
            'applyRecord',
            $fixture['record'],
            HistoricalBestowalCloseoutService::CANONICAL_MANIFEST_SHA256,
            self::ADMIN_MEMBER_ID,
            'TEST-CHANGE-42',
        );

        $recommendation = $this->recommendations->get($fixture['recommendationId']);
        $bestowal = $this->bestowals->get($fixture['bestowalId']);
        $actionItem = $this->actionItems->get($fixture['actionItemId']);

        $this->assertSame('Closed', $recommendation->status);
        $this->assertSame('Given', $recommendation->state);
        $this->assertSame('2024-10-12', $recommendation->given?->format('Y-m-d'));
        $this->assertSame($fixture['gatheringId'], (int)$recommendation->gathering_id);
        $this->assertSame(Bestowal::LIFECYCLE_GIVEN, $bestowal->lifecycle_status);
        $this->assertSame('2024-10-12', $bestowal->bestowed_at?->format('Y-m-d'));
        $this->assertSame($fixture['gatheringId'], (int)$bestowal->gathering_id);
        $this->assertSame(ActionItem::STATUS_COMPLETED, $actionItem->status);
        $this->assertSame(self::ADMIN_MEMBER_ID, (int)$actionItem->completed_by);

        $this->assertSame(1, $this->actionItems->find()->where([
            'entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE,
            'entity_id' => $fixture['bestowalId'],
        ])->count());
        $this->assertSame(1, $this->actionLogCount($fixture['actionItemId']));
        $this->assertSame(1, $this->stateLogCount($fixture['recommendationId']));

        $actionLog = $this->actionItemLogs->find()
            ->where(['action_item_id' => $fixture['actionItemId']])
            ->firstOrFail();
        $this->assertSame(ActionItem::STATUS_OPEN, $actionLog->from_status);
        $this->assertSame(ActionItem::STATUS_COMPLETED, $actionLog->to_status);
        $this->assertSame(self::ADMIN_MEMBER_ID, (int)$actionLog->created_by);
        $this->assertStringContainsString('change=TEST-CHANGE-42', (string)$actionLog->note);
        $this->assertStringContainsString(
            'manifest_sha256=' . HistoricalBestowalCloseoutService::CANONICAL_MANIFEST_SHA256,
            (string)$actionLog->note,
        );

        $stateLog = $this->stateLogs->find()
            ->where(['recommendation_id' => $fixture['recommendationId']])
            ->firstOrFail();
        $this->assertSame('Scheduled', $stateLog->from_state);
        $this->assertSame('Given', $stateLog->to_state);
        $this->assertSame('To Give', $stateLog->from_status);
        $this->assertSame('Closed', $stateLog->to_status);
        $this->assertSame(self::ADMIN_MEMBER_ID, (int)$stateLog->created_by);
    }

    public function testCompletedRecordIsRecognizedAsIdempotentWithoutNewLogs(): void
    {
        $fixture = $this->makeEligibleFixture();
        $this->invoke(
            'applyRecord',
            $fixture['record'],
            HistoricalBestowalCloseoutService::CANONICAL_MANIFEST_SHA256,
            self::ADMIN_MEMBER_ID,
            'TEST-IDEMPOTENCE',
        );
        $actionLogCount = $this->actionLogCount($fixture['actionItemId']);
        $stateLogCount = $this->stateLogCount($fixture['recommendationId']);

        $firstInspection = $this->invoke('inspectRecord', $fixture['record'], false);
        $secondInspection = $this->invoke('inspectRecord', $fixture['record'], false);

        $this->assertSame('already_applied', $firstInspection['result']);
        $this->assertSame($firstInspection, $secondInspection);
        $this->assertSame($actionLogCount, $this->actionLogCount($fixture['actionItemId']));
        $this->assertSame($stateLogCount, $this->stateLogCount($fixture['recommendationId']));
    }

    public function testOuterTransactionRollsBackEveryCloseoutWrite(): void
    {
        $fixture = $this->makeEligibleFixture();
        $before = $this->capturedState($fixture);
        $connection = $this->recommendations->getConnection();
        $connection->enableSavePoints();

        try {
            $connection->transactional(function () use ($fixture): void {
                $this->invoke(
                    'applyRecord',
                    $fixture['record'],
                    HistoricalBestowalCloseoutService::CANONICAL_MANIFEST_SHA256,
                    self::ADMIN_MEMBER_ID,
                    'TEST-FORCED-ROLLBACK',
                );

                throw new RuntimeException('Force rollback after every closeout write.');
            });
            $this->fail('Expected the forced rollback exception.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Force rollback after every closeout write.', $exception->getMessage());
        }

        $this->assertSame($before, $this->capturedState($fixture));
        $this->assertSame(0, $this->actionLogCount($fixture['actionItemId']));
        $this->assertSame(0, $this->stateLogCount($fixture['recommendationId']));
    }

    public function testFingerprintDriftBlocksRecordWithoutWrites(): void
    {
        $fixture = $this->makeEligibleFixture();
        $actionItem = $this->actionItems->get($fixture['actionItemId']);
        $actionItem->source_ref = 'unexpected';
        $this->actionItems->saveOrFail($actionItem);

        $inspection = $this->invoke('inspectRecord', $fixture['record'], false);

        $this->assertSame('drift', $inspection['result']);
        $this->assertStringContainsString('Apply fingerprint changed', $inspection['reason']);
        $this->assertSame(Bestowal::LIFECYCLE_OPEN, $this->bestowals->get($fixture['bestowalId'])->lifecycle_status);
        $this->assertSame('Scheduled', $this->recommendations->get($fixture['recommendationId'])->state);
        $this->assertSame(0, $this->actionLogCount($fixture['actionItemId']));
        $this->assertSame(0, $this->stateLogCount($fixture['recommendationId']));
    }

    public function testOpenActionItemWithCompletionMetadataIsDrift(): void
    {
        $fixture = $this->makeEligibleFixture();
        $actionItem = $this->actionItems->get($fixture['actionItemId']);
        $actionItem->completed_at = new DateTime('2024-01-01 00:00:00');
        $actionItem->completed_by = self::ADMIN_MEMBER_ID;
        $this->actionItems->saveOrFail($actionItem);

        $inspection = $this->invoke('inspectRecord', $fixture['record'], false);

        $this->assertSame('drift', $inspection['result']);
        $this->assertStringContainsString('completion metadata', $inspection['reason']);
        $this->assertSame(0, $this->actionLogCount($fixture['actionItemId']));
        $this->assertSame(0, $this->stateLogCount($fixture['recommendationId']));
    }

    public function testMixedApplyPopulationIsMarkedAsDrift(): void
    {
        $inspections = [
            $this->inspection(10, 'actionable'),
            $this->inspection(20, 'already_applied'),
            [
                'recommendationId' => 30,
                'disposition' => 'hold',
                'result' => 'verified_control',
                'reason' => '',
            ],
        ];

        $result = $this->invoke('normalizeApplyPopulation', $inspections);

        $this->assertSame('drift', $result[0]['result']);
        $this->assertSame('drift', $result[1]['result']);
        $this->assertStringContainsString('not uniformly', strtolower($result[0]['reason']));
        $this->assertSame('verified_control', $result[2]['result']);

        $uniform = [$this->inspection(40, 'actionable'), $this->inspection(50, 'actionable')];
        $this->assertSame($uniform, $this->invoke('normalizeApplyPopulation', $uniform));
    }

    public function testPublicRunRejectsNonCanonicalHashBeforeDatabaseWork(): void
    {
        $result = $this->service->run(
            '/does/not/matter.json',
            str_repeat('a', 64),
            249,
            self::ADMIN_MEMBER_ID,
            'ansteorra',
            'TEST-HASH-GUARD',
            false,
        );

        $this->assertFalse($result->success);
        $this->assertStringContainsString('canonical reviewed digest', (string)$result->reason);
        $this->assertSame(0, $result->data['summary']['changed']);
    }

    public function testRunInputRejectsWrongTenant(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('restricted to the ansteorra tenant');

        $this->invoke(
            'validateRunInputs',
            HistoricalBestowalCloseoutService::CANONICAL_MANIFEST_SHA256,
            249,
            self::ADMIN_MEMBER_ID,
            'other-tenant',
            'TEST-TENANT-GUARD',
        );
    }

    public function testRuntimeGuardRejectsInactiveActor(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('audit actor is not an active member');

        // The seeded admin is intentionally "verified", not literal "active".
        $this->invoke('assertRuntimeGuards', self::ADMIN_MEMBER_ID);
    }

    /**
     * @return array{
     *   recommendationId: int,
     *   bestowalId: int,
     *   actionItemId: int,
     *   gatheringId: int,
     *   record: array<string, mixed>
     * }
     */
    private function makeEligibleFixture(): array
    {
        $member = $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);
        $award = $this->getTableLocator()->get('Awards.Awards')->find()->firstOrFail();
        $gathering = $this->getTableLocator()->get('Gatherings')->find()->firstOrFail();

        $recommendation = $this->recommendations->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'requester_id' => self::ADMIN_MEMBER_ID,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'award_id' => (int)$award->id,
            'gathering_id' => (int)$gathering->id,
            'requester_sca_name' => (string)$member->sca_name,
            'member_sca_name' => (string)$member->sca_name,
            'contact_email' => 'historical-closeout-test@example.test',
            'reason' => 'Historical closeout integration fixture',
            'status' => 'To Give',
            'state' => 'Scheduled',
            'call_into_court' => 'Not Set',
            'court_availability' => 'Not Set',
            'person_to_notify' => '',
        ]);
        $recommendation = $this->recommendations->saveOrFail($recommendation);

        $bestowal = $this->bestowals->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'member_sca_name' => (string)$member->sca_name,
            'gathering_id' => (int)$gathering->id,
            'primary_recommendation_id' => (int)$recommendation->id,
            'award_id' => (int)$award->id,
            'lifecycle_status' => Bestowal::LIFECYCLE_OPEN,
            'source' => Bestowal::SOURCE_RECOMMENDATION,
            'stack_rank' => 0,
        ]);
        $bestowal = $this->bestowals->saveOrFail($bestowal);

        $recommendation->bestowal_id = (int)$bestowal->id;
        $recommendation = $this->recommendations->saveOrFail($recommendation, ['systemSync' => true]);
        $this->bestowalRecommendations->saveOrFail($this->bestowalRecommendations->newEntity([
            'bestowal_id' => (int)$bestowal->id,
            'recommendation_id' => (int)$recommendation->id,
        ]));

        $actionItem = $this->actionItems->saveOrFail($this->actionItems->newEntity([
            'entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE,
            'entity_id' => (int)$bestowal->id,
            'title' => 'Given',
            'description' => 'Historical award was presented',
            'assignee_type' => ActionItem::ASSIGNEE_TYPE_MEMBER,
            'assignee_config' => ['member_id' => self::ADMIN_MEMBER_ID],
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'status' => ActionItem::STATUS_OPEN,
            'is_gating' => true,
            'sort_order' => 1,
            'source_ref' => 'given',
            'completion_config' => null,
        ]));

        $record = [
            'recommendationId' => (int)$recommendation->id,
            'disposition' => 'apply',
            'historicalGivenDate' => '2024-10-12',
            'dateSource' => 'workbook.op_award_date',
            'reason' => null,
            'expected' => [
                'recommendationStatus' => 'To Give',
                'recommendationState' => 'Scheduled',
                'recommendationGivenDate' => null,
                'recommendationDeleted' => null,
                'memberId' => self::ADMIN_MEMBER_ID,
                'memberNameSha256' => hash('sha256', (string)$member->sca_name),
                'awardId' => (int)$award->id,
                'gatheringId' => (int)$gathering->id,
                'bestowalId' => (int)$bestowal->id,
                'bestowalLifecycleStatus' => Bestowal::LIFECYCLE_OPEN,
                'bestowalBestowedDate' => null,
                'bestowalMemberId' => self::ADMIN_MEMBER_ID,
                'bestowalMemberNameSha256' => hash('sha256', (string)$member->sca_name),
                'bestowalAwardId' => (int)$award->id,
                'bestowalGatheringId' => (int)$gathering->id,
                'actionItemId' => (int)$actionItem->id,
                'actionItemStatus' => ActionItem::STATUS_OPEN,
                'actionItemIsGating' => true,
                'actionItemSourceRef' => 'given',
                'actionItemCompletionConfig' => null,
            ],
        ];

        return [
            'recommendationId' => (int)$recommendation->id,
            'bestowalId' => (int)$bestowal->id,
            'actionItemId' => (int)$actionItem->id,
            'gatheringId' => (int)$gathering->id,
            'record' => $record,
        ];
    }

    /**
     * @param array<string, mixed> $fixture Fixture IDs and record.
     * @return array<string, mixed>
     */
    private function capturedState(array $fixture): array
    {
        $recommendation = $this->recommendations->get($fixture['recommendationId']);
        $bestowal = $this->bestowals->get($fixture['bestowalId']);
        $actionItem = $this->actionItems->get($fixture['actionItemId']);

        return [
            'recommendationStatus' => $recommendation->status,
            'recommendationState' => $recommendation->state,
            'recommendationGiven' => $recommendation->given,
            'recommendationGatheringId' => $recommendation->gathering_id,
            'bestowalLifecycle' => $bestowal->lifecycle_status,
            'bestowalBestowedAt' => $bestowal->bestowed_at,
            'bestowalGatheringId' => $bestowal->gathering_id,
            'actionItemStatus' => $actionItem->status,
            'actionItemCompletedAt' => $actionItem->completed_at,
            'actionItemCompletedBy' => $actionItem->completed_by,
        ];
    }

    /**
     * @return array{recommendationId: int, disposition: string, result: string, reason: string}
     */
    private function inspection(int $recommendationId, string $result): array
    {
        return [
            'recommendationId' => $recommendationId,
            'disposition' => 'apply',
            'result' => $result,
            'reason' => '',
        ];
    }

    private function actionLogCount(int $actionItemId): int
    {
        return $this->actionItemLogs->find()->where(['action_item_id' => $actionItemId])->count();
    }

    private function stateLogCount(int $recommendationId): int
    {
        return $this->stateLogs->find()->where(['recommendation_id' => $recommendationId])->count();
    }

    private function invoke(string $method, mixed ...$arguments): mixed
    {
        $reflection = new ReflectionMethod(HistoricalBestowalCloseoutService::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($this->service, ...$arguments);
    }
}
