<?php
declare(strict_types=1);

namespace App\KMP\GridColumns;

/** Fictional gathering metadata rendered by the real Dataverse components. */
class UiPatternGatheringsGridColumns extends BaseGridColumns
{
    /** @return array<string, array<string, mixed>> */
    public static function getColumns(): array
    {
        $columns = [
            'name' => ['label' => 'Gathering', 'required' => true, 'searchable' => true],
            'start_date' => ['label' => 'Date', 'filterable' => true, 'filterType' => 'date-range'],
            'branch' => ['label' => 'Branch', 'searchable' => true, 'filterQueryField' => 'branch_key'],
            'activity' => ['label' => 'Activity', 'searchable' => true, 'filterQueryField' => 'activity_key'],
            'royal_progress' => ['label' => 'Royal Progress', 'searchable' => true,
                'filterQueryField' => 'royal_progress_key'],
            'status' => ['label' => 'Status', 'filterQueryField' => 'status_key'],
            'location' => ['label' => 'Location', 'searchable' => true, 'defaultVisible' => false],
            'rsvps' => ['label' => 'RSVPs', 'type' => 'number', 'alignment' => 'right', 'defaultVisible' => false],
        ];
        foreach ($columns as $key => &$column) {
            $column += ['key' => $key, 'type' => 'string', 'sortable' => true, 'defaultVisible' => true];
            if (isset($column['filterQueryField'])) {
                $column += ['filterable' => true, 'filterType' => 'dropdown'];
            }
        }
        unset($column);
        $columns['name']['cellRenderer'] = static function ($value, $row, $view): string {
            if ($row['id'] === 'winter-court') {
                return $view->Html->link($value, [
                    'plugin' => null, 'controller' => 'UiPatterns', 'action' => 'reader',
                ], ['data-turbo-frame' => '_top', 'class' => 'fw-semibold']);
            }

            return '<span class="fw-semibold">' . h($value) . '</span>';
        };

        return $columns;
    }

    /** @return array<string, array<string, mixed>> */
    public static function getSystemViews(array $options = []): array
    {
        return [
            'all' => ['id' => 'all', 'name' => 'All gatherings', 'config' => []],
            'published' => ['id' => 'published', 'name' => 'Published', 'config' => [
                'filters' => [['field' => 'status', 'operator' => 'in', 'value' => ['published']]],
            ]],
            'royal-progress' => ['id' => 'royal-progress', 'name' => 'Royal Progress', 'config' => [
                'filters' => [['field' => 'royal_progress', 'operator' => 'in',
                    'value' => ['crown-ansteorra', 'coronet-oakmere', 'coronet-silvermere']]],
            ]],
            'drafts' => ['id' => 'drafts', 'name' => 'Drafts', 'config' => [
                'filters' => [['field' => 'status', 'operator' => 'in', 'value' => ['draft']]],
            ]],
        ];
    }
}
