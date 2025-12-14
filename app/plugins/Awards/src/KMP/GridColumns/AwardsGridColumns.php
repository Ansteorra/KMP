<?php

declare(strict_types=1);

namespace Awards\KMP\GridColumns;

use App\KMP\GridColumns\BaseGridColumns;

/**
 * Grid column definitions for the Awards index view
 * Used by Awards\Controller\AwardsController::gridData()
 */
class AwardsGridColumns extends BaseGridColumns
{
    /**
     * Get column metadata for the awards grid
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
                'clickAction' => 'navigate:/awards/awards/view/:id',
            ],
            'domain_name' => [
                'key' => 'domain_name',
                'label' => __('Domain'),
                'type' => 'string',
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'dropdown',
                'searchable' => true,
                'defaultVisible' => true,
                'width' => '150px',
                'alignment' => 'left',
            ],
            'level_name' => [
                'key' => 'level_name',
                'label' => __('Level'),
                'type' => 'string',
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'dropdown',
                'searchable' => true,
                'defaultVisible' => true,
                'width' => '150px',
                'alignment' => 'left',
            ],
            'branch_name' => [
                'key' => 'branch_name',
                'label' => __('Branch'),
                'type' => 'string',
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'dropdown',
                'searchable' => true,
                'defaultVisible' => true,
                'width' => '150px',
                'alignment' => 'left',
            ],
            'description' => [
                'key' => 'description',
                'label' => __('Description'),
                'type' => 'string',
                'sortable' => false,
                'filterable' => false,
                'searchable' => true,
                'defaultVisible' => false,
                'width' => '250px',
                'alignment' => 'left',
            ],
        ];
    }
}
