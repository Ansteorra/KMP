<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Command;

use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\BestowalTodoTemplateItem;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\ORM\Table;

/**
 * Coverage for the `awards materialize_bestowal_todos` onboarding command:
 * open bestowals whose award carries a to-do template get their checklist
 * materialized, while cancelled bestowals are skipped.
 */
class MaterializeBestowalTodosCommandTest extends BaseTestCase
{
    use ConsoleIntegrationTestTrait;

    private Table $awardsTable;
    private Table $templatesTable;
    private Table $itemsTable;
    private Table $bestowalsTable;
    private Table $actionItemsTable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->awardsTable = $this->getTableLocator()->get('Awards.Awards');
        $this->templatesTable = $this->getTableLocator()->get('Awards.BestowalTodoTemplates');
        $this->itemsTable = $this->getTableLocator()->get('Awards.BestowalTodoTemplateItems');
        $this->bestowalsTable = $this->getTableLocator()->get('Awards.Bestowals');
        $this->actionItemsTable = $this->getTableLocator()->get('ActionItems');
    }

    public function testCommandMaterializesOpenBestowalChecklist(): void
    {
        $templateId = $this->createTemplateWithItems();
        $awardId = $this->assignTemplateToAward($templateId);
        $bestowalId = $this->createBestowal($awardId, Bestowal::LIFECYCLE_OPEN);

        $this->exec('awards materialize_bestowal_todos --bestowal-id ' . $bestowalId);

        $this->assertExitSuccess();
        $this->assertOutputContains('materialized 2 new to-do item(s)');
        $this->assertCount(2, $this->loadActionItems($bestowalId));
    }

    public function testCommandSkipsCancelledBestowals(): void
    {
        $templateId = $this->createTemplateWithItems();
        $awardId = $this->assignTemplateToAward($templateId);
        $bestowalId = $this->createBestowal($awardId, Bestowal::LIFECYCLE_CANCELLED);

        $this->exec('awards materialize_bestowal_todos --bestowal-id ' . $bestowalId);

        $this->assertExitSuccess();
        $this->assertOutputContains('Processed 0 bestowal(s)');
        $this->assertCount(0, $this->loadActionItems($bestowalId));
    }

    private function createTemplateWithItems(): int
    {
        $template = $this->templatesTable->newEntity([
            'name' => 'Command Test Template ' . uniqid(),
            'description' => 'Parallel checks for command tests.',
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
            'is_gating' => false,
            'sort_order' => 0,
        ]);
        $this->assertNotFalse($this->itemsTable->save($memberItem), json_encode($memberItem->getErrors()));

        $gatingItem = $this->itemsTable->newEntity([
            'template_id' => $template->id,
            'item_key' => 'given',
            'label' => 'Given',
            'assignee_type' => BestowalTodoTemplateItem::ASSIGNEE_TYPE_PERMISSION,
            'assignee_source_id' => self::SUPER_USER_PERMISSION_ID,
            'branch_mode' => BestowalTodoTemplateItem::BRANCH_MODE_AWARD,
            'is_gating' => true,
            'sort_order' => 1,
        ]);
        $this->assertNotFalse($this->itemsTable->save($gatingItem), json_encode($gatingItem->getErrors()));

        return (int)$template->id;
    }

    private function assignTemplateToAward(int $templateId): int
    {
        $award = $this->awardsTable->find()->first();
        $this->assertNotNull($award, 'Expected at least one seed award.');
        $award->set('branch_id', self::KINGDOM_BRANCH_ID);
        $award->set('bestowal_todo_template_id', $templateId);
        $this->assertNotFalse($this->awardsTable->save($award), json_encode($award->getErrors()));

        return (int)$award->id;
    }

    private function createBestowal(int $awardId, string $lifecycleStatus): int
    {
        $bestowal = $this->bestowalsTable->newEmptyEntity();
        $bestowal->set('award_id', $awardId);
        $bestowal->set('lifecycle_status', $lifecycleStatus);
        $bestowal->set('member_sca_name', 'Command Test Recipient');
        $bestowal->set('stack_rank', 0);
        $bestowal->set('source', 'test');
        $bestowal->set('roaming_court', false);
        $this->bestowalsTable->saveOrFail($bestowal, ['validate' => false, 'checkRules' => false]);

        return (int)$bestowal->id;
    }

    /**
     * @param int $bestowalId Owner bestowal id.
     * @return list<\App\Model\Entity\ActionItem>
     */
    private function loadActionItems(int $bestowalId): array
    {
        return $this->actionItemsTable->find()
            ->where([
                'entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE,
                'entity_id' => $bestowalId,
            ])
            ->all()
            ->toList();
    }
}
