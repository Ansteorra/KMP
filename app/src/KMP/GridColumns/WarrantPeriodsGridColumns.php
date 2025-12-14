<?php

declare(strict_types=1);

namespace App\KMP\GridColumns;

/**
 * Warrant Periods Grid Column Metadata
 *
 * Defines all available columns for the Warrant Periods data grid.
 * Simple grid with start and end date columns.
 */
class WarrantPeriodsGridColumns extends BaseGridColumns
{
    /**
     * Get column metadata for warrant periods grid
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

            'start_date' => [
                'key' => 'start_date',
                'label' => 'Start Date',
                'type' => 'date',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date-range',
                'defaultVisible' => true,
                'required' => true,
                'width' => '150px',
                'alignment' => 'left',
            ],

            'end_date' => [
                'key' => 'end_date',
                'label' => 'End Date',
                'type' => 'date',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date-range',
                'defaultVisible' => true,
                'required' => true,
                'width' => '150px',
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
