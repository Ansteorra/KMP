<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\Model\Entity\ActionItem;
use App\Services\ActionItems\ActionItemCompletionFormRegistry;
use App\Services\ActionItems\ActionItemService;
use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\BestowalTodoTemplateItem;
use Awards\Services\BestowalTodoCompletionFormProvider;
use Awards\Services\BestowalTodoMaterializationService;
use Cake\I18n\DateTime;

class BestowalTodoSyncLifecycleRegressionTest extends BaseTestCase
{
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

        $this->completionFormProviders = ActionItemCompletionFormRegistry::providers();
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        ActionItemCompletionFormRegistry::clear();
        foreach ($this->completionFormProviders as $source => $provider) {
            ActionItemCompletionFormRegistry::register($source, $provider);
        }

        parent::tearDown();
    }

    public function testDeletedOwnerRejectsStaleSynchronizationAndQueuedTransitions(): void
    {
        $templates = $this->getTableLocator()->get('Awards.BestowalTodoTemplates');
        $template = $templates->saveOrFail($templates->newEntity([
            'name' => 'Deleted owner lifecycle regression ' . uniqid('', true),
            'is_active' => true,
        ]));
        $templateItems = $this->getTableLocator()->get('Awards.BestowalTodoTemplateItems');
        $sharedDefinition = $templateItems->saveOrFail($templateItems->newEntity([
            'template_id' => (int)$template->id,
            'item_key' => 'deleted_owner_shared',
            'label' => 'Original deleted-owner to-do',
            'assignee_type' => BestowalTodoTemplateItem::ASSIGNEE_TYPE_MEMBER,
            'assignee_source_id' => self::ADMIN_MEMBER_ID,
            'branch_mode' => BestowalTodoTemplateItem::BRANCH_MODE_AWARD,
            'is_gating' => true,
            'sort_order' => 0,
        ]));

        $awards = $this->getTableLocator()->get('Awards.Awards');
        $award = $awards->find()->firstOrFail();
        $award->set('branch_id', self::KINGDOM_BRANCH_ID);
        $award->set('bestowal_todo_template_id', (int)$template->id);
        $awards->saveOrFail($award);

        $bestowals = $this->getTableLocator()->get('Awards.Bestowals');
        $bestowal = $bestowals->saveOrFail($bestowals->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'member_sca_name' => 'Deleted Owner Recipient',
            'award_id' => (int)$award->id,
            'roaming_court' => false,
            'lifecycle_status' => Bestowal::LIFECYCLE_OPEN,
            'stack_rank' => 0,
            'source' => Bestowal::SOURCE_AD_HOC,
        ]));
        $staleBestowal = clone $bestowal;

        $service = new BestowalTodoMaterializationService();
        $materialized = $service->materializeForBestowal($bestowal);
        $this->assertTrue($materialized->success, (string)$materialized->reason);
        $this->assertCount(1, $materialized->data);
        $actionItemId = (int)$materialized->data[0]->id;

        $sharedDefinition->label = 'A stale sync must not apply this title';
        $templateItems->saveOrFail($sharedDefinition);
        $templateItems->saveOrFail($templateItems->newEntity([
            'template_id' => (int)$template->id,
            'item_key' => 'deleted_owner_new',
            'label' => 'A stale sync must not create this to-do',
            'assignee_type' => BestowalTodoTemplateItem::ASSIGNEE_TYPE_MEMBER,
            'assignee_source_id' => self::ADMIN_MEMBER_ID,
            'branch_mode' => BestowalTodoTemplateItem::BRANCH_MODE_AWARD,
            'is_gating' => false,
            'sort_order' => 1,
        ]));

        $bestowals->deleteOrFail($bestowal);
        $softDeletedSync = $service->syncForBestowal($staleBestowal, self::ADMIN_MEMBER_ID);
        $this->assertTrue($softDeletedSync->success, (string)$softDeletedSync->reason);
        $this->assertTrue($softDeletedSync->data['skipped']);

        $actionItems = $this->getTableLocator()->get('ActionItems');
        $item = $actionItems->get($actionItemId);
        $this->assertSame('Original deleted-owner to-do', $item->title);
        $this->assertSame(ActionItem::STATUS_OPEN, $item->status);
        $this->assertSame(1, $actionItems->find()->where([
            'entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE,
            'entity_id' => (int)$staleBestowal->id,
        ])->count());

        $queuedCompletion = (new ActionItemService())->complete(
            $actionItemId,
            self::ADMIN_MEMBER_ID,
            'Queued after owner deletion.',
            false,
        );
        $this->assertFalse($queuedCompletion->success);
        $this->assertSame('The to-do owner is no longer active.', $queuedCompletion->reason);
        $this->assertSame(ActionItem::STATUS_OPEN, $actionItems->get($actionItemId)->status);

        $bestowals->getConnection()->delete('awards_bestowals', ['id' => (int)$staleBestowal->id]);
        $hardDeletedSync = $service->syncForBestowal($staleBestowal, self::ADMIN_MEMBER_ID);
        $this->assertTrue($hardDeletedSync->success, (string)$hardDeletedSync->reason);
        $this->assertTrue($hardDeletedSync->data['skipped']);
        $hardDeletedCancellation = (new ActionItemService())->cancel(
            $actionItemId,
            self::ADMIN_MEMBER_ID,
            'Queued after hard deletion.',
            false,
        );
        $this->assertFalse($hardDeletedCancellation->success);
        $this->assertSame('The to-do owner is no longer active.', $hardDeletedCancellation->reason);
        $this->assertSame(ActionItem::STATUS_OPEN, $actionItems->get($actionItemId)->status);
    }

    public function testSyncRemovingCompletedEventScheduledDoesNotReopenCompletedAddedToAgenda(): void
    {
        ActionItemCompletionFormRegistry::register(
            'Awards.BestowalTodoSyncLifecycleRegression',
            new BestowalTodoCompletionFormProvider(),
        );

        $templates = $this->getTableLocator()->get('Awards.BestowalTodoTemplates');
        $template = $templates->saveOrFail($templates->newEntity([
            'name' => 'Removed prerequisite regression ' . uniqid('', true),
            'description' => 'Current process intentionally omits Event Scheduled.',
            'is_active' => true,
        ]));
        $templateItems = $this->getTableLocator()->get('Awards.BestowalTodoTemplateItems');
        $templateItems->saveOrFail($templateItems->newEntity([
            'template_id' => (int)$template->id,
            'item_key' => BestowalTodoTemplateItem::ITEM_KEY_ADDED_TO_AGENDA,
            'label' => 'Added to Agenda',
            'assignee_type' => BestowalTodoTemplateItem::ASSIGNEE_TYPE_MEMBER,
            'assignee_source_id' => self::ADMIN_MEMBER_ID,
            'branch_mode' => BestowalTodoTemplateItem::BRANCH_MODE_AWARD,
            'is_gating' => true,
            'sort_order' => 20,
        ]));

        $awards = $this->getTableLocator()->get('Awards.Awards');
        $award = $awards->find()->firstOrFail();
        $award->set('branch_id', self::KINGDOM_BRANCH_ID);
        $award->set('bestowal_todo_template_id', (int)$template->id);
        $awards->saveOrFail($award);

        $bestowals = $this->getTableLocator()->get('Awards.Bestowals');
        $bestowal = $bestowals->saveOrFail($bestowals->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'member_sca_name' => 'Removed Prerequisite Recipient',
            'award_id' => (int)$award->id,
            'roaming_court' => false,
            'lifecycle_status' => Bestowal::LIFECYCLE_OPEN,
            'stack_rank' => 0,
            'source' => Bestowal::SOURCE_AD_HOC,
        ]));

        $actionItems = $this->getTableLocator()->get('ActionItems');
        $completedAt = DateTime::now();
        $eventScheduled = $actionItems->saveOrFail($actionItems->newEntity([
            'entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE,
            'entity_id' => (int)$bestowal->id,
            'title' => 'Event Scheduled',
            'assignee_type' => ActionItem::ASSIGNEE_TYPE_MEMBER,
            'assignee_config' => ['member_id' => self::ADMIN_MEMBER_ID],
            'status' => ActionItem::STATUS_COMPLETED,
            'is_gating' => true,
            'sort_order' => 10,
            'source_ref' => BestowalTodoTemplateItem::ITEM_KEY_EVENT_SCHEDULED,
            'completed_at' => $completedAt,
            'completed_by' => self::ADMIN_MEMBER_ID,
        ]));
        $addedToAgenda = $actionItems->saveOrFail($actionItems->newEntity([
            'entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE,
            'entity_id' => (int)$bestowal->id,
            'title' => 'Added to Agenda',
            'assignee_type' => ActionItem::ASSIGNEE_TYPE_MEMBER,
            'assignee_config' => ['member_id' => self::ADMIN_MEMBER_ID],
            'status' => ActionItem::STATUS_COMPLETED,
            'is_gating' => true,
            'sort_order' => 20,
            'source_ref' => BestowalTodoTemplateItem::ITEM_KEY_ADDED_TO_AGENDA,
            'completed_at' => $completedAt,
            'completed_by' => self::ADMIN_MEMBER_ID,
        ]));

        $result = (new BestowalTodoMaterializationService())->syncForBestowal(
            $bestowal,
            self::ADMIN_MEMBER_ID,
        );

        $this->assertTrue($result->success, (string)$result->reason);
        $this->assertSame(1, $result->data['cancelledCount']);
        $this->assertSame(0, $result->data['requiredReopenedCount']);
        $this->assertSame(
            ActionItem::STATUS_CANCELLED,
            $actionItems->get($eventScheduled->id)->status,
        );
        $addedToAgenda = $actionItems->get($addedToAgenda->id);
        $this->assertSame(ActionItem::STATUS_COMPLETED, $addedToAgenda->status);
        $this->assertSame(['required_fields' => []], $addedToAgenda->completion_config);
        $this->assertFalse(
            (new BestowalTodoCompletionFormProvider())->canHandle($addedToAgenda),
            'Required field None must disable the built-in agenda fallback and prerequisite.',
        );
    }

    public function testSyncCompletesSatisfiedPrerequisitesRegardlessOfDisplayOrder(): void
    {
        ActionItemCompletionFormRegistry::register(
            'Awards.BestowalTodoSyncOrderingRegression',
            new BestowalTodoCompletionFormProvider(),
        );

        $templates = $this->getTableLocator()->get('Awards.BestowalTodoTemplates');
        $template = $templates->saveOrFail($templates->newEntity([
            'name' => 'Required-field ordering regression ' . uniqid('', true),
            'is_active' => true,
        ]));
        $templateItems = $this->getTableLocator()->get('Awards.BestowalTodoTemplateItems');
        $itemDefinitions = [
            [
                'item_key' => BestowalTodoTemplateItem::ITEM_KEY_ADDED_TO_AGENDA,
                'label' => 'Added to Agenda',
                'required_field' => BestowalTodoTemplateItem::REQUIRED_FIELD_COURT_SLOT,
                'sort_order' => 1,
            ],
            [
                'item_key' => BestowalTodoTemplateItem::ITEM_KEY_EVENT_SCHEDULED,
                'label' => 'Event Scheduled',
                'required_field' => BestowalTodoTemplateItem::REQUIRED_FIELD_GATHERING,
                'sort_order' => 2,
            ],
        ];
        foreach ($itemDefinitions as $itemData) {
            $itemData += [
                'template_id' => (int)$template->id,
                'assignee_type' => BestowalTodoTemplateItem::ASSIGNEE_TYPE_MEMBER,
                'assignee_source_id' => self::ADMIN_MEMBER_ID,
                'branch_mode' => BestowalTodoTemplateItem::BRANCH_MODE_AWARD,
                'is_gating' => true,
                'required_field_config' => BestowalTodoTemplateItem::getDefaultRequiredFieldConfigForSourceRef(
                    $itemData['item_key'],
                ),
            ];
            $templateItems->saveOrFail($templateItems->newEntity($itemData));
        }

        $awards = $this->getTableLocator()->get('Awards.Awards');
        $award = $awards->find()->firstOrFail();
        $award->set('branch_id', self::KINGDOM_BRANCH_ID);
        $award->set('bestowal_todo_template_id', (int)$template->id);
        $awards->saveOrFail($award);

        $gathering = $this->getTableLocator()->get('Gatherings')->find()
            ->select(['id'])
            ->firstOrFail();
        $bestowals = $this->getTableLocator()->get('Awards.Bestowals');
        $bestowal = $bestowals->saveOrFail($bestowals->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'member_sca_name' => 'Ordering Regression Recipient',
            'award_id' => (int)$award->id,
            'gathering_id' => (int)$gathering->id,
            'roaming_court' => true,
            'lifecycle_status' => Bestowal::LIFECYCLE_OPEN,
            'stack_rank' => 0,
            'source' => Bestowal::SOURCE_AD_HOC,
        ]));

        $service = new BestowalTodoMaterializationService();
        $materialized = $service->materializeForBestowal($bestowal);
        $this->assertTrue($materialized->success, (string)$materialized->reason);
        $this->assertCount(2, $materialized->data);
        foreach ($materialized->data as $item) {
            $this->assertTrue($item->isOpen());
        }

        $result = $service->syncForBestowal($bestowal, self::ADMIN_MEMBER_ID);

        $this->assertTrue($result->success, (string)$result->reason);
        $this->assertSame(0, $result->data['createdCount']);
        $this->assertSame(2, $result->data['requiredCompletedCount']);
        $statuses = $this->getTableLocator()->get('ActionItems')->find()
            ->select(['source_ref', 'status'])
            ->where([
                'entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE,
                'entity_id' => (int)$bestowal->id,
            ])
            ->all()
            ->combine('source_ref', 'status')
            ->toArray();
        $this->assertSame(ActionItem::STATUS_COMPLETED, $statuses[BestowalTodoTemplateItem::ITEM_KEY_EVENT_SCHEDULED]);
        $this->assertSame(ActionItem::STATUS_COMPLETED, $statuses[BestowalTodoTemplateItem::ITEM_KEY_ADDED_TO_AGENDA]);
        $this->assertSame(
            Bestowal::LIFECYCLE_OPEN,
            $bestowals->get($bestowal->id)->lifecycle_status,
            'Definition synchronization may satisfy every gating to-do but must not mark the bestowal Given.',
        );
    }
}
