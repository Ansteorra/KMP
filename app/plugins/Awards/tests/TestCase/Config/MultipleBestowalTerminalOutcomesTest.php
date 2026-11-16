<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Config;

use AllowMultipleBestowalTerminalOutcomes;
use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\BestowalTodoTemplateItem;
use Migrations\Migration\Environment;

require_once dirname(__DIR__, 3) . '/config/Migrations/20261105010000_AllowMultipleBestowalTerminalOutcomes.php';

class MultipleBestowalTerminalOutcomesTest extends BaseTestCase
{
    public function testMigrationSeedsAnsteorraDefaultsWithoutReplacingCustomOrDeletedDefinitions(): void
    {
        $templates = $this->getTableLocator()->get('Awards.BestowalTodoTemplates');
        $items = $this->getTableLocator()->get('Awards.BestowalTodoTemplateItems');
        $ids = [];
        foreach (['Kingdom Award', 'Baronial Award', 'Custom process'] as $name) {
            $templates->updateAll(['name' => 'Original ' . $name], ['name' => $name]);
            $template = $templates->saveOrFail($templates->newEntity(['name' => $name, 'is_active' => true]));
            $ids[$name] = (int)$template->id;
            $items->saveOrFail($items->newEntity([
                'template_id' => $template->id, 'item_key' => 'given', 'label' => 'Presented',
                'is_terminal' => true, 'is_gating' => true, 'sort_order' => 50,
                'assignee_type' => BestowalTodoTemplateItem::ASSIGNEE_TYPE_MEMBER,
                'assignee_source_id' => self::TEST_MEMBER_AGATHA_ID,
                'branch_mode' => BestowalTodoTemplateItem::BRANCH_MODE_ANCESTOR_TYPE,
                'branch_type' => 'Kingdom',
            ]));
        }
        $custom = $items->saveOrFail($items->newEntity([
            'template_id' => $ids['Baronial Award'], 'item_key' => 'given_not_received', 'label' => 'Keep our definition',
            'is_terminal' => false, 'is_gating' => false, 'sort_order' => 40,
            'assignee_type' => BestowalTodoTemplateItem::ASSIGNEE_TYPE_MEMBER,
            'assignee_source_id' => self::ADMIN_MEMBER_ID,
            'branch_mode' => BestowalTodoTemplateItem::BRANCH_MODE_AWARD,
        ]));
        $items->deleteOrFail($custom);
        $this->migration()->up();
        $this->migration()->up();
        $added = $items->find()->where([
            'template_id' => $ids['Kingdom Award'], 'item_key' => 'given_not_received',
        ])->firstOrFail();
        $this->assertTrue($added->is_terminal);
        $this->assertFalse($added->is_gating);
        $this->assertSame('Given Not Received', $added->terminal_status);
        $this->assertSame(self::TEST_MEMBER_AGATHA_ID, $added->assignee_source_id);
        $this->assertSame(BestowalTodoTemplateItem::BRANCH_MODE_ANCESTOR_TYPE, $added->branch_mode);
        $this->assertSame('Kingdom', $added->branch_type);
        $this->assertSame(1, $items->find()->where([
            'template_id' => $ids['Kingdom Award'], 'item_key' => 'given_not_received',
        ])->count());
        $preserved = $items->find('onlyTrashed')->where(['id' => $custom->id])->firstOrFail();
        $this->assertSame('Keep our definition', $preserved->label);
        $this->assertFalse($preserved->is_terminal);
        $this->assertSame(0, $items->find()->where([
            'template_id' => $ids['Custom process'], 'item_key' => 'given_not_received',
        ])->count());
    }

    public function testMigrationDoesNotSeedOtherKingdoms(): void
    {
        $this->getTableLocator()->get('Branches')->updateAll(['name' => 'Another Kingdom'], ['name' => 'Ansteorra']);
        $templates = $this->getTableLocator()->get('Awards.BestowalTodoTemplates');
        $templates->updateAll(['name' => 'Original Kingdom Award'], ['name' => 'Kingdom Award']);
        $template = $templates->saveOrFail($templates->newEntity(['name' => 'Kingdom Award', 'is_active' => true]));
        $items = $this->getTableLocator()->get('Awards.BestowalTodoTemplateItems');
        $items->saveOrFail($items->newEntity([
            'template_id' => $template->id, 'item_key' => 'given', 'label' => 'Given',
            'is_terminal' => true, 'is_gating' => true, 'sort_order' => 50,
            'assignee_type' => BestowalTodoTemplateItem::ASSIGNEE_TYPE_MEMBER,
            'assignee_source_id' => self::ADMIN_MEMBER_ID,
            'branch_mode' => BestowalTodoTemplateItem::BRANCH_MODE_AWARD,
        ]));
        $before = $items->find('withTrashed')->count();
        $this->migration()->up();
        $this->assertSame($before, $items->find('withTrashed')->count());
    }

    private function migration(): AllowMultipleBestowalTerminalOutcomes
    {
        $environment = new Environment('multiple-bestowal-outcomes-test', ['connection' => 'test']);

        return (new AllowMultipleBestowalTerminalOutcomes(20261105010000))->setAdapter($environment->getAdapter());
    }
}
