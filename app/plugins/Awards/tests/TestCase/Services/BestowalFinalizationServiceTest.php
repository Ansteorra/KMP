<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\Model\Entity\ActionItem;
use App\Services\ActionItems\ActionItemService;
use App\Test\TestCase\BaseTestCase;
use Awards\Event\BestowalTodoCompletionListener;
use Awards\Model\Entity\Bestowal;
use Awards\Services\BestowalFinalizationService;
use Cake\Event\EventManager;
use Cake\ORM\Table;

/**
 * Coverage for the bestowal "Mark Given" finalization service and its automatic
 * trigger when the gating "Given" to-do is completed.
 */
class BestowalFinalizationServiceTest extends BaseTestCase
{
    private Table $bestowals;
    private Table $actionItems;
    private ActionItemService $actionItemService;
    private BestowalTodoCompletionListener $listener;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->bestowals = $this->getTableLocator()->get('Awards.Bestowals');
        $this->actionItems = $this->getTableLocator()->get('ActionItems');
        $this->actionItemService = new ActionItemService();

        // Register the auto-finalize listener deterministically for the e2e path
        // (idempotent with any registration from the plugin bootstrap).
        $this->listener = new BestowalTodoCompletionListener();
        EventManager::instance()->on($this->listener);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        EventManager::instance()->off($this->listener);
        parent::tearDown();
    }

    /**
     * Completing the gating to-do through the core service auto-finalizes the
     * owning bestowal to "given".
     *
     * @return void
     */
    public function testCompletingGatingTodoAutoFinalizesBestowal(): void
    {
        $bestowal = $this->makeBestowal();
        $todo = $this->makeTodo((int)$bestowal->id, ['is_gating' => true]);

        $result = $this->actionItemService->complete((int)$todo->id, self::ADMIN_MEMBER_ID);

        $this->assertTrue($result->success);
        $reloaded = $this->bestowals->get($bestowal->id);
        $this->assertSame(Bestowal::LIFECYCLE_GIVEN, $reloaded->lifecycle_status);
        $this->assertNotNull($reloaded->bestowed_at);
    }

    /**
     * Completing an optional (non-gating) to-do never finalizes the bestowal.
     *
     * @return void
     */
    public function testCompletingOptionalTodoDoesNotFinalize(): void
    {
        $bestowal = $this->makeBestowal();
        $this->makeTodo((int)$bestowal->id, ['is_gating' => true, 'title' => 'Given']);
        $optional = $this->makeTodo((int)$bestowal->id, [
            'is_gating' => false,
            'title' => 'Regalia allotted',
            'sort_order' => 2,
        ]);

        $result = $this->actionItemService->complete((int)$optional->id, self::ADMIN_MEMBER_ID);

        $this->assertTrue($result->success);
        $reloaded = $this->bestowals->get($bestowal->id);
        $this->assertSame(Bestowal::LIFECYCLE_OPEN, $reloaded->lifecycle_status);
    }

    /**
     * The strict markGiven path refuses to finalize while a gating check is open.
     *
     * @return void
     */
    public function testMarkGivenFailsWhenGatingIncomplete(): void
    {
        $bestowal = $this->makeBestowal();
        $this->makeTodo((int)$bestowal->id, ['is_gating' => true]);

        $result = $this->finalizationService()->markGiven((int)$bestowal->id, self::ADMIN_MEMBER_ID);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required checks', (string)$result->reason);
        $reloaded = $this->bestowals->get($bestowal->id);
        $this->assertSame(Bestowal::LIFECYCLE_OPEN, $reloaded->lifecycle_status);
    }

    /**
     * A cancelled bestowal can never be marked given, even with gating complete.
     *
     * @return void
     */
    public function testMarkGivenFailsForCancelledBestowal(): void
    {
        $bestowal = $this->makeBestowal(['lifecycle_status' => Bestowal::LIFECYCLE_CANCELLED]);
        $this->makeTodo((int)$bestowal->id, [
            'is_gating' => true,
            'status' => ActionItem::STATUS_COMPLETED,
        ]);

        $result = $this->finalizationService()->markGiven((int)$bestowal->id, self::ADMIN_MEMBER_ID);

        $this->assertFalse($result->success);
        $reloaded = $this->bestowals->get($bestowal->id);
        $this->assertSame(Bestowal::LIFECYCLE_CANCELLED, $reloaded->lifecycle_status);
    }

    /**
     * Auto-finalize is one-directional and idempotent: an already-given bestowal
     * is left untouched (a success no-op).
     *
     * @return void
     */
    public function testFinalizeIsIdempotentForGivenBestowal(): void
    {
        $bestowal = $this->makeBestowal(['lifecycle_status' => Bestowal::LIFECYCLE_GIVEN]);
        $this->makeTodo((int)$bestowal->id, [
            'is_gating' => true,
            'status' => ActionItem::STATUS_COMPLETED,
        ]);

        $result = $this->finalizationService()->finalizeFromGatingCompletion(
            (int)$bestowal->id,
            self::ADMIN_MEMBER_ID,
        );

        $this->assertTrue($result->success);
        $reloaded = $this->bestowals->get($bestowal->id);
        $this->assertSame(Bestowal::LIFECYCLE_GIVEN, $reloaded->lifecycle_status);
    }

    /**
     * Build a finalization service backed by the test table locator.
     *
     * @return \Awards\Services\BestowalFinalizationService
     */
    private function finalizationService(): BestowalFinalizationService
    {
        return new BestowalFinalizationService($this->actionItemService, null, $this->bestowals);
    }

    /**
     * Persist a bestowal with the fields required by current validation.
     *
     * @param array<string, mixed> $overrides Field overrides
     * @return \Awards\Model\Entity\Bestowal
     */
    private function makeBestowal(array $overrides = []): Bestowal
    {
        $award = $this->getTableLocator()->get('Awards.Awards')->find()->select(['id'])->firstOrFail();
        $data = array_merge([
            'member_id' => self::ADMIN_MEMBER_ID,
            'member_sca_name' => 'Test Recipient',
            'award_id' => $award->id,
            'lifecycle_status' => Bestowal::LIFECYCLE_OPEN,
            'source' => Bestowal::SOURCE_RECOMMENDATION,
            'stack_rank' => 0,
        ], $overrides);

        return $this->bestowals->saveOrFail($this->bestowals->newEntity($data));
    }

    /**
     * Persist an action item owned by the given bestowal, assigned to the admin.
     *
     * @param int $bestowalId Owning bestowal id
     * @param array<string, mixed> $overrides Field overrides
     * @return \App\Model\Entity\ActionItem
     */
    private function makeTodo(int $bestowalId, array $overrides = []): ActionItem
    {
        $data = array_merge([
            'entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE,
            'entity_id' => $bestowalId,
            'title' => 'Given',
            'description' => 'Award presented in court',
            'assignee_type' => ActionItem::ASSIGNEE_TYPE_MEMBER,
            'assignee_config' => ['member_id' => self::ADMIN_MEMBER_ID],
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'status' => ActionItem::STATUS_OPEN,
            'is_gating' => true,
            'sort_order' => 1,
        ], $overrides);

        return $this->actionItems->saveOrFail($this->actionItems->newEntity($data));
    }
}
