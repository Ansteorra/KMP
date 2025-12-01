<?php

declare(strict_types=1);

namespace Waivers\KMP\GridColumns;

use App\KMP\GridColumns\BaseGridColumns;

/**
 * Waiver Types Grid Column Metadata
 *
 * Defines the column configuration for the WaiverTypes Dataverse-style grid view.
 */
class WaiverTypesGridColumns extends BaseGridColumns
{
    /**
     * Get row action configurations for waiver types grid
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getRowActions(): array
    {
        return [];
        $buttons = [
            'edit' => [
                'key' => 'edit',
                'type' => 'link',
                'label' => '',
                'icon' => 'bi-pencil-fill',
                'class' => 'btn-sm btn btn-primary',
                'permission' => 'edit',
                'url' => [
                    'plugin' => 'Waivers',
                    'controller' => 'WaiverTypes',
                    'action' => 'edit',
                    'idField' => 'id',
                ],
            ],
            'toggleActive' => [
                'key' => 'toggleActive',
                'type' => 'postLink',
                'label' => '',
                'icon' => 'bi-toggle-off',
                'class' => 'btn-sm btn btn-warning',
                'permission' => 'toggleActive',
                'url' => [
                    'plugin' => 'Waivers',
                    'controller' => 'WaiverTypes',
                    'action' => 'toggleActive',
                    'idField' => 'id',
                ],
                'confirmMessage' => 'Are you sure you want to toggle the active status of this waiver type?',
            ],
            'delete' => [
                'key' => 'delete',
                'type' => 'postLink',
                'label' => '',
                'icon' => 'bi-trash-fill',
                'class' => 'btn-sm btn btn-danger',
                'permission' => 'delete',
                'url' => [
                    'plugin' => 'Waivers',
                    'controller' => 'WaiverTypes',
                    'action' => 'delete',
                    'idField' => 'id',
                ],
                'confirmMessage' => 'Are you sure you want to delete this waiver type?',
            ],
        ];
    }

    /**
     * Get column metadata for waiver types grid
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
                'width' => '200px',
                'alignment' => 'left',
                'clickAction' => 'navigate:/waivers/waiver-types/view/:id',
            ],

            'description' => [
                'key' => 'description',
                'label' => 'Description',
                'type' => 'string',
                'sortable' => false,
                'filterable' => false,
                'searchable' => true,
                'defaultVisible' => true,
                'width' => '250px',
                'alignment' => 'left',
            ],

            'has_template' => [
                'key' => 'has_template',
                'label' => 'Template',
                'type' => 'boolean',
                'sortable' => false,
                'filterable' => false,
                'defaultVisible' => true,
                'width' => '100px',
                'alignment' => 'center',
                'description' => 'Whether the waiver type has a template file or URL',
            ],

            'retention_description' => [
                'key' => 'retention_description',
                'label' => 'Retention Policy',
                'type' => 'string',
                'sortable' => false,
                'filterable' => false,
                'defaultVisible' => true,
                'width' => '200px',
                'alignment' => 'left',
                'description' => 'Human-readable retention policy description',
            ],

            'convert_to_pdf' => [
                'key' => 'convert_to_pdf',
                'label' => 'Convert to PDF',
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

            'is_active' => [
                'key' => 'is_active',
                'label' => 'Status',
                'type' => 'badge',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'defaultVisible' => true,
                'width' => '100px',
                'alignment' => 'center',
                'filterOptions' => [
                    ['value' => '1', 'label' => 'Active'],
                    ['value' => '0', 'label' => 'Inactive'],
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

    /**
     * Get system views for waiver types
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getSystemViews(): array
    {
        return [
            'sys-waiver-types-active' => [
                'id' => 'sys-waiver-types-active',
                'name' => __('Active'),
                'description' => __('Active waiver types'),
                'canManage' => false,
                'config' => [
                    'filters' => [
                        ['field' => 'is_active', 'operator' => 'eq', 'value' => '1'],
                    ],
                ],
            ],
            'sys-waiver-types-inactive' => [
                'id' => 'sys-waiver-types-inactive',
                'name' => __('Inactive'),
                'description' => __('Inactive waiver types'),
                'canManage' => false,
                'config' => [
                    'filters' => [
                        ['field' => 'is_active', 'operator' => 'eq', 'value' => '0'],
                    ],
                ],
            ],
            'sys-waiver-types-all' => [
                'id' => 'sys-waiver-types-all',
                'name' => __('All'),
                'description' => __('All waiver types'),
                'canManage' => false,
                'config' => [
                    'filters' => [],
                ],
            ],
        ];
    }
}