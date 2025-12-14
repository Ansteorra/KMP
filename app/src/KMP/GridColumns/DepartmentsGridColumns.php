<?php

declare(strict_types=1);

namespace App\KMP\GridColumns;

/**
 * Grid column definitions for the Departments index view
 * Used by Officers\Controller\DepartmentsController::gridData()
 */
class DepartmentsGridColumns extends BaseGridColumns
{
    /**
     * Get column metadata for the departments grid
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getColumns(): array
    {
        return [
            'name' => [
                'key' => 'name',
                'label' => __('Name'),
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'searchable' => true,
                'defaultVisible' => true,
                'required' => true,
                'width' => '200px',
                'alignment' => 'left',
                'clickAction' => 'navigate:/officers/departments/view/:id',
            ],
            'created' => [
                'key' => 'created',
                'label' => __('Created'),
                'type' => 'datetime',
                'sortable' => true,
                'filterable' => false,
                'searchable' => false,
                'defaultVisible' => false,
                'width' => '150px',
                'alignment' => 'left',
            ],
            'modified' => [
                'key' => 'modified',
                'label' => __('Modified'),
                'type' => 'datetime',
                'sortable' => true,
                'filterable' => false,
                'searchable' => false,
                'defaultVisible' => false,
                'width' => '150px',
                'alignment' => 'left',
            ],
        ];
    }
}
