<?php
declare(strict_types=1);

use Awards\Model\Entity\Bestowal;
use Cake\ORM\TableRegistry;
use Migrations\BaseMigration;

/**
 * Apply the current bestowal To-Do labels to restored and existing data.
 */
class RenameBestowalTodoLabels extends BaseMigration
{
    private const LABELS = [
        'has_scroll' => [
            'old' => 'Has Scroll',
            'new' => 'Scroll Ready',
        ],
        'regalia_allotted' => [
            'old' => 'Regalia Allotted For',
            'new' => 'Insignia Ready',
        ],
    ];

    /**
     * @return void
     */
    public function up(): void
    {
        $this->applyLabels('new');
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->applyLabels('old');
    }

    private function applyLabels(string $version): void
    {
        $templateItems = TableRegistry::getTableLocator()->get('Awards.BestowalTodoTemplateItems');
        $actionItems = TableRegistry::getTableLocator()->get('ActionItems');

        foreach (self::LABELS as $itemKey => $labels) {
            $label = $labels[$version];
            $templateItems->updateAll(['label' => $label], ['item_key' => $itemKey]);
            $actionItems->updateAll(
                ['title' => $label],
                [
                    'entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE,
                    'source_ref' => $itemKey,
                ],
            );
        }
    }
}
