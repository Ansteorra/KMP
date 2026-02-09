<?php

declare(strict_types=1);

namespace Waivers\KMP\GridColumns;

use App\KMP\GridColumns\BaseGridColumns;

/**
 * Gathering Waivers Grid Column Metadata
 *
 * Defines the column configuration for the GatheringWaivers Dataverse-style grid view.
 */
class GatheringWaiversGridColumns extends BaseGridColumns
{
    /**
     * Get row action configurations for gathering waivers grid
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getRowActions(): array
    {
        return [];
    }

    /**
     * Get column metadata for gathering waivers grid
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

            'gathering_id' => [
                'key' => 'gathering_id',
                'label' => 'Gathering',
                'type' => 'relation',
                'sortable' => true,
                'filterable' => false,
                'searchable' => true,
                'defaultVisible' => true,
                'width' => '250px',
                'alignment' => 'left',
                'renderField' => 'gathering.name',
                'queryField' => 'Gatherings.name',
                'description' => 'The gathering this waiver is associated with',
                'clickAction' => 'navigate:waivers/gathering-waivers/view/:id',
                'clickActionPermission' => 'view',
            ],

            'branch_id' => [
                'key' => 'branch_id',
                'label' => 'Branch',
                'type' => 'relation',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'filterOptionsSource' => 'Branches',
                'searchable' => true,
                'defaultVisible' => true,
                'width' => '180px',
                'alignment' => 'left',
                'renderField' => 'gathering.branch.name',
                'queryField' => 'Branches.name',
                'filterQueryField' => 'Gatherings.branch_id',
                'description' => 'The branch hosting the gathering',
            ],

            'waiver_type_id' => [
                'key' => 'waiver_type_id',
                'label' => 'Waiver Type',
                'type' => 'relation',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'filterOptionsSource' => [
                    'table' => 'Waivers.WaiverTypes',
                    'conditions' => ['is_active' => true],
                ],
                'defaultVisible' => true,
                'width' => '180px',
                'alignment' => 'left',
                'renderField' => 'waiver_type.name',
                'queryField' => 'WaiverTypes.name',
                'description' => 'The type of waiver submitted',

            ],

            'status' => [
                'key' => 'status',
                'label' => 'Status',
                'type' => 'badge',
                'sortable' => false,
                'filterable' => false,
                'defaultVisible' => true,
                'width' => '120px',
                'alignment' => 'center',
                'description' => 'Computed status: active, declined, or pending retention',
            ],

            'retention_date' => [
                'key' => 'retention_date',
                'label' => 'Retention Until',
                'type' => 'date',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date-range',
                'defaultVisible' => true,
                'width' => '140px',
                'alignment' => 'left',
                'description' => 'Date until which the waiver must be retained',
            ],

            'gathering_start_date' => [
                'key' => 'gathering_start_date',
                'label' => 'Event Date',
                'type' => 'relation',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date-range',
                'defaultVisible' => true,
                'width' => '140px',
                'alignment' => 'left',
                'renderField' => 'gathering.start_date',
                'queryField' => 'Gatherings.start_date',
                'description' => 'Event start date',
            ],

            'declined_at' => [
                'key' => 'declined_at',
                'label' => 'Declined At',
                'type' => 'datetime',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => false,
                'width' => '150px',
                'alignment' => 'left',
                'description' => 'When the waiver was declined',
            ],

            'created' => [
                'key' => 'created',
                'label' => 'Uploaded',
                'type' => 'datetime',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date-range',
                'defaultVisible' => true,
                'width' => '150px',
                'alignment' => 'left',
                'description' => 'When the waiver was uploaded',
            ],

            'actions' => [
                'key' => 'actions',
                'label' => '',
                'type' => 'actions',
                'required' => true,
                'defaultVisible' => true,
                'sortable' => false,
                'exportable' => false,
                'width' => '100px',
                'alignment' => 'right',
            ],
        ];
    }

    /**
     * Get system views for gathering waivers
     *
     * @param array<string, mixed> $options Optional parameters (e.g., branch filtering)
     * @return array<string, array<string, mixed>>
     */
    public static function getSystemViews(array $options = []): array
    {
        return [];
    }
}