<?php

declare(strict_types=1);

namespace App\KMP\GridColumns;

use App\Model\Entity\Permission;

/**
 * Permissions Grid Column Metadata
 *
 * Defines all available columns for the Permissions data grid.
 * Includes name, scoping rule, and multiple boolean requirement flags.
 */
class PermissionsGridColumns extends BaseGridColumns
{
    /**
     * Get column metadata for permissions grid
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
                'width' => '250px',
                'alignment' => 'left',
                'clickAction' => 'navigate:/permissions/view/:id',
            ],

            'scoping_rule' => [
                'key' => 'scoping_rule',
                'label' => 'Scoping Rule',
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'defaultVisible' => true,
                'width' => '150px',
                'alignment' => 'left',
                'filterOptions' => [
                    ['value' => Permission::SCOPE_GLOBAL, 'label' => Permission::SCOPE_GLOBAL],
                    ['value' => Permission::SCOPE_BRANCH_ONLY, 'label' => Permission::SCOPE_BRANCH_ONLY],
                    ['value' => Permission::SCOPE_BRANCH_AND_CHILDREN, 'label' => Permission::SCOPE_BRANCH_AND_CHILDREN],
                ],
            ],

            'require_active_membership' => [
                'key' => 'require_active_membership',
                'label' => 'Membership',
                'type' => 'boolean',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'defaultVisible' => true,
                'width' => '100px',
                'alignment' => 'center',
                'filterOptions' => [
                    ['value' => '1', 'label' => 'Required'],
                    ['value' => '0', 'label' => 'Not Required'],
                ],
            ],

            'require_active_background_check' => [
                'key' => 'require_active_background_check',
                'label' => 'Background Check',
                'type' => 'boolean',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'defaultVisible' => true,
                'width' => '130px',
                'alignment' => 'center',
                'filterOptions' => [
                    ['value' => '1', 'label' => 'Required'],
                    ['value' => '0', 'label' => 'Not Required'],
                ],
            ],

            'require_min_age' => [
                'key' => 'require_min_age',
                'label' => 'Minimum Age',
                'type' => 'number',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => true,
                'width' => '100px',
                'alignment' => 'center',
            ],

            'requires_warrant' => [
                'key' => 'requires_warrant',
                'label' => 'Warrant',
                'type' => 'boolean',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'defaultVisible' => true,
                'width' => '100px',
                'alignment' => 'center',
                'filterOptions' => [
                    ['value' => '1', 'label' => 'Required'],
                    ['value' => '0', 'label' => 'Not Required'],
                ],
            ],

            'is_super_user' => [
                'key' => 'is_super_user',
                'label' => 'Super User',
                'type' => 'boolean',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'defaultVisible' => true,
                'width' => '100px',
                'alignment' => 'center',
                'filterOptions' => [
                    ['value' => '1', 'label' => 'Yes'],
                    ['value' => '0', 'label' => 'No'],
                ],
            ],

            'is_system' => [
                'key' => 'is_system',
                'label' => 'System',
                'type' => 'boolean',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'defaultVisible' => true,
                'width' => '100px',
                'alignment' => 'center',
                'filterOptions' => [
                    ['value' => '1', 'label' => 'Yes'],
                    ['value' => '0', 'label' => 'No'],
                ],
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
