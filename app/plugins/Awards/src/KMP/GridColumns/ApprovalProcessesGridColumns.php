<?php
declare(strict_types=1);

namespace Awards\KMP\GridColumns;

use App\KMP\GridColumns\BaseGridColumns;

class ApprovalProcessesGridColumns extends BaseGridColumns
{
    /**
     * Get approval process grid column metadata.
     *
     * @return array
     */
    public static function getColumns(): array
    {
        return [
            'name' => [
                'key' => 'name',
                'label' => __('Name'),
                'type' => 'string',
                'sortable' => true,
                'filterable' => false,
                'searchable' => true,
                'defaultVisible' => true,
                'required' => true,
                'clickAction' => 'navigate:/awards/approval-processes/view/:id',
            ],
            'is_active' => [
                'key' => 'is_active',
                'label' => __('Active'),
                'type' => 'boolean',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'queryField' => 'ApprovalProcesses.is_active',
                'defaultVisible' => true,
                'filterOptions' => [
                    ['value' => '1', 'label' => 'Yes'],
                    ['value' => '0', 'label' => 'No'],
                ],
            ],
            'step_summary' => [
                'key' => 'step_summary',
                'label' => __('Steps'),
                'type' => 'string',
                'sortable' => false,
                'filterable' => false,
                'searchable' => false,
                'defaultVisible' => true,
            ],
            'description' => [
                'key' => 'description',
                'label' => __('Description'),
                'type' => 'string',
                'sortable' => false,
                'filterable' => false,
                'searchable' => true,
                'defaultVisible' => false,
            ],
        ];
    }
}
