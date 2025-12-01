<?php

declare(strict_types=1);

namespace App\KMP\GridColumns;

use App\Model\Entity\ActiveWindowBaseEntity;

/**
 * Member Roles Grid Column Metadata
 *
 * Defines the column configuration for the member roles Dataverse-style grid view.
 * Used in the member profile Roles tab to display current, upcoming, and previous roles.
 */
class MemberRolesGridColumns extends BaseGridColumns
{
    /**
     * Get column metadata for member roles grid
     */
    public static function getColumns(): array
    {
        $columns = [
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

            'role_id' => [
                'key' => 'role_id',
                'label' => 'Role',
                'type' => 'relation',
                'sortable' => true,
                'searchable' => true,
                'defaultVisible' => true,
                'width' => '200px',
                'alignment' => 'left',
                'renderField' => 'role.name',
                'queryField' => 'Roles.name',
                'description' => 'Role name',
            ],

            'start_on' => [
                'key' => 'start_on',
                'label' => 'Start Date',
                'type' => 'date',
                'sortable' => true,
                'filterable' => true,
                'defaultVisible' => true,
                'filterType' => 'date-range',
                'width' => '140px',
                'alignment' => 'left',
                'queryField' => 'MemberRoles.start_on',
                'renderField' => 'start_on',
            ],

            'expires_on' => [
                'key' => 'expires_on',
                'label' => 'End Date',
                'type' => 'date',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date-range',
                'defaultVisible' => true,
                'width' => '140px',
                'alignment' => 'left',
                'queryField' => 'MemberRoles.expires_on',
                'renderField' => 'expires_on',
                'nullMeansActive' => true,  // NULL = no known expiration (active indefinitely)
            ],

            'approved_by_id' => [
                'key' => 'approved_by_id',
                'label' => 'Approved By',
                'type' => 'relation',
                'sortable' => true,
                'searchable' => true,
                'defaultVisible' => true,
                'width' => '180px',
                'alignment' => 'left',
                'renderField' => 'approved_by.sca_name',
                'queryField' => 'ApprovedBy.sca_name',
                'description' => 'Member who approved this role',
            ],

            'entity_type' => [
                'key' => 'entity_type',
                'label' => 'Granted By',
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'defaultVisible' => true,
                'width' => '160px',
                'alignment' => 'left',
                'filterOptions' => [
                    ['value' => 'Direct Grant', 'label' => 'Direct Grant'],
                    ['value' => 'Officers.Officers', 'label' => 'Office'],
                    ['value' => 'Warrants', 'label' => 'Warrant'],
                ],
            ],

            'branch_id' => [
                'key' => 'branch_id',
                'label' => 'Scope',
                'type' => 'relation',
                'sortable' => true,
                'searchable' => true,
                'defaultVisible' => true,
                'width' => '200px',
                'alignment' => 'left',
                'renderField' => 'branch.name',
                'queryField' => 'Branches.name',
                'description' => 'Branch scope of the role',
            ],

            'status' => [
                'key' => 'status',
                'label' => 'Status',
                'type' => 'badge',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'defaultVisible' => false,
                'width' => '120px',
                'alignment' => 'center',
                'filterOptions' => [
                    ['value' => ActiveWindowBaseEntity::CURRENT_STATUS, 'label' => ActiveWindowBaseEntity::CURRENT_STATUS],
                    ['value' => ActiveWindowBaseEntity::UPCOMING_STATUS, 'label' => ActiveWindowBaseEntity::UPCOMING_STATUS],
                    ['value' => ActiveWindowBaseEntity::EXPIRED_STATUS, 'label' => ActiveWindowBaseEntity::EXPIRED_STATUS],
                    ['value' => ActiveWindowBaseEntity::DEACTIVATED_STATUS, 'label' => ActiveWindowBaseEntity::DEACTIVATED_STATUS],
                ],
            ],

            'actions' => [
                'key' => 'actions',
                'label' => '',
                'type' => 'actions',
                'required' => true,
                'defaultVisible' => true,
                'sortable' => false,
                'exportable' => false,
                'width' => '120px',
                'alignment' => 'right',
            ],
        ];

        return $columns;
    }

    /**
     * Get default visible columns
     */
    public static function getDefaultVisibleColumns(): array
    {
        return array_filter(
            static::getColumns(),
            fn($col) => !empty($col['defaultVisible'])
        );
    }

    /**
     * Get required columns (cannot be hidden)
     */
    public static function getRequiredColumns(): array
    {
        $required = [];
        foreach (static::getColumns() as $key => $col) {
            if (!empty($col['required'])) {
                $required[] = $key;
            }
        }
        return $required;
    }

    /**
     * Get searchable columns for full-text search
     */
    public static function getSearchableColumns(): array
    {
        $searchable = [];
        foreach (static::getColumns() as $key => $col) {
            if (!empty($col['searchable'])) {
                $searchable[] = $col['queryField'] ?? $key;
            }
        }
        return $searchable;
    }
}
