<?php

declare(strict_types=1);

namespace App\KMP\GridColumns;

/**
 * AppSettings Grid Column Metadata
 */
class AppSettingsGridColumns extends BaseGridColumns
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
}
