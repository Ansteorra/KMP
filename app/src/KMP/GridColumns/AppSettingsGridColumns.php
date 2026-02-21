<?php

declare(strict_types=1);

namespace App\KMP\GridColumns;

/**
 * AppSettings Grid Column Metadata
 */
class AppSettingsGridColumns extends BaseGridColumns
{
    /**
     * Get row actions for app settings grid
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getRowActions(): array
    {
        return [
            'edit' => [
                'key' => 'edit',
                'type' => 'modal',
                'label' => '',
                'icon' => 'bi-pencil-fill',
                'class' => 'btn-sm btn btn-primary',
                'modalTarget' => '#editAppSettingModal',
                'permission' => 'edit',
                'dataAttributes' => [
                    'controller' => 'outlet-btn',
                    'action' => 'click->outlet-btn#fireNotice',
                    'outlet-btn-btn-data-value' => [
                        'id' => 'id',
                        'name' => 'name',
                        'type' => 'type',
                    ],
                ],
            ],
            'delete' => [
                'key' => 'delete',
                'type' => 'postLink',
                'label' => '',
                'icon' => 'bi-trash-fill',
                'class' => 'btn-sm btn btn-danger',
                'permission' => 'delete',
                'condition' => ['required' => false],
                'url' => [
                    'plugin' => null,
                    'controller' => 'AppSettings',
                    'action' => 'delete',
                    'idField' => 'id',
                ],
                'confirmMessage' => 'Are you sure you want to delete the setting "{{name}}"?',
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
                'label' => 'Setting Name',
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'searchable' => true,
                'defaultVisible' => true,
                'required' => true,
                'width' => '300px',
                'alignment' => 'left',
            ],

            'value_preview' => [
                'key' => 'value_preview',
                'label' => 'Value',
                'type' => 'string',
                'sortable' => false,
                'filterable' => false,
                'searchable' => true,
                'queryField' => 'AppSettings.value', // Search the actual database column
                'defaultVisible' => true,
                'width' => '400px',
                'alignment' => 'left',
                'exportable' => false,
            ],

            'type' => [
                'key' => 'type',
                'label' => 'Type',
                'type' => 'badge',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'defaultVisible' => true,
                'width' => '100px',
                'alignment' => 'center',
                'filterOptions' => [
                    ['value' => 'string', 'label' => 'String'],
                    ['value' => 'json', 'label' => 'JSON'],
                    ['value' => 'yaml', 'label' => 'YAML'],
                    ['value' => 'password', 'label' => 'Password'],
                ],
            ],

            'required' => [
                'key' => 'required',
                'label' => 'Required',
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
                'defaultVisible' => true,
                'width' => '150px',
                'alignment' => 'left',
            ],
        ];
    }

    /**
     * Get searchable columns for full-text search
     *
     * Overrides base method to use queryField when available,
     * which allows virtual columns (like value_preview) to map
     * to actual database columns (like AppSettings.value).
     *
     * @return array<string> Searchable column keys or queryFields
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
