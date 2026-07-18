<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Config;

use App\Model\Entity\ActionItem;
use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\Bestowal;
use Cake\ORM\TableRegistry;
use RenameBestowalTodoLabels;

require_once dirname(__DIR__, 3) . '/config/Migrations/20260715215000_RenameBestowalTodoLabels.php';

/**
 * Verifies bestowal To-Do label reconciliation for restored databases.
 */
class RenameBestowalTodoLabelsTest extends BaseTestCase
{
    public function testMigrationRenamesTemplatesAndMaterializedActionItems(): void
    {
        $templates = TableRegistry::getTableLocator()->get('Awards.BestowalTodoTemplates');
        $template = $templates->saveOrFail($templates->newEntity([
            'name' => 'Rename Test ' . uniqid('', true),
            'description' => 'Migration test template',
            'is_active' => true,
        ]));
        $permission = TableRegistry::getTableLocator()->get('Permissions')
            ->find()
            ->select(['id'])
            ->firstOrFail();
        $templateItems = TableRegistry::getTableLocator()->get('Awards.BestowalTodoTemplateItems');
        foreach (
            [
                'has_scroll' => 'Has Scroll',
                'regalia_allotted' => 'Regalia Allotted For',
            ] as $itemKey => $label
        ) {
            $templateItems->saveOrFail($templateItems->newEntity([
                'template_id' => $template->id,
                'item_key' => $itemKey,
                'label' => $label,
                'assignee_type' => 'permission',
                'assignee_source_id' => $permission->id,
                'branch_mode' => 'award_branch',
                'is_gating' => true,
                'sort_order' => 1,
            ]));
        }

        $bestowals = TableRegistry::getTableLocator()->get('Awards.Bestowals');
        $award = TableRegistry::getTableLocator()->get('Awards.Awards')->find()->select(['id'])->firstOrFail();
        $bestowal = $bestowals->saveOrFail($bestowals->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'award_id' => $award->id,
            'source' => Bestowal::SOURCE_AD_HOC,
            'stack_rank' => 0,
        ]));

        $actionItems = TableRegistry::getTableLocator()->get('ActionItems');
        foreach (
            [
                'has_scroll' => 'Has Scroll',
                'regalia_allotted' => 'Regalia Allotted For',
            ] as $sourceRef => $title
        ) {
            $actionItems->saveOrFail($actionItems->newEntity([
                'entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE,
                'entity_id' => $bestowal->id,
                'title' => $title,
                'assignee_type' => ActionItem::ASSIGNEE_TYPE_MEMBER,
                'assignee_config' => ['member_id' => self::ADMIN_MEMBER_ID],
                'branch_id' => self::KINGDOM_BRANCH_ID,
                'status' => ActionItem::STATUS_OPEN,
                'is_gating' => true,
                'sort_order' => 1,
                'source_ref' => $sourceRef,
            ]));
        }

        $migration = new RenameBestowalTodoLabels(20260715215000);
        $migration->up();
        $migration->up();

        $this->assertSame(
            ['Scroll Ready'],
            $templateItems->find()
                ->select(['label'])
                ->where(['item_key' => 'has_scroll'])
                ->distinct(['label'])
                ->all()
                ->extract('label')
                ->toList(),
        );
        $this->assertSame(
            ['Insignia Ready'],
            $templateItems->find()
                ->select(['label'])
                ->where(['item_key' => 'regalia_allotted'])
                ->distinct(['label'])
                ->all()
                ->extract('label')
                ->toList(),
        );
        $this->assertSame(
            ['has_scroll' => 'Scroll Ready', 'regalia_allotted' => 'Insignia Ready'],
            $actionItems->find()
                ->select(['source_ref', 'title'])
                ->where(['entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE])
                ->where(['source_ref IN' => ['has_scroll', 'regalia_allotted']])
                ->all()
                ->combine('source_ref', 'title')
                ->toArray(),
        );
    }
}
