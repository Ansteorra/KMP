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
        $this->assertSame(self::KINGDOM_BRANCH_ID, (int)$memberItem->branch_id);
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
        $award = $this->awardsTable->find()->where(['bestowal_todo_template_id IS' => null])->first();
        $this->assertNotNull($award, 'Expected a seed award without a to-do template.');
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
