<?php

declare(strict_types=1);

namespace App\KMP\GridColumns;

use Cake\I18n\FrozenDate;

/**
 * Gathering Attendances Grid Column Metadata
 *
 * Defines the column configuration for the gathering attendances Dataverse-style grid view.
 * Used in the member profile Gatherings tab to display upcoming and past gathering attendance.
 */
class GatheringAttendancesGridColumns extends BaseGridColumns
{
    /**
     * Get row actions for gathering attendances grid
     *
     * Provides actions for viewing the gathering and editing attendance.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getRowActions(): array
    {
        return [
            'view' => [
                'key' => 'view',
                'type' => 'link',
                'label' => '',
                'icon' => 'bi-binoculars',
                'class' => 'btn btn-sm btn-secondary',
                'permission' => 'view',
                'url' => [
                    'controller' => 'Gatherings',
                    'action' => 'view',
                    'idField' => 'gathering.public_id',
                ],
            ],
            'edit' => [
                'key' => 'edit',
                'type' => 'modal',
                'label' => '',
                'icon' => 'bi-pencil-fill',
                'class' => 'btn btn-sm btn-primary',
                'modalTarget' => '#editGatheringAttendanceModal',
                'dataAttributes' => [
                    'attendance-id' => '{{id}}',
                    'gathering-id' => '{{gathering.id}}',
                ],
            ],
        ];
    }

    /**
     * Get column metadata for gathering attendances grid
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

            'gathering_id' => [
                'key' => 'gathering_id',
                'label' => 'Gathering',
                'type' => 'relation',
                'sortable' => true,
                'searchable' => true,
                'defaultVisible' => true,
                'width' => '250px',
                'alignment' => 'left',
                'renderField' => 'gathering.name',
                'queryField' => 'Gatherings.name',
                'description' => 'Gathering name',
            ],

            'gathering_branch' => [
                'key' => 'gathering_branch',
                'label' => 'Branch',
                'type' => 'relation',
                'sortable' => true,
                'searchable' => true,
                'defaultVisible' => true,
                'width' => '180px',
                'alignment' => 'left',
                'renderField' => 'gathering.branch.name',
                'queryField' => 'GatheringBranches.name',
                'description' => 'Branch hosting the gathering',
            ],

            'gathering_type' => [
                'key' => 'gathering_type',
                'label' => 'Type',
                'type' => 'relation',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'defaultVisible' => true,
                'width' => '140px',
                'alignment' => 'left',
                'renderField' => 'gathering.gathering_type.name',
                'queryField' => 'GatheringTypes.name',
            ],

            'gathering_status' => [
                'key' => 'gathering_status',
                'label' => 'Status',
                'type' => 'custom',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => true,
                'width' => '100px',
                'alignment' => 'center',
                'queryField' => 'Gatherings.cancelled_at',
                'cellRenderer' => function ($value, $row, $view) {
                    $gathering = $row['gathering'] ?? null;
                    if (!$gathering) {
                        return '<span class="text-muted">—</span>';
                    }
                    $cancelledAt = $gathering['cancelled_at'] ?? null;
                    if ($cancelledAt !== null) {
                        return '<span class="badge bg-danger">' . __('Cancelled') . '</span>';
                    }
                    return '<span class="badge bg-success">' . __('Active') . '</span>';
                },
            ],

            'gathering_date' => [
                'key' => 'gathering_date',
                'label' => 'Date',
                'type' => 'daterange',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => true,
                'width' => '200px',
                'alignment' => 'left',
                'renderField' => ['gathering.start_date', 'gathering.end_date'],
                'queryField' => 'Gatherings.start_date',
                'cellRenderer' => function ($value, $row, $view) {
                    // Extract start and end dates from the gathering
                    $gathering = $row['gathering'] ?? null;
                    if (!$gathering) {
                        return '<span class="text-muted">—</span>';
                    }

                    $startDate = $gathering['start_date'] ?? null;
                    $endDate = $gathering['end_date'] ?? null;

                    if (!$startDate) {
                        return '<span class="text-muted">—</span>';
                    }

                    // Format dates as MM/DD/YYYY to save space
                    $startFormatted = $view->Timezone->date($startDate, 'm/d/Y');

                    // If end date is same as start date or missing, show single date
                    if (!$endDate || $startDate->format('Y-m-d') === $endDate->format('Y-m-d')) {
                        return h($startFormatted);
                    }

                    // Show date range
                    $endFormatted = $view->Timezone->date($endDate, 'm/d/Y');
                    return h($startFormatted) . ' – ' . h($endFormatted);
                },
            ],

            'start_date' => [
                'key' => 'start_date',
                'label' => 'Start Date',
                'type' => 'date',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date-range',
                'defaultVisible' => false,  // Hidden by default since date range shows both
                'width' => '140px',
                'alignment' => 'left',
                'renderField' => 'gathering.start_date',
                'queryField' => 'Gatherings.start_date',
            ],


            'end_date' => [
                'key' => 'end_date',
                'label' => 'End Date',
                'type' => 'date',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date-range',
                'defaultVisible' => false,  // Hidden by default since date range shows both
                'width' => '140px',
                'alignment' => 'left',
                'renderField' => 'gathering.end_date',
                'queryField' => 'Gatherings.end_date',
            ],

            'public_note' => [
                'key' => 'public_note',
                'label' => 'Note',
                'type' => 'string',
                'sortable' => false,
                'filterable' => false,
                'defaultVisible' => true,
                'width' => '200px',
                'alignment' => 'left',
            ],

            'sharing' => [
                'key' => 'sharing',
                'label' => 'Sharing',
                'type' => 'custom',
                'sortable' => false,
                'filterable' => false,
                'defaultVisible' => true,
                'width' => '200px',
                'alignment' => 'left',
                'description' => 'Who can see this attendance',
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

    /**
     * Get date range filter columns
     *
     * Defines columns that support date range filtering with start/end parameters.
     * This includes both start_date and end_date for proper filtering of past/upcoming gatherings.
     */
    public static function getDateRangeFilterColumns(): array
    {
        return array_filter(
            static::getColumns(),
            fn($col) => !empty($col['filterable']) && ($col['filterType'] ?? null) === 'date-range'
        );
    }

    /**
     * System views for gathering attendances dv_grid.
     *
     * @param array<string, mixed> $options
     * @return array<string, array<string, mixed>>
     */
    public static function getSystemViews(array $options = []): array
    {
        $today = FrozenDate::today();
        $todayString = $today->format('Y-m-d');
        $yesterdayString = $today->subDays(1)->format('Y-m-d');

        return [
            'sys-gatherings-upcoming' => [
                'id' => 'sys-gatherings-upcoming',
                'name' => __('Upcoming'),
                'description' => __('Gatherings scheduled in the future'),
                'canManage' => false,
                'config' => [
                    'filters' => [
                        ['field' => 'start_date', 'operator' => 'dateRange', 'value' => [$todayString, null]],
                    ],
                ],
            ],
            'sys-gatherings-past' => [
                'id' => 'sys-gatherings-past',
                'name' => __('Past'),
                'description' => __('Past gatherings'),
                'canManage' => false,
                'config' => [
                    'filters' => [
                        ['field' => 'end_date', 'operator' => 'dateRange', 'value' => [null, $yesterdayString]],
                    ],
                ],
            ],
        ];
    }
}
