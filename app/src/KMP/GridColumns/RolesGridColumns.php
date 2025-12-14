<?php

declare(strict_types=1);

namespace App\KMP\GridColumns;

/**
 * Roles Grid Column Metadata
 */
class RolesGridColumns extends BaseGridColumns
{
    /**
     * Get available system views for the Roles grid.
     *
     * @param array<string, mixed> $options Optional context (unused)
     * @return array<string, array<string, mixed>>
     */
    public static function getSystemViews(array $options = []): array
    {
        return [
            'sys-roles-system' => [
                'id' => 'sys-roles-system',
                'name' => __('System Roles'),
                'description' => __('System-defined roles'),
                'canManage' => false,
                'config' => [
                    'columns' => ['name', 'is_system', 'member_count'],
                    'filters' => [
                        ['field' => 'is_system', 'operator' => 'eq', 'value' => '1'],
                    ],
                ],
            ],
            'sys-roles-custom' => [
                'id' => 'sys-roles-custom',
                'name' => __('Custom Roles'),
                'description' => __('User-defined roles'),
                'canManage' => false,
                'config' => [
                    'columns' => ['name', 'is_system', 'member_count'],
                    'filters' => [
                        ['field' => 'is_system', 'operator' => 'eq', 'value' => '0'],
                    ],
                ],
            ],
        ];
    }

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
                'label' => 'Role Name',
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'searchable' => true,
                'defaultVisible' => true,
                'required' => true,
                'width' => '200px',
                'alignment' => 'left',
                'clickAction' => 'navigate:/roles/view/:id',
            ],

            'is_system' => [
                'key' => 'is_system',
                'label' => 'System Role',
                'type' => 'boolean',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'defaultVisible' => true,
                'width' => '120px',
                'alignment' => 'center',
                'filterOptions' => [
                    ['value' => '1', 'label' => 'Yes'],
                    ['value' => '0', 'label' => 'No'],
                ],
            ],

            'member_count' => [
                'key' => 'member_count',
                'label' => 'Members',
                'type' => 'number',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => true,
                'width' => '100px',
                'alignment' => 'right',
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
