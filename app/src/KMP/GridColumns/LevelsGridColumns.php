<?php

declare(strict_types=1);

namespace App\KMP\GridColumns;

/**
 * Grid column definitions for the Award Levels index view
 * Used by Awards\Controller\LevelsController::gridData()
 */
class LevelsGridColumns extends BaseGridColumns
{
    /**
     * Get column metadata for the levels grid
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
                'clickAction' => 'navigate:/awards/levels/view/:id',
            ],
            'progression_order' => [
                'key' => 'progression_order',
                'label' => __('Order'),
                'type' => 'number',
                'sortable' => true,
                'filterable' => false,
                'searchable' => false,
                'defaultVisible' => true,
                'width' => '80px',
                'alignment' => 'center',
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
