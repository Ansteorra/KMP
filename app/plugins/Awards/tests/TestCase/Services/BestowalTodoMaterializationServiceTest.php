<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\Model\Entity\ActionItem;
use App\Services\ActionItems\ActionItemService;
use App\Services\ServiceResult;
use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\BestowalTodoTemplateItem;
use Awards\Services\BestowalTodoAssigneeResolver;
use Awards\Services\BestowalTodoMaterializationService;
use Cake\I18n\DateTime;
use Cake\ORM\Table;

/**
 * End-to-end coverage for materializing a bestowal's parallel to-do checklist
 * from its award's assigned template into core ActionItems.
 */
class BestowalTodoMaterializationServiceTest extends BaseTestCase
{
    private Table $awardsTable;
    private Table $templatesTable;
    private Table $itemsTable;
    private Table $actionItemsTable;
    private BestowalTodoMaterializationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->awardsTable = $this->getTableLocator()->get('Awards.Awards');
        $this->templatesTable = $this->getTableLocator()->get('Awards.BestowalTodoTemplates');
        $this->itemsTable = $this->getTableLocator()->get('Awards.BestowalTodoTemplateItems');
        $this->actionItemsTable = $this->getTableLocator()->get('ActionItems');
        $this->service = new BestowalTodoMaterializationService();
    }

    public function testMaterializeCreatesActionItemsFromTemplate(): void
    {
        $templateId = $this->createTemplateWithItems();
        $awardId = $this->assignTemplateToAward($templateId, self::KINGDOM_BRANCH_ID);
        $bestowalId = 9000001;
        $bestowal = $this->buildBestowal($bestowalId, $awardId);

        $result = $this->service->materializeForBestowal($bestowal);

        $this->assertTrue($result->success, (string)$result->reason);
        $this->assertCount(2, $result->data);

        $items = $this->loadActionItems($bestowalId);
        $this->assertCount(2, $items);

        $memberItem = $items['scroll_assigned'];
        $this->assertSame(ActionItem::ASSIGNEE_TYPE_MEMBER, $memberItem->assignee_type);
        $this->assertSame(self::TEST_MEMBER_AGATHA_ID, (int)$memberItem->assignee_config['member_id']);
        $this->assertNull($memberItem->branch_id);
        $this->assertTrue((bool)$memberItem->is_gating);
        $this->assertSame(ActionItem::STATUS_OPEN, $memberItem->status);

        $permissionItem = $items['scroll_finished'];
        $this->assertSame(ActionItem::ASSIGNEE_TYPE_DYNAMIC, $permissionItem->assignee_type);
        $this->assertSame(
            BestowalTodoAssigneeResolver::class,
            $permissionItem->assignee_config['service'],
        );
        $this->assertSame('resolveMemberIds', $permissionItem->assignee_config['method']);
        $this->assertSame(
            BestowalTodoTemplateItem::ASSIGNEE_TYPE_PERMISSION,
            $permissionItem->assignee_config['kind'],
        );
        $this->assertSame(
            self::SUPER_USER_PERMISSION_ID,
            (int)$permissionItem->assignee_config['source_id'],
        );
        $this->assertSame(self::KINGDOM_BRANCH_ID, (int)$permissionItem->branch_id);
        $this->assertFalse((bool)$permissionItem->is_gating);
    }

    public function testMaterializeCopiesRequiredFieldCompletionConfig(): void
    {
        $templateId = $this->createTemplateWithRequiredGatheringItem();
        $awardId = $this->assignTemplateToAward($templateId, self::KINGDOM_BRANCH_ID);
        $bestowalId = 9000004;
        $bestowal = $this->buildBestowal($bestowalId, $awardId);

        $result = $this->service->materializeForBestowal($bestowal);

        $this->assertTrue($result->success, (string)$result->reason);
        $items = $this->loadActionItems($bestowalId);
        $eventScheduled = $items['event_scheduled'];
        $requiredFields = $eventScheduled->getRequiredFieldConfigs();
        $this->assertCount(1, $requiredFields);
        $this->assertSame(BestowalTodoTemplateItem::REQUIRED_FIELD_GATHERING, $requiredFields[0]['field']);
        $this->assertSame(
            BestowalTodoTemplateItem::COMPLETION_PROVIDER_BESTOWAL_GATHERING,
            $requiredFields[0]['provider'],
        );
        $this->assertTrue($requiredFields[0]['conditional_complete_on_assign']);
        $this->assertTrue($eventScheduled->canAutoCompleteWhenRequirementsSatisfied());
    }

    public function testMaterializeScopesRolePermissionAndOfficeItemsToAwardBranch(): void
    {
        $templateId = $this->createTemplateWithScopedAssigneeItems();
        $awardId = $this->assignTemplateToAward($templateId, self::KINGDOM_BRANCH_ID);
        $bestowalId = 9000005;
        $bestowal = $this->buildBestowal($bestowalId, $awardId);

        $this->assertSame(self::KINGDOM_BRANCH_ID, $bestowal->getBranchId());
        $result = $this->service->materializeForBestowal($bestowal);

        $this->assertTrue($result->success, (string)$result->reason);
        $items = $this->loadActionItems($bestowalId);
        foreach (['role_scope', 'permission_scope', 'office_scope'] as $sourceRef) {
            $this->assertArrayHasKey($sourceRef, $items);
            $this->assertSame(ActionItem::ASSIGNEE_TYPE_DYNAMIC, $items[$sourceRef]->assignee_type);
            $this->assertSame(self::KINGDOM_BRANCH_ID, (int)$items[$sourceRef]->branch_id);
            $this->assertSame(
                BestowalTodoAssigneeResolver::class,
                $items[$sourceRef]->assignee_config['service'],
            );
        }
        $this->assertSame(BestowalTodoTemplateItem::ASSIGNEE_TYPE_ROLE, $items['role_scope']->assignee_config['kind']);
        $this->assertSame(
            BestowalTodoTemplateItem::ASSIGNEE_TYPE_PERMISSION,
            $items['permission_scope']->assignee_config['kind'],
        );
        $this->assertSame(
            BestowalTodoTemplateItem::ASSIGNEE_TYPE_OFFICE,
            $items['office_scope']->assignee_config['kind'],
        );
    }

    public function testMaterializeIsIdempotentOnSourceRef(): void
    {
        $templateId = $this->createTemplateWithItems();
        $awardId = $this->assignTemplateToAward($templateId, self::KINGDOM_BRANCH_ID);
        $bestowalId = 9000002;
        $bestowal = $this->buildBestowal($bestowalId, $awardId);

        $first = $this->service->materializeForBestowal($bestowal);
        $this->assertTrue($first->success, (string)$first->reason);
        $this->assertCount(2, $first->data);

        $second = $this->service->materializeForBestowal($bestowal);
        $this->assertTrue($second->success, (string)$second->reason);
        $this->assertCount(0, $second->data, 'Re-materializing must not duplicate items.');

        $this->assertCount(2, $this->loadActionItems($bestowalId));
    }

    public function testMaterializeIsNoOpWhenAwardHasNoTemplate(): void
    {
        $award = $this->awardsTable->find()->orderByAsc('id')->first();
        $this->assertNotNull($award, 'Expected at least one seed award.');
        $this->awardsTable->updateAll(
            ['bestowal_todo_template_id' => null],
            ['id' => $award->id],
        );
        $bestowalId = 9000003;
        $bestowal = $this->buildBestowal($bestowalId, (int)$award->id);

        $result = $this->service->materializeForBestowal($bestowal);

        $this->assertTrue($result->success, (string)$result->reason);
        $this->assertSame([], $result->data);
        $this->assertCount(0, $this->loadActionItems($bestowalId));
    }

    public function testMaterializeIsNoOpForGivenBestowal(): void
    {
        $templateId = $this->createTemplateWithItems();
        $awardId = $this->assignTemplateToAward($templateId, self::KINGDOM_BRANCH_ID);
        $bestowal = $this->createPersistedBestowal(
            $awardId,
            Bestowal::LIFECYCLE_GIVEN,
            'Given Materialization Guard',
        );

        $result = $this->service->materializeForBestowal($bestowal);

        $this->assertTrue($result->success, (string)$result->reason);
        $this->assertSame([], $result->data);
        $this->assertCount(0, $this->loadActionItems((int)$bestowal->id));
    }

    public function testSyncForBestowalReconcilesCurrentInactiveTemplateAndIsIdempotent(): void
    {
        $templateId = $this->createTemplateWithItems();
        $awardId = $this->assignTemplateToAward($templateId, self::KINGDOM_BRANCH_ID);
        $bestowalId = 9000010;
        $bestowal = $this->buildBestowal($bestowalId, $awardId);
        $materialized = $this->service->materializeForBestowal($bestowal);
        $this->assertTrue($materialized->success, (string)$materialized->reason);

        $existing = $this->loadActionItems($bestowalId);
        $completed = $existing['scroll_assigned'];
        $completed->status = ActionItem::STATUS_COMPLETED;
        $completed->completed_at = DateTime::now();
        $completed->completed_by = self::ADMIN_MEMBER_ID;
        $this->actionItemsTable->saveOrFail($completed);
        $completedAt = $completed->completed_at;

        $templateItems = $this->itemsTable->find()
            ->where(['template_id' => $templateId])
            ->all()
            ->combine('item_key', fn($item) => $item)
            ->toArray();
        $keptDefinition = $templateItems['scroll_assigned'];
        $keptDefinition->label = 'Updated scroll assignment';
        $keptDefinition->assignee_source_id = self::ADMIN_MEMBER_ID;
        $keptDefinition->is_gating = false;
        $this->itemsTable->saveOrFail($keptDefinition);
        $this->itemsTable->deleteOrFail($templateItems['scroll_finished']);

        $addedDefinition = $this->itemsTable->newEntity([
            'template_id' => $templateId,
            'item_key' => 'presentation_planned',
            'label' => 'Presentation planned',
            'assignee_type' => BestowalTodoTemplateItem::ASSIGNEE_TYPE_MEMBER,
            'assignee_source_id' => self::TEST_MEMBER_AGATHA_ID,
            'branch_mode' => BestowalTodoTemplateItem::BRANCH_MODE_AWARD,
            'is_gating' => true,
            'sort_order' => 2,
        ]);
        $this->itemsTable->saveOrFail($addedDefinition);
        $template = $this->templatesTable->get($templateId);
        $template->is_active = false;
        $this->templatesTable->saveOrFail($template);

        $result = $this->service->syncForBestowal($bestowal, self::ADMIN_MEMBER_ID);

        $this->assertTrue($result->success, (string)$result->reason);
        $this->assertFalse($result->data['skipped']);
        $this->assertSame($templateId, $result->data['templateId']);
        $this->assertSame(1, $result->data['createdCount']);
        $this->assertSame(1, $result->data['updatedCount']);
        $this->assertSame(1, $result->data['cancelledCount']);

        $synced = $this->loadActionItems($bestowalId);
        $this->assertSame('Updated scroll assignment', $synced['scroll_assigned']->title);
        $this->assertTrue($synced['scroll_assigned']->isCompleted());
        $this->assertEquals($completedAt, $synced['scroll_assigned']->completed_at);
        $this->assertSame(self::ADMIN_MEMBER_ID, (int)$synced['scroll_assigned']->completed_by);
        $this->assertSame(self::ADMIN_MEMBER_ID, (int)$synced['scroll_assigned']->assignee_lookup_id);
        $this->assertSame(ActionItem::STATUS_CANCELLED, $synced['scroll_finished']->status);
        $this->assertTrue($synced['presentation_planned']->isOpen());

        $retiredLogCount = $this->getTableLocator()->get('ActionItemLogs')->find()
            ->where(['action_item_id' => (int)$synced['scroll_finished']->id])
            ->count();
        $second = $this->service->syncForBestowal($bestowal, self::ADMIN_MEMBER_ID);
        $this->assertTrue($second->success, (string)$second->reason);
        $this->assertSame(0, $second->data['createdCount']);
        $this->assertSame(0, $second->data['updatedCount']);
        $this->assertSame(0, $second->data['cancelledCount']);
        $this->assertSame(0, $second->data['reopenedCount']);
        $this->assertSame(
            $retiredLogCount,
            $this->getTableLocator()->get('ActionItemLogs')->find()
                ->where(['action_item_id' => (int)$synced['scroll_finished']->id])
                ->count(),
        );
    }

    public function testSyncForBestowalSwitchesTemplatesWhilePreservingStableItemHistory(): void
    {
        $originalTemplateId = $this->createTemplateWithItems();
        $awardId = $this->assignTemplateToAward($originalTemplateId, self::KINGDOM_BRANCH_ID);
        $bestowal = $this->createPersistedBestowal(
            $awardId,
            Bestowal::LIFECYCLE_OPEN,
            'Template Switch',
        );
        $materialized = $this->service->materializeForBestowal($bestowal);
        $this->assertTrue($materialized->success, (string)$materialized->reason);

        $originalItems = $this->loadActionItems((int)$bestowal->id);
        $sharedId = (int)$originalItems['scroll_assigned']->id;
        $removedId = (int)$originalItems['scroll_finished']->id;
        $completeResult = (new ActionItemService())->complete(
            $sharedId,
            self::ADMIN_MEMBER_ID,
            'Completed before the process changed.',
            false,
        );
        $this->assertTrue($completeResult->success, (string)$completeResult->reason);
        $completedBeforeSync = $this->actionItemsTable->get($sharedId);
        $completedAt = $completedBeforeSync->completed_at;
        $sharedLogCount = $this->countActionItemLogs($sharedId);

        $replacementTemplateId = $this->createTemplateFromDefinitions([
            [
                'item_key' => 'presentation_planned',
                'label' => 'Presentation planned',
                'assignee_source_id' => self::TEST_MEMBER_AGATHA_ID,
                'is_gating' => true,
                'sort_order' => 0,
            ],
            [
                'item_key' => 'scroll_assigned',
                'label' => 'Scroll assignment confirmed',
                'description' => 'Use the current reign process.',
                'assignee_source_id' => self::ADMIN_MEMBER_ID,
                'is_gating' => false,
                'sort_order' => 1,
            ],
        ]);
        $award = $this->awardsTable->get($awardId);
        $award->bestowal_todo_template_id = $replacementTemplateId;
        $this->awardsTable->saveOrFail($award);

        $result = $this->service->syncForBestowal($bestowal, self::ADMIN_MEMBER_ID);

        $this->assertTrue($result->success, (string)$result->reason);
        $this->assertSame($replacementTemplateId, $result->data['templateId']);
        $this->assertSame(1, $result->data['createdCount']);
        $this->assertSame(1, $result->data['updatedCount']);
        $this->assertSame(1, $result->data['cancelledCount']);
        $this->assertSame(0, $result->data['reopenedCount']);

        $synced = $this->loadActionItems((int)$bestowal->id);
        $this->assertCount(3, $synced, 'Cancelled history remains alongside the current two-item definition.');
        $this->assertSame($sharedId, (int)$synced['scroll_assigned']->id);
        $this->assertSame('Scroll assignment confirmed', $synced['scroll_assigned']->title);
        $this->assertSame('Use the current reign process.', $synced['scroll_assigned']->description);
        $this->assertSame(1, (int)$synced['scroll_assigned']->sort_order);
        $this->assertFalse((bool)$synced['scroll_assigned']->is_gating);
        $this->assertTrue($synced['scroll_assigned']->isCompleted());
        $this->assertEquals($completedAt, $synced['scroll_assigned']->completed_at);
        $this->assertSame(self::ADMIN_MEMBER_ID, (int)$synced['scroll_assigned']->completed_by);
        $this->assertSame(
            $sharedLogCount,
            $this->countActionItemLogs($sharedId),
            'Refreshing the shared definition must not rewrite its completion history.',
        );

        $this->assertSame($removedId, (int)$synced['scroll_finished']->id);
        $this->assertSame(ActionItem::STATUS_CANCELLED, $synced['scroll_finished']->status);
        $this->assertSame(
            ActionItemService::SYSTEM_DEFINITION_SYNC_CANCEL_NOTE,
            $this->latestActionItemLogNote($removedId),
        );
        $this->assertSame(0, (int)$synced['presentation_planned']->sort_order);
        $this->assertTrue($synced['presentation_planned']->isOpen());
        $createdId = (int)$synced['presentation_planned']->id;
        $logCountAfterSync = $this->countActionItemLogsForBestowal((int)$bestowal->id);
        $this->assertSame(
            Bestowal::LIFECYCLE_OPEN,
            $this->getTableLocator()->get('Awards.Bestowals')->get($bestowal->id)->lifecycle_status,
        );

        $second = $this->service->syncForBestowal($bestowal, self::ADMIN_MEMBER_ID);

        $this->assertTrue($second->success, (string)$second->reason);
        $this->assertSame(0, $second->data['createdCount']);
        $this->assertSame(0, $second->data['updatedCount']);
        $this->assertSame(0, $second->data['cancelledCount']);
        $this->assertSame(0, $second->data['reopenedCount']);
        $afterSecondSync = $this->loadActionItems((int)$bestowal->id);
        $this->assertSame($sharedId, (int)$afterSecondSync['scroll_assigned']->id);
        $this->assertSame($removedId, (int)$afterSecondSync['scroll_finished']->id);
        $this->assertSame($createdId, (int)$afterSecondSync['presentation_planned']->id);
        $this->assertSame($logCountAfterSync, $this->countActionItemLogsForBestowal((int)$bestowal->id));
        $this->assertSame(
            Bestowal::LIFECYCLE_OPEN,
            $this->getTableLocator()->get('Awards.Bestowals')->get($bestowal->id)->lifecycle_status,
        );
    }

    public function testSyncForBestowalReopensSameActionItemAfterTemplateItemDeleteAndRestore(): void
    {
        $templateId = $this->createTemplateWithItems();
        $awardId = $this->assignTemplateToAward($templateId, self::KINGDOM_BRANCH_ID);
        $bestowal = $this->createPersistedBestowal(
            $awardId,
            Bestowal::LIFECYCLE_OPEN,
            'Definition Restore',
        );
        $materialized = $this->service->materializeForBestowal($bestowal);
        $this->assertTrue($materialized->success, (string)$materialized->reason);

        $initialItems = $this->loadActionItems((int)$bestowal->id);
        $returnedActionItemId = (int)$initialItems['scroll_finished']->id;
        $templateItem = $this->itemsTable->find()
            ->where([
                'template_id' => $templateId,
                'item_key' => 'scroll_finished',
            ])
            ->firstOrFail();
        $templateItemId = (int)$templateItem->id;
        $this->itemsTable->deleteOrFail($templateItem);

        $removed = $this->service->syncForBestowal($bestowal, self::ADMIN_MEMBER_ID);

        $this->assertTrue($removed->success, (string)$removed->reason);
        $this->assertSame(1, $removed->data['cancelledCount']);
        $cancelled = $this->loadActionItems((int)$bestowal->id)['scroll_finished'];
        $this->assertSame($returnedActionItemId, (int)$cancelled->id);
        $this->assertSame(ActionItem::STATUS_CANCELLED, $cancelled->status);
        $this->assertSame(
            ActionItemService::SYSTEM_DEFINITION_SYNC_CANCEL_NOTE,
            $this->latestActionItemLogNote($returnedActionItemId),
        );

        $deletedTemplateItem = $this->itemsTable->find('withTrashed')
            ->where(['id' => $templateItemId])
            ->firstOrFail();
        $this->assertNotNull($deletedTemplateItem->deleted);
        $deletedTemplateItem->deleted = null;
        $this->itemsTable->saveOrFail($deletedTemplateItem);

        $returned = $this->service->syncForBestowal($bestowal, self::ADMIN_MEMBER_ID);

        $this->assertTrue($returned->success, (string)$returned->reason);
        $this->assertSame(0, $returned->data['createdCount']);
        $this->assertSame(1, $returned->data['reopenedCount']);
        $reopened = $this->loadActionItems((int)$bestowal->id)['scroll_finished'];
        $this->assertSame($returnedActionItemId, (int)$reopened->id);
        $this->assertTrue($reopened->isOpen());
        $this->assertSame(
            1,
            $this->actionItemsTable->find()
                ->where([
                    'entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE,
                    'entity_id' => (int)$bestowal->id,
                    'source_ref' => 'scroll_finished',
                ])
                ->count(),
            'A restored template row must reuse the existing ActionItem instead of creating a duplicate.',
        );
        $this->assertSame(
            ActionItemService::SYSTEM_DEFINITION_SYNC_REOPEN_NOTE,
            $this->latestActionItemLogNote($returnedActionItemId),
        );
        $logCountAfterReopen = $this->countActionItemLogs($returnedActionItemId);

        $third = $this->service->syncForBestowal($bestowal, self::ADMIN_MEMBER_ID);

        $this->assertTrue($third->success, (string)$third->reason);
        $this->assertSame(0, $third->data['createdCount']);
        $this->assertSame(0, $third->data['updatedCount']);
        $this->assertSame(0, $third->data['cancelledCount']);
        $this->assertSame(0, $third->data['reopenedCount']);
        $this->assertSame($logCountAfterReopen, $this->countActionItemLogs($returnedActionItemId));
        $this->assertSame(
            $returnedActionItemId,
            (int)$this->loadActionItems((int)$bestowal->id)['scroll_finished']->id,
        );
        $this->assertSame(
            Bestowal::LIFECYCLE_OPEN,
            $this->getTableLocator()->get('Awards.Bestowals')->get($bestowal->id)->lifecycle_status,
        );
    }

    public function testSyncForBestowalTreatsEmptyTemplateAsAuthoritative(): void
    {
        $template = $this->templatesTable->saveOrFail($this->templatesTable->newEntity([
            'name' => 'Empty Sync Template ' . uniqid(),
            'description' => 'Intentionally empty.',
            'is_active' => true,
        ]));
        $awardId = $this->assignTemplateToAward((int)$template->id, self::KINGDOM_BRANCH_ID);
        $bestowalId = 9000011;
        $bestowal = $this->buildBestowal($bestowalId, $awardId);
        $existing = $this->actionItemsTable->saveOrFail($this->actionItemsTable->newEntity([
            'entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE,
            'entity_id' => $bestowalId,
            'title' => 'Existing preparation',
            'assignee_type' => ActionItem::ASSIGNEE_TYPE_MEMBER,
            'assignee_config' => ['member_id' => self::TEST_MEMBER_AGATHA_ID],
            'status' => ActionItem::STATUS_OPEN,
            'is_gating' => true,
            'sort_order' => 0,
            'source_ref' => 'existing_preparation',
        ]));

        $result = $this->service->syncForBestowal($bestowal, self::ADMIN_MEMBER_ID);

        $this->assertTrue($result->success, (string)$result->reason);
        $this->assertFalse($result->data['skipped']);
        $this->assertSame(1, $result->data['cancelledCount']);
        $this->assertSame(ActionItem::STATUS_CANCELLED, $this->actionItemsTable->get($existing->id)->status);
        $this->assertSame(1, $this->getTableLocator()->get('ActionItemLogs')->find()
            ->where(['action_item_id' => (int)$existing->id])
            ->count());

        $second = $this->service->syncForBestowal($bestowal, self::ADMIN_MEMBER_ID);
        $this->assertTrue($second->success, (string)$second->reason);
        $this->assertSame(0, $second->data['cancelledCount']);
    }

    public function testSyncForBestowalRollsBackWhenRequiredFieldPassFails(): void
    {
        $templateId = $this->createTemplateWithItems();
        $awardId = $this->assignTemplateToAward($templateId, self::KINGDOM_BRANCH_ID);
        $bestowalId = 9000012;
        $bestowal = $this->buildBestowal($bestowalId, $awardId);
        $actionItemService = $this->getMockBuilder(ActionItemService::class)
            ->onlyMethods(['synchronizeFor', 'syncRequiredFieldCompletionStates'])
            ->getMock();
        $actionItemService->expects($this->once())
            ->method('synchronizeFor')
            ->willReturnCallback(function () use ($bestowalId): ServiceResult {
                $this->actionItemsTable->saveOrFail($this->actionItemsTable->newEntity([
                    'entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE,
                    'entity_id' => $bestowalId,
                    'title' => 'Must roll back',
                    'assignee_type' => ActionItem::ASSIGNEE_TYPE_MEMBER,
                    'assignee_config' => ['member_id' => self::ADMIN_MEMBER_ID],
                    'status' => ActionItem::STATUS_OPEN,
                    'is_gating' => true,
                    'sort_order' => 0,
                    'source_ref' => 'must_roll_back',
                ]));

                return new ServiceResult(true, null, [
                    'createdCount' => 1,
                    'updatedCount' => 0,
                    'cancelledCount' => 0,
                    'reopenedCount' => 0,
                    'unchangedCount' => 0,
                    'requiredCompletedCount' => 1,
                    'requiredReopenedCount' => 0,
                    'requiredSkippedCount' => 0,
                ]);
            });
        $actionItemService->expects($this->once())
            ->method('syncRequiredFieldCompletionStates')
            ->willReturn(new ServiceResult(false, 'Required-field reconciliation failed.'));

        $result = (new BestowalTodoMaterializationService($actionItemService))
            ->syncForBestowal($bestowal, self::ADMIN_MEMBER_ID);

        $this->assertFalse($result->success);
        $this->assertSame(
            'Bestowal to-do synchronization failed. Review server logs for details.',
            $result->reason,
        );
        $this->assertStringNotContainsString('Required-field reconciliation failed.', $result->reason);
        $this->assertCount(0, $this->loadActionItems($bestowalId));
    }

    public function testSyncForBestowalAccumulatesSkippedRequiredFieldChecksAcrossPasses(): void
    {
        $templateId = $this->createTemplateWithItems();
        $awardId = $this->assignTemplateToAward($templateId, self::KINGDOM_BRANCH_ID);
        $bestowal = $this->createPersistedBestowal(
            $awardId,
            Bestowal::LIFECYCLE_OPEN,
            'Required Skip Accumulation',
        );
        $actionItemService = $this->getMockBuilder(ActionItemService::class)
            ->onlyMethods(['synchronizeFor', 'syncRequiredFieldCompletionStates'])
            ->getMock();
        $actionItemService->expects($this->once())
            ->method('synchronizeFor')
            ->willReturn(new ServiceResult(true, null, [
                'createdCount' => 0,
                'updatedCount' => 0,
                'cancelledCount' => 0,
                'reopenedCount' => 0,
                'unchangedCount' => 2,
                'requiredCompletedCount' => 1,
                'requiredReopenedCount' => 0,
                'requiredSkippedCount' => 1,
            ]));
        $actionItemService->expects($this->exactly(2))
            ->method('syncRequiredFieldCompletionStates')
            ->willReturnOnConsecutiveCalls(
                new ServiceResult(true, null, [
                    'completedCount' => 1,
                    'reopenedCount' => 0,
                    'skippedCount' => 2,
                ]),
                new ServiceResult(true, null, [
                    'completedCount' => 0,
                    'reopenedCount' => 0,
                    'skippedCount' => 3,
                ]),
            );

        $result = (new BestowalTodoMaterializationService($actionItemService))
            ->syncForBestowal($bestowal, self::ADMIN_MEMBER_ID);

        $this->assertTrue($result->success, (string)$result->reason);
        $this->assertSame(6, $result->data['requiredSkippedCount']);
    }

    public function testSyncTemplateProcessesOpenLifecycleOnly(): void
    {
        $templateId = $this->createTemplateWithItems();
        $awardId = $this->createAwardForTemplate($templateId, 'Scoped Lifecycle Sync');
        $open = $this->createPersistedBestowal($awardId, Bestowal::LIFECYCLE_OPEN, 'Bulk Open');
        $given = $this->createPersistedBestowal($awardId, Bestowal::LIFECYCLE_GIVEN, 'Bulk Given');
        $cancelled = $this->createPersistedBestowal($awardId, Bestowal::LIFECYCLE_CANCELLED, 'Bulk Cancelled');

        $result = $this->service->syncOpenBestowalsForTemplate($templateId, self::ADMIN_MEMBER_ID);

        $this->assertTrue($result->success, (string)$result->reason);
        $this->assertSame(1, $result->data['processedCount']);
        $this->assertSame(1, $result->data['changedCount']);
        $this->assertCount($result->data['skippedCount'], $result->data['skips']);
        foreach ($result->data['skips'] as $skip) {
            $this->assertGreaterThan(0, (int)$skip['bestowalId']);
            $this->assertNotSame('', (string)$skip['reason']);
        }
        $this->assertCount(2, $this->loadActionItems((int)$open->id));
        $this->assertCount(0, $this->loadActionItems((int)$given->id));
        $this->assertCount(0, $this->loadActionItems((int)$cancelled->id));
    }

    public function testTemplateScopedSyncCountsAndProcessesOnlyOutdatedOpenBestowals(): void
    {
        $templateAId = $this->createTemplateWithItems();
        $templateBId = $this->createTemplateWithItems();
        $awardAId = $this->createAwardForTemplate($templateAId, 'Template A Scoped Sync');
        $awardBId = $this->createAwardForTemplate($templateBId, 'Template B Scoped Sync');
        $openA = $this->createPersistedBestowal($awardAId, Bestowal::LIFECYCLE_OPEN, 'Open A');
        $openB = $this->createPersistedBestowal($awardBId, Bestowal::LIFECYCLE_OPEN, 'Open B');
        $givenA = $this->createPersistedBestowal($awardAId, Bestowal::LIFECYCLE_GIVEN, 'Given A');

        $this->assertTrue($this->service->materializeForBestowal($openA)->success);
        $this->assertTrue($this->service->materializeForBestowal($openB)->success);
        $bestowals = $this->getTableLocator()->get('Awards.Bestowals');
        $this->assertNotNull($bestowals->get($openA->id)->todo_template_signature);
        $this->assertNotNull($bestowals->get($openB->id)->todo_template_signature);
        $this->assertSame(0, $this->service->countOutdatedOpenBestowals($templateAId));
        $this->assertSame(0, $this->service->countOutdatedOpenBestowals($templateBId));

        $templateAItem = $this->itemsTable->find()
            ->where(['template_id' => $templateAId, 'item_key' => 'scroll_assigned'])
            ->firstOrFail();
        $templateAItem->label = 'Updated scroll assignment';
        $this->itemsTable->saveOrFail($templateAItem);
        $bestowals->updateAll(['todo_template_signature' => null], ['id' => (int)$givenA->id]);

        $this->assertSame(1, $this->service->countOutdatedOpenBestowals($templateAId));
        $this->assertSame(0, $this->service->countOutdatedOpenBestowals($templateBId));

        $result = $this->service->syncOpenBestowalsForTemplate($templateAId, self::ADMIN_MEMBER_ID);

        $this->assertTrue($result->success, (string)$result->reason);
        $this->assertSame(1, $result->data['candidateCount']);
        $this->assertSame(1, $result->data['processedCount']);
        $this->assertSame(1, $result->data['changedCount']);
        $this->assertSame(0, $this->service->countOutdatedOpenBestowals($templateAId));
        $this->assertSame(0, $this->service->countOutdatedOpenBestowals($templateBId));
        $this->assertSame('Updated scroll assignment', $this->loadActionItems((int)$openA->id)['scroll_assigned']->title);
        $this->assertSame('Scroll assigned', $this->loadActionItems((int)$openB->id)['scroll_assigned']->title);
        $this->assertCount(0, $this->loadActionItems((int)$givenA->id));
    }

    public function testTemplateScopedSyncRepairsLegacyBestowalWithNoStoredSignature(): void
    {
        $templateId = $this->createTemplateWithItems();
        $awardId = $this->createAwardForTemplate($templateId, 'Legacy Signature Repair');
        $bestowal = $this->createPersistedBestowal(
            $awardId,
            Bestowal::LIFECYCLE_OPEN,
            'Legacy Signature Repair',
        );
        $this->assertTrue($this->service->materializeForBestowal($bestowal)->success);
        $bestowals = $this->getTableLocator()->get('Awards.Bestowals');
        $bestowals->updateAll(['todo_template_signature' => null], ['id' => (int)$bestowal->id]);

        $this->assertSame(1, $this->service->countOutdatedOpenBestowals($templateId));

        $result = $this->service->syncOpenBestowalsForTemplate($templateId, self::ADMIN_MEMBER_ID);

        $this->assertTrue($result->success, (string)$result->reason);
        $this->assertSame(1, $result->data['processedCount']);
        $this->assertSame(1, $result->data['unchangedCount']);
        $this->assertNotNull($bestowals->get($bestowal->id)->todo_template_signature);
        $this->assertSame(0, $this->service->countOutdatedOpenBestowals($templateId));
    }

    public function testTransactionalOperationsRestoreDisabledSavePointConfiguration(): void
    {
        $connection = $this->awardsTable->getConnection();
        $savePointsWereEnabled = $connection->isSavePointsEnabled();
        $connection->disableSavePoints();

        try {
            $templateId = $this->createTemplateWithItems();
            $awardId = $this->assignTemplateToAward($templateId, self::KINGDOM_BRANCH_ID);
            $bestowal = $this->buildBestowal(9000010, $awardId);

            $materializeResult = $this->service->materializeForBestowal($bestowal);
            $this->assertTrue($materializeResult->success, (string)$materializeResult->reason);
            $this->assertFalse($connection->isSavePointsEnabled());

            $syncResult = $this->service->syncForBestowal($bestowal, self::ADMIN_MEMBER_ID);
            $this->assertTrue($syncResult->success, (string)$syncResult->reason);
            $this->assertFalse($connection->isSavePointsEnabled());

            $bulkResult = $this->service->syncOpenBestowalsForTemplate(
                $templateId,
                self::ADMIN_MEMBER_ID,
            );
            $this->assertTrue($bulkResult->success, (string)$bulkResult->reason);
            $this->assertFalse($connection->isSavePointsEnabled());
        } finally {
            if ($savePointsWereEnabled) {
                $connection->enableSavePoints();
            } else {
                $connection->disableSavePoints();
            }
        }
    }

    private function createTemplateWithItems(): int
    {
        $template = $this->templatesTable->newEntity([
            'name' => 'Materialization Test Template ' . uniqid(),
            'description' => 'Parallel checks for tests.',
            'is_active' => true,
        ]);
        $this->assertNotFalse($this->templatesTable->save($template), json_encode($template->getErrors()));

        $memberItem = $this->itemsTable->newEntity([
            'template_id' => $template->id,
            'item_key' => 'scroll_assigned',
            'label' => 'Scroll assigned',
            'assignee_type' => BestowalTodoTemplateItem::ASSIGNEE_TYPE_MEMBER,
            'assignee_source_id' => self::TEST_MEMBER_AGATHA_ID,
            'branch_mode' => BestowalTodoTemplateItem::BRANCH_MODE_AWARD,
            'is_gating' => true,
            'sort_order' => 0,
        ]);
        $this->assertNotFalse($this->itemsTable->save($memberItem), json_encode($memberItem->getErrors()));

        $permissionItem = $this->itemsTable->newEntity([
            'template_id' => $template->id,
            'item_key' => 'scroll_finished',
            'label' => 'Scroll finished',
            'assignee_type' => BestowalTodoTemplateItem::ASSIGNEE_TYPE_PERMISSION,
            'assignee_source_id' => self::SUPER_USER_PERMISSION_ID,
            'branch_mode' => BestowalTodoTemplateItem::BRANCH_MODE_AWARD,
            'is_gating' => false,
            'sort_order' => 1,
        ]);
        $this->assertNotFalse($this->itemsTable->save($permissionItem), json_encode($permissionItem->getErrors()));

        return (int)$template->id;
    }

    /**
     * @param array<int, array<string, mixed>> $definitions Template item definitions.
     */
    private function createTemplateFromDefinitions(array $definitions): int
    {
        $template = $this->templatesTable->saveOrFail($this->templatesTable->newEntity([
            'name' => 'Replacement Materialization Template ' . uniqid(),
            'description' => 'A changed process used to verify in-flight synchronization.',
            'is_active' => true,
        ]));

        foreach ($definitions as $definition) {
            $this->itemsTable->saveOrFail($this->itemsTable->newEntity($definition + [
                'template_id' => (int)$template->id,
                'assignee_type' => BestowalTodoTemplateItem::ASSIGNEE_TYPE_MEMBER,
                'branch_mode' => BestowalTodoTemplateItem::BRANCH_MODE_AWARD,
            ]));
        }

        return (int)$template->id;
    }

    private function createTemplateWithRequiredGatheringItem(): int
    {
        $template = $this->templatesTable->newEntity([
            'name' => 'Required Gathering Template ' . uniqid(),
            'description' => 'Event scheduled requires a gathering.',
            'is_active' => true,
        ]);
        $this->assertNotFalse($this->templatesTable->save($template), json_encode($template->getErrors()));

        $item = $this->itemsTable->newEntity([
            'template_id' => $template->id,
            'item_key' => 'event_scheduled',
            'label' => 'Event Scheduled',
            'assignee_type' => BestowalTodoTemplateItem::ASSIGNEE_TYPE_MEMBER,
            'assignee_source_id' => self::TEST_MEMBER_AGATHA_ID,
            'branch_mode' => BestowalTodoTemplateItem::BRANCH_MODE_AWARD,
            'is_gating' => false,
            'required_field' => BestowalTodoTemplateItem::REQUIRED_FIELD_GATHERING,
            'required_field_config' => [
                'provider' => BestowalTodoTemplateItem::COMPLETION_PROVIDER_BESTOWAL_GATHERING,
                'conditional_complete_on_assign' => true,
                ActionItem::COMPLETION_CONFIG_AUTO_COMPLETE => true,
            ],
            'sort_order' => 0,
        ]);
        $this->assertNotFalse($this->itemsTable->save($item), json_encode($item->getErrors()));

        return (int)$template->id;
    }

    private function createTemplateWithScopedAssigneeItems(): int
    {
        $template = $this->templatesTable->newEntity([
            'name' => 'Scoped Assignees Template ' . uniqid(),
            'description' => 'Role, permission, and office scoping.',
            'is_active' => true,
        ]);
        $this->assertNotFalse($this->templatesTable->save($template), json_encode($template->getErrors()));

        $role = $this->getTableLocator()->get('Roles')->find()->select(['id'])->firstOrFail();
        $permission = $this->getTableLocator()->get('Permissions')->find()->select(['id'])->firstOrFail();
        $office = $this->getTableLocator()->get('Officers.Offices')->find()->select(['id'])->firstOrFail();
        $definitions = [
            [
                'item_key' => 'role_scope',
                'label' => 'Role scoped',
                'assignee_type' => BestowalTodoTemplateItem::ASSIGNEE_TYPE_ROLE,
                'assignee_source_id' => (int)$role->id,
            ],
            [
                'item_key' => 'permission_scope',
                'label' => 'Permission scoped',
                'assignee_type' => BestowalTodoTemplateItem::ASSIGNEE_TYPE_PERMISSION,
                'assignee_source_id' => (int)$permission->id,
            ],
            [
                'item_key' => 'office_scope',
                'label' => 'Office scoped',
                'assignee_type' => BestowalTodoTemplateItem::ASSIGNEE_TYPE_OFFICE,
                'assignee_source_id' => (int)$office->id,
            ],
        ];
        foreach ($definitions as $index => $definition) {
            $item = $this->itemsTable->newEntity($definition + [
                'template_id' => (int)$template->id,
                'branch_mode' => BestowalTodoTemplateItem::BRANCH_MODE_AWARD,
                'is_gating' => false,
                'sort_order' => $index,
            ]);
            $this->assertNotFalse($this->itemsTable->save($item), json_encode($item->getErrors()));
        }

        return (int)$template->id;
    }

    private function assignTemplateToAward(int $templateId, int $branchId): int
    {
        $award = $this->awardsTable->find()->first();
        $this->assertNotNull($award, 'Expected at least one seed award.');
        $award->set('branch_id', $branchId);
        $award->set('bestowal_todo_template_id', $templateId);
        $this->assertNotFalse($this->awardsTable->save($award), json_encode($award->getErrors()));

        return (int)$award->id;
    }

    private function createAwardForTemplate(int $templateId, string $name): int
    {
        $award = $this->awardsTable->saveOrFail($this->awardsTable->newEntity([
            'name' => $name . ' ' . uniqid('', true),
            'abbreviation' => strtoupper(substr(md5(uniqid('', true)), 0, 8)),
            'domain_id' => 2,
            'level_id' => 1,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'bestowal_todo_template_id' => $templateId,
            'is_active' => true,
        ]));

        return (int)$award->id;
    }

    private function buildBestowal(int $bestowalId, int $awardId): Bestowal
    {
        $bestowals = $this->getTableLocator()->get('Awards.Bestowals');
        $bestowal = $bestowals->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'member_sca_name' => 'Materialization Test Recipient ' . $bestowalId,
            'award_id' => $awardId,
            'lifecycle_status' => Bestowal::LIFECYCLE_OPEN,
            'stack_rank' => 0,
            'source' => Bestowal::SOURCE_AD_HOC,
        ]);
        $bestowal->id = $bestowalId;

        return $bestowals->saveOrFail($bestowal);
    }

    private function createPersistedBestowal(int $awardId, string $lifecycleStatus, string $name): Bestowal
    {
        $table = $this->getTableLocator()->get('Awards.Bestowals');

        return $table->saveOrFail($table->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'member_sca_name' => $name . ' ' . uniqid(),
            'award_id' => $awardId,
            'lifecycle_status' => $lifecycleStatus,
            'stack_rank' => 0,
            'source' => Bestowal::SOURCE_AD_HOC,
        ]));
    }

    /**
     * @param int $bestowalId Owner bestowal id.
     * @return array<string, \App\Model\Entity\ActionItem> Items keyed by source_ref.
     */
    private function loadActionItems(int $bestowalId): array
    {
        $rows = $this->actionItemsTable->find()
            ->where([
                'entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE,
                'entity_id' => $bestowalId,
            ])
            ->all();

        $keyed = [];
        foreach ($rows as $row) {
            $keyed[(string)$row->source_ref] = $row;
        }

        return $keyed;
    }

    private function countActionItemLogs(int $actionItemId): int
    {
        return $this->getTableLocator()->get('ActionItemLogs')->find()
            ->where(['action_item_id' => $actionItemId])
            ->count();
    }

    private function countActionItemLogsForBestowal(int $bestowalId): int
    {
        $actionItemIds = $this->actionItemsTable->find()
            ->select(['id'])
            ->where([
                'entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE,
                'entity_id' => $bestowalId,
            ]);

        return $this->getTableLocator()->get('ActionItemLogs')->find()
            ->where(['action_item_id IN' => $actionItemIds])
            ->count();
    }

    private function latestActionItemLogNote(int $actionItemId): string
    {
        return (string)$this->getTableLocator()->get('ActionItemLogs')->find()
            ->select(['note'])
            ->where(['action_item_id' => $actionItemId])
            ->orderByDesc('id')
            ->firstOrFail()
            ->note;
    }
}
