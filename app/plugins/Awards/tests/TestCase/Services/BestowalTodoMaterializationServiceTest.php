<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\Model\Entity\ActionItem;
use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\BestowalTodoTemplateItem;
use Awards\Services\BestowalTodoAssigneeResolver;
use Awards\Services\BestowalTodoMaterializationService;
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

    private function buildBestowal(int $bestowalId, int $awardId): Bestowal
    {
        $bestowal = $this->getTableLocator()->get('Awards.Bestowals')->newEmptyEntity();
        $bestowal->id = $bestowalId;
        $bestowal->award_id = $awardId;

        return $bestowal;
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
}
