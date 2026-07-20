<?php
declare(strict_types=1);

namespace Awards\Model\Entity;

use App\Model\Entity\BaseEntity;

/**
 * Bestowal to-do template configuration.
 *
 * A reusable, named checklist assigned to awards. When a bestowal is created
 * for an award, the award's template items are materialized into parallel
 * action items (to-dos).
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property int|null $branch_id
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 * @property int|null $created_by
 * @property int|null $modified_by
 * @property \Cake\I18n\DateTime|null $deleted
 * @property \Awards\Model\Entity\BestowalTodoTemplateItem[] $bestowal_todo_template_items
 */
class BestowalTodoTemplate extends BaseEntity
{
    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'name' => true,
        'description' => true,
        'is_active' => true,
        'branch_id' => true,
        'created' => true,
        'modified' => true,
        'created_by' => true,
        'modified_by' => true,
        'deleted' => true,
        'bestowal_todo_template_items' => true,
    ];

    /**
     * Summarize configured items for grids and detail views.
     *
     * @return string
     */
    protected function _getItemSummary(): string
    {
        $items = $this->bestowal_todo_template_items ?? [];
        if ($items === []) {
            return (string)__('No to-do items configured');
        }

        $labels = [];
        foreach ($items as $item) {
            $labels[] = (string)$item->label;
        }

        return implode(', ', $labels);
    }
}
