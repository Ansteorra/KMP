<?php

declare(strict_types=1);

namespace App\KMP\GridColumns;

/**
 * Gatherings Grid Column Metadata
 *
 * Defines shared columns for gathering listings and calendar grid integration.
 */
class GatheringsGridColumns extends BaseGridColumns
{
    /**
     * Get row actions for gatherings grid listings
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getRowActions(): array
    {
        return [
            'view' => [
                'key' => 'view',
                'type' => 'link',
                'label' => '',
                'icon' => 'bi-binoculars',
                'class' => 'btn btn-sm btn-secondary',
                'permission' => 'view',
                'url' => [
                    'controller' => 'Gatherings',
                    'action' => 'view',
                    'idField' => 'public_id',
                ],
            ],
            'edit' => [
                'key' => 'edit',
                'type' => 'link',
                'label' => '',
                'icon' => 'bi-pencil-fill',
                'class' => 'btn btn-sm btn-primary',
                'permission' => 'edit',
                'url' => [
                    'controller' => 'Gatherings',
                    'action' => 'edit',
                    'idField' => 'id',
                ],
            ],
        ];
    }

    /**
     * Get column metadata for the gatherings grid
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

            'public_id' => [
                'key' => 'public_id',
                'label' => 'Public ID',
                'type' => 'string',
                'sortable' => false,
                'filterable' => false,
                'defaultVisible' => false,
                'width' => '120px',
                'alignment' => 'left',
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
                'width' => '260px',
                'alignment' => 'left',
                'clickAction' => 'navigate:/gatherings/view/:public_id',
            ],

            'branch_id' => [
                'key' => 'branch_id',
                'label' => 'Branch',
                'type' => 'relation',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'filterOptionsSource' => 'branches',
                'defaultVisible' => true,
                'width' => '220px',
                'alignment' => 'left',
                'renderField' => 'branch.name',
                'queryField' => 'Branches.name',
            ],

            'gathering_type_id' => [
                'key' => 'gathering_type_id',
                'label' => 'Type',
                'type' => 'relation',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'filterOptionsSource' => 'gathering-types',
                'defaultVisible' => true,
                'width' => '180px',
                'alignment' => 'left',
                'renderField' => 'gathering_type.name',
                'queryField' => 'GatheringTypes.name',
            ],

            'start_date' => [
                'key' => 'start_date',
                'label' => 'Start',
                'type' => 'datetime',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date-range',
                'defaultVisible' => true,
                'width' => '170px',
                'alignment' => 'left',
            ],

            'end_date' => [
                'key' => 'end_date',
                'label' => 'End',
                'type' => 'datetime',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date-range',
                'defaultVisible' => true,
                'width' => '170px',
                'alignment' => 'left',
            ],

            'location' => [
                'key' => 'location',
                'label' => 'Location',
                'type' => 'string',
                'sortable' => false,
                'filterable' => true,
                'searchable' => true,
                'defaultVisible' => false,
                'width' => '240px',
                'alignment' => 'left',
            ],

            'activity_filter' => [
                'key' => 'activity_filter',
                'label' => 'Activity',
                'type' => 'relation',
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'dropdown',
                'filterOptionsSource' => 'gathering-activities',
                'defaultVisible' => false,
                'alignment' => 'left',
                'queryField' => 'GatheringActivities.id',
            ],

            'activity_count' => [
                'key' => 'activity_count',
                'label' => '# Activities',
                'type' => 'number',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => false,
                'width' => '120px',
                'alignment' => 'center',
            ],

            'created' => [
                'key' => 'created',
                'label' => 'Created',
                'type' => 'datetime',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => false,
                'width' => '170px',
                'alignment' => 'left',
            ],

            'modified' => [
                'key' => 'modified',
                'label' => 'Updated',
                'type' => 'datetime',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => false,
                'width' => '170px',
                'alignment' => 'left',
            ],
        ];
    }

    /**
     * Gatherings searchable columns definition
     *
     * @return array<int, string>
     */
    public static function getSearchableColumns(): array
    {
        return ['name', 'location'];
    }
}
