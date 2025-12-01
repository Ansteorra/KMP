<?php

declare(strict_types=1);

namespace App\KMP\GridColumns;

/**
 * Activity Groups Grid Column Metadata
 *
 * Defines all available columns for the Activity Groups data grid.
 * Activity Groups provide organizational structure for activities.
 */
class ActivityGroupsGridColumns extends BaseGridColumns
{
    /**
     * Get column metadata for activity groups grid
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getColumns(): array
    {
        return [
            'id' => [
                'key' => 'id',
                'label' => 'ID',
                'type' => 'number',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => false,
                'width' => '80px',
                'alignment' => 'right',
            ],

            'name' => [
                'key' => 'name',
                'label' => 'Name',
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'searchable' => true,
                'defaultVisible' => true,
                'required' => true,
                'width' => '300px',
                'alignment' => 'left',
                'clickAction' => 'navigate:/activities/activity-groups/view/:id',
            ],

            'activity_count' => [
                'key' => 'activity_count',
                'label' => 'Activities',
                'type' => 'number',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => true,
                'width' => '100px',
                'alignment' => 'center',
            ],

            'created' => [
                'key' => 'created',
                'label' => 'Created',
                'type' => 'datetime',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => false,
                'width' => '150px',
                'alignment' => 'left',
            ],

            'modified' => [
                'key' => 'modified',
                'label' => 'Modified',
                'type' => 'datetime',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => false,
                'width' => '150px',
                'alignment' => 'left',
            ],
        ];
    }
}
