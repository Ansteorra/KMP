<?php
declare(strict_types=1);

namespace App\KMP\GridColumns;

use App\Model\Entity\GatheringActivity;
use App\View\AppView;

/**
 * Gathering Activities Grid Column Metadata
 *
 * Defines all available columns for the Gathering Activities data grid.
 * Includes each activity’s configured appearance alongside its name and display order.
 */
class GatheringActivitiesGridColumns extends BaseGridColumns
{
    /** @return array<string, array<string, mixed>> */
    public static function getRowActions(): array
    {
        return [
            'view' => [
                'key' => 'view', 'type' => 'link', 'label' => 'View', 'icon' => 'eye',
                'class' => 'btn btn-sm btn-outline-secondary', 'permission' => 'view',
                'ariaLabel' => 'View {{name}}',
                'url' => ['controller' => 'GatheringActivities', 'action' => 'view', 'idField' => 'id'],
            ],
            'edit' => [
                'key' => 'edit', 'type' => 'modal', 'label' => 'Edit', 'icon' => 'edit',
                'class' => 'btn btn-sm btn-primary', 'permission' => 'edit',
                'ariaLabel' => 'Edit {{name}}', 'modalTarget' => '#gatheringActivityModal',
                'dataAttributes' => ['activity-id' => '{{id}}'],
            ],
        ];
    }

    /**
     * Get column metadata for gathering activities grid
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

            'icon' => [
                'key' => 'icon',
                'label' => 'Icon',
                'type' => 'custom',
                'sortable' => false,
                'filterable' => false,
                'searchable' => false,
                'exportable' => false,
                'defaultVisible' => true,
                'width' => '70px',
                'alignment' => 'center',
                'cellRenderer' => static function ($value, GatheringActivity $row, AppView $view): string {
                    return $view->GatheringActivity->badge($row, true);
                },
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
                'width' => '200px',
                'alignment' => 'left',
                'clickAction' => 'navigate:/gathering-activities/view/:id',
            ],

            'activity_type' => [
                'key' => 'activity_type',
                'label' => 'Activity Type',
                'type' => 'string',
                'sortable' => true,
                'searchable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'filterOptions' => array_map(
                    static fn(string $type): array => ['value' => $type, 'label' => $type],
                    GatheringActivity::ACTIVITY_TYPES,
                ),
                'defaultVisible' => true,
                'width' => '160px',
                'alignment' => 'left',
            ],

            'display_order' => [
                'key' => 'display_order',
                'label' => 'Display order',
                'type' => 'number',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => true,
                'width' => '130px',
                'alignment' => 'right',
            ],

            'description' => [
                'key' => 'description',
                'label' => 'Description',
                'type' => 'string',
                'sortable' => false,
                'filterable' => false,
                'searchable' => true,
                'defaultVisible' => true,
                'width' => '500px',
                'alignment' => 'left',
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
