<?php

declare(strict_types=1);

namespace App\KMP\GridColumns;

/**
 * Warrant Rosters Grid Column Metadata
 * 
 * Defines column configuration for the Warrant Rosters Dataverse grid.
 */
class WarrantRostersGridColumns extends BaseGridColumns
{
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
                'width' => '250px',
                'alignment' => 'left',
                'clickAction' => 'navigate:/warrant-rosters/view/:id',
            ],

            'warrant_count' => [
                'key' => 'warrant_count',
                'label' => 'Warrants',
                'type' => 'number',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => true,
                'width' => '100px',
                'alignment' => 'right',
            ],

            'approvals_required' => [
                'key' => 'approvals_required',
                'label' => 'Approvals Required',
                'type' => 'number',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => true,
                'width' => '150px',
                'alignment' => 'right',
            ],

            'approval_count' => [
                'key' => 'approval_count',
                'label' => 'Approvals',
                'type' => 'number',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => true,
                'width' => '100px',
                'alignment' => 'right',
            ],

            'status' => [
                'key' => 'status',
                'label' => 'Status',
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'defaultVisible' => true,
                'width' => '120px',
                'alignment' => 'center',
                'filterOptions' => [
                    ['value' => 'Pending', 'label' => 'Pending'],
                    ['value' => 'Approved', 'label' => 'Approved'],
                    ['value' => 'Declined', 'label' => 'Declined'],
                ],
            ],

            'created' => [
                'key' => 'created',
                'label' => 'Created',
                'type' => 'datetime',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => true,
                'width' => '150px',
                'alignment' => 'left',
            ],

            'created_by_member_sca_name' => [
                'key' => 'created_by_member_sca_name',
                'label' => 'Created By',
                'type' => 'string',
                'sortable' => false,
                'filterable' => false,
                'searchable' => true,
                'defaultVisible' => true,
                'width' => '180px',
                'alignment' => 'left',
            ],
        ];
    }
}
