<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\ActionItem;
use App\Services\ActionItems\ActionItemCompletionForm;
use App\Services\ActionItems\ActionItemCompletionFormProviderInterface;
use App\Services\ActionItems\ActionItemCompletionFormRegistry;
use App\Services\ActionItems\ActionItemService;
use App\Services\ServiceResult;
use App\Test\TestCase\BaseTestCase;
use Awards\Event\BestowalTodoCompletionListener;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\BestowalTodoTemplateItem;
use Awards\Services\BestowalCancellationService;
use Awards\Services\BestowalFinalizationService;
use Awards\Services\BestowalRecommendationSyncService;
use Awards\Services\BestowalTodoMaterializationService;
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
     * @var array<string, \App\Services\ActionItems\ActionItemCompletionFormProviderInterface>
     */
    private array $completionFormProviders;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->bestowals = $this->getTableLocator()->get('Awards.Bestowals');
        $this->actionItems = $this->getTableLocator()->get('ActionItems');
        $this->actionItemService = new ActionItemService();
        $this->completionFormProviders = ActionItemCompletionFormRegistry::providers();

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
        ActionItemCompletionFormRegistry::clear();
        foreach ($this->completionFormProviders as $source => $provider) {
            ActionItemCompletionFormRegistry::register($source, $provider);
        }
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
     * All requirement-driven completions settle before a gating completion
     * event can make the owner immutable.
     *
     * @return void
     */
    public function testCompletingGatingTodoDefersFinalizationUntilRequiredFieldBatchSettles(): void
    {
        ActionItemCompletionFormRegistry::register(
            'AwardsTestAlwaysSatisfied',
            new class implements ActionItemCompletionFormProviderInterface {
                public function canHandle(ActionItem $item): bool
                {
                    foreach ($item->getRequiredFieldConfigs() as $config) {
                        if (($config['provider'] ?? null) === 'Tests.AlwaysSatisfied') {
                            return true;
                        }
                    }

                    return false;
                }

                public function buildForm(ActionItem $item, KmpIdentityInterface $user): ?ActionItemCompletionForm
                {
                    return null;
                }

                public function applySubmission(
                    ActionItem $item,
                    array $data,
                    int $actorId,
                    KmpIdentityInterface $user,
                ): ServiceResult {
                    return new ServiceResult(true);
                }

                public function validateCompletion(ActionItem $item): ServiceResult
                {
                    return new ServiceResult(true);
                }
            },
        );

        $bestowal = $this->makeBestowal();
        $manualGating = $this->makeTodo((int)$bestowal->id, [
            'title' => 'Manual gating check',
            'source_ref' => 'manual_gating',
            'sort_order' => 0,
        ]);
        $requirementConfig = [
            ActionItem::COMPLETION_CONFIG_AUTO_COMPLETE => true,
            'required_fields' => [[
                'provider' => 'Tests.AlwaysSatisfied',
                'field' => 'ready',
            ]],
        ];
        $automaticGating = $this->makeTodo((int)$bestowal->id, [
            'title' => 'Automatic gating check',
            'source_ref' => 'automatic_gating',
            'sort_order' => 1,
            'completion_config' => $requirementConfig,
        ]);
        $automaticOptional = $this->makeTodo((int)$bestowal->id, [
            'title' => 'Automatic optional check',
            'source_ref' => 'automatic_optional',
            'is_gating' => false,
            'sort_order' => 2,
            'completion_config' => $requirementConfig,
        ]);

        $result = $this->actionItemService->complete(
            (int)$manualGating->id,
            self::ADMIN_MEMBER_ID,
        );

        $this->assertTrue($result->success, (string)$result->reason);
        $this->assertSame(ActionItem::STATUS_COMPLETED, $this->actionItems->get($automaticGating->id)->status);
        $this->assertSame(ActionItem::STATUS_COMPLETED, $this->actionItems->get($automaticOptional->id)->status);
        $this->assertSame(
            Bestowal::LIFECYCLE_GIVEN,
            $this->bestowals->get($bestowal->id)->lifecycle_status,
        );
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

    public function testMarkGivenAllowsSynchronizedAssignedTemplatesWithoutGates(): void
    {
        $templates = $this->getTableLocator()->get('Awards.BestowalTodoTemplates');
        $templateItems = $this->getTableLocator()->get('Awards.BestowalTodoTemplateItems');
        $awards = $this->getTableLocator()->get('Awards.Awards');
        $award = $awards->find()->firstOrFail();
        $award->branch_id = self::KINGDOM_BRANCH_ID;

        foreach ([false, true] as $includeOptionalItem) {
            $template = $templates->saveOrFail($templates->newEntity([
                'name' => sprintf(
                    '%s template finalization regression %s',
                    $includeOptionalItem ? 'Optional-only' : 'Empty',
                    uniqid('', true),
                ),
                'is_active' => true,
            ]));
            if ($includeOptionalItem) {
                $templateItems->saveOrFail($templateItems->newEntity([
                    'template_id' => (int)$template->id,
                    'item_key' => 'optional_after_court',
                    'label' => 'Optional follow-up',
                    'assignee_type' => BestowalTodoTemplateItem::ASSIGNEE_TYPE_MEMBER,
                    'assignee_source_id' => self::ADMIN_MEMBER_ID,
                    'branch_mode' => BestowalTodoTemplateItem::BRANCH_MODE_AWARD,
                    'is_gating' => false,
                    'sort_order' => 0,
                ]));
            }
            $award->bestowal_todo_template_id = (int)$template->id;
            $awards->saveOrFail($award);

            $bestowal = $this->makeBestowal([
                'award_id' => (int)$award->id,
                'member_sca_name' => $includeOptionalItem
                    ? 'Optional-only Template Recipient'
                    : 'Empty Template Recipient',
            ]);
            $sync = (new BestowalTodoMaterializationService())->syncForBestowal(
                $bestowal,
                self::ADMIN_MEMBER_ID,
            );
            $this->assertTrue($sync->success, (string)$sync->reason);
            $this->assertFalse($sync->data['skipped'], (string)$sync->reason);
            $this->assertFalse($this->actionItemService->hasActiveGatingItems(
                Bestowal::ACTION_ITEM_ENTITY_TYPE,
                (int)$bestowal->id,
            ));

            $result = $this->finalizationService()->markGiven(
                (int)$bestowal->id,
                self::ADMIN_MEMBER_ID,
            );

            $this->assertTrue($result->success, (string)$result->reason);
            $this->assertSame(
                Bestowal::LIFECYCLE_GIVEN,
                $this->bestowals->get($bestowal->id)->lifecycle_status,
            );
        }
    }

    public function testMarkGivenRequiresScheduledAndAgendaGatingTodos(): void
    {
        $bestowal = $this->makeBestowal();
        $this->makeTodo((int)$bestowal->id, [
            'title' => 'Event Scheduled',
            'source_ref' => BestowalTodoTemplateItem::ITEM_KEY_EVENT_SCHEDULED,
            'status' => ActionItem::STATUS_COMPLETED,
            'sort_order' => 10,
        ]);
        $this->makeTodo((int)$bestowal->id, [
            'title' => 'Added to Agenda',
            'source_ref' => BestowalTodoTemplateItem::ITEM_KEY_ADDED_TO_AGENDA,
            'status' => ActionItem::STATUS_OPEN,
            'sort_order' => 20,
        ]);
        $this->makeTodo((int)$bestowal->id, [
            'title' => 'Given',
            'status' => ActionItem::STATUS_COMPLETED,
            'sort_order' => 30,
        ]);

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

    public function testGivenBestowalRejectsQueuedTodoReopen(): void
    {
        $bestowal = $this->makeBestowal(['lifecycle_status' => Bestowal::LIFECYCLE_GIVEN]);
        $todo = $this->makeTodo((int)$bestowal->id, [
            'status' => ActionItem::STATUS_COMPLETED,
        ]);

        $result = $this->actionItemService->reopen(
            (int)$todo->id,
            self::ADMIN_MEMBER_ID,
            null,
            false,
        );

        $this->assertFalse($result->success);
        $this->assertSame('The to-do owner is no longer active.', $result->reason);
        $this->assertSame(ActionItem::STATUS_COMPLETED, $this->actionItems->get($todo->id)->status);
    }

    public function testCancelledBestowalRejectsQueuedTodoCompletion(): void
    {
        $bestowal = $this->makeBestowal(['lifecycle_status' => Bestowal::LIFECYCLE_CANCELLED]);
        $todo = $this->makeTodo((int)$bestowal->id);

        $result = $this->actionItemService->complete(
            (int)$todo->id,
            self::ADMIN_MEMBER_ID,
            null,
            false,
        );

        $this->assertFalse($result->success);
        $this->assertSame('The to-do owner is no longer active.', $result->reason);
        $this->assertSame(ActionItem::STATUS_OPEN, $this->actionItems->get($todo->id)->status);
    }

    public function testGivenBestowalRejectsDirectDefinitionSynchronization(): void
    {
        $bestowal = $this->makeBestowal(['lifecycle_status' => Bestowal::LIFECYCLE_GIVEN]);

        $result = $this->actionItemService->synchronizeFor(
            Bestowal::ACTION_ITEM_ENTITY_TYPE,
            (int)$bestowal->id,
            [[
                'source_ref' => 'late_terminal_item',
                'title' => 'Late terminal item',
                'assignee_type' => ActionItem::ASSIGNEE_TYPE_MEMBER,
                'assignee_config' => ['member_id' => self::ADMIN_MEMBER_ID],
            ]],
            self::KINGDOM_BRANCH_ID,
            self::ADMIN_MEMBER_ID,
        );

        $this->assertFalse($result->success);
        $this->assertSame('The to-do owner is no longer active.', $result->reason);
        $this->assertSame(0, $this->actionItems->find()->where([
            'entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE,
            'entity_id' => (int)$bestowal->id,
        ])->count());
    }

    public function testGivenBestowalCannotBeCancelled(): void
    {
        $bestowal = $this->makeBestowal(['lifecycle_status' => Bestowal::LIFECYCLE_GIVEN]);

        $result = (new BestowalCancellationService())->cancel(
            (int)$bestowal->id,
            self::ADMIN_MEMBER_ID,
            'This stale request must not win.',
        );

        $this->assertFalse($result['success']);
        $this->assertSame('Given bestowals cannot be cancelled.', $result['error']);
        $this->assertSame(
            Bestowal::LIFECYCLE_GIVEN,
            $this->bestowals->get($bestowal->id)->lifecycle_status,
        );
    }

    public function testMarkGivenRollsBackWhenRecommendationSyncFails(): void
    {
        $bestowal = $this->makeBestowal();
        $this->makeTodo((int)$bestowal->id, [
            'is_gating' => true,
            'status' => ActionItem::STATUS_COMPLETED,
        ]);
        $syncService = $this->createMock(BestowalRecommendationSyncService::class);
        $syncService->method('syncFromBestowal')->willReturn([
            'success' => false,
            'error' => 'Recommendation sync failed.',
        ]);
        $service = new BestowalFinalizationService($this->actionItemService, $syncService, $this->bestowals);

        $result = $service->markGiven((int)$bestowal->id, self::ADMIN_MEMBER_ID);

        $this->assertFalse($result->success);
        $this->assertSame('Recommendation sync failed.', $result->reason);
        $reloaded = $this->bestowals->get($bestowal->id);
        $this->assertSame(Bestowal::LIFECYCLE_OPEN, $reloaded->lifecycle_status);
        $this->assertNull($reloaded->bestowed_at);
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
