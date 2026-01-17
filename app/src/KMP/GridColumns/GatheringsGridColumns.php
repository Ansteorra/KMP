<?php

declare(strict_types=1);

namespace App\KMP\GridColumns;

use App\KMP\TimezoneHelper;
use DateTime;
use DateTimeZone;

/**
 * Gatherings Grid Column Metadata
 *
 * Defines shared columns for gathering listings and calendar grid integration.
 */
class GatheringsGridColumns extends BaseGridColumns
{

    /**
     * Compute key month boundaries in both local and UTC timezones.
     *
     * @return array<string, string>
     */
    public static function getSystemViewDateBoundaries(string $userTimezone): array
    {
        $timezone = new DateTimeZone($userTimezone);

        $thisMonthStart = new DateTime('first day of this month 00:00:00', $timezone);
        $thisMonthEnd = new DateTime('last day of this month 23:59:59', $timezone);
        $nextMonthStart = (clone $thisMonthStart)->modify('first day of next month');
        $nextMonthEnd = (clone $thisMonthEnd)->modify('last day of next month')->setTime(23, 59, 59);
        $previousCutoff = (clone $thisMonthStart)->setTime(0, 0, 0);

        $thisMonthStartUtc = TimezoneHelper::toUtc($thisMonthStart->format('Y-m-d H:i:s'), $userTimezone);
        $thisMonthEndUtc = TimezoneHelper::toUtc($thisMonthEnd->format('Y-m-d H:i:s'), $userTimezone);
        $nextMonthStartUtc = TimezoneHelper::toUtc($nextMonthStart->format('Y-m-d H:i:s'), $userTimezone);
        $nextMonthEndUtc = TimezoneHelper::toUtc($nextMonthEnd->format('Y-m-d H:i:s'), $userTimezone);
        $previousCutoffUtc = TimezoneHelper::toUtc($previousCutoff->format('Y-m-d H:i:s'), $userTimezone);

        return [
            'thisMonthStartUtc' => $thisMonthStartUtc->format('Y-m-d H:i:s'),
            'thisMonthEndUtc' => $thisMonthEndUtc->format('Y-m-d H:i:s'),
            'nextMonthStartUtc' => $nextMonthStartUtc->format('Y-m-d H:i:s'),
            'nextMonthEndUtc' => $nextMonthEndUtc->format('Y-m-d H:i:s'),
            'previousCutoffUtc' => $previousCutoffUtc->format('Y-m-d H:i:s'),
            'thisMonthStartLocal' => $thisMonthStart->format('Y-m-d'),
            'thisMonthEndLocal' => $thisMonthEnd->format('Y-m-d'),
            'nextMonthStartLocal' => $nextMonthStart->format('Y-m-d'),
            'nextMonthEndLocal' => $nextMonthEnd->format('Y-m-d'),
        ];
    }

    /**
     * Get row actions for gatherings grid listings
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
                    'idField' => 'public_id',
                ],
            ],
            'edit' => [
                'key' => 'edit',
                'type' => 'link',
                'label' => '',
                'icon' => 'bi-pencil-fill',
                'class' => 'btn btn-sm btn-primary',
                'permission' => 'edit',
                'url' => [
                    'controller' => 'Gatherings',
                    'action' => 'edit',
                    'idField' => 'id',
                ],
            ],
        ];
    }

    /**
     * Get column metadata for the gatherings grid
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

            'public_id' => [
                'key' => 'public_id',
                'label' => 'Public ID',
                'type' => 'string',
                'sortable' => false,
                'filterable' => false,
                'defaultVisible' => false,
                'width' => '120px',
                'alignment' => 'left',
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
                'width' => '260px',
                'alignment' => 'left',
                'clickAction' => 'navigate:/gatherings/view/:public_id',
            ],

            'branch_id' => [
                'key' => 'branch_id',
                'label' => 'Branch',
                'type' => 'relation',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'filterOptionsSource' => 'Branches',
                'defaultVisible' => true,
                'width' => '220px',
                'alignment' => 'left',
                'renderField' => 'branch.name',
                'queryField' => 'Branches.id',
            ],

            'gathering_type_id' => [
                'key' => 'gathering_type_id',
                'label' => 'Type',
                'type' => 'relation',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'filterOptionsSource' => 'GatheringTypes',
                'defaultVisible' => true,
                'width' => '180px',
                'alignment' => 'left',
                'renderField' => 'gathering_type.name',
                'queryField' => 'GatheringTypes.id',
            ],

            'start_date' => [
                'key' => 'start_date',
                'label' => 'Start',
                'type' => 'datetime',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date-range',
                'defaultVisible' => true,
                'width' => '170px',
                'alignment' => 'left',
            ],

            'end_date' => [
                'key' => 'end_date',
                'label' => 'End',
                'type' => 'datetime',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date-range',
                'defaultVisible' => true,
                'width' => '170px',
                'alignment' => 'left',
            ],

            'location' => [
                'key' => 'location',
                'label' => 'Location',
                'type' => 'string',
                'sortable' => false,
                'filterable' => true,
                'searchable' => true,
                'defaultVisible' => false,
                'width' => '240px',
                'alignment' => 'left',
            ],

            'activity_filter' => [
                'key' => 'activity_filter',
                'label' => 'Activity',
                'type' => 'relation',
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'dropdown',
                'filterOptionsSource' => 'GatheringActivities',
                'defaultVisible' => false,
                'alignment' => 'left',
                'queryField' => 'GatheringActivities.id',
            ],

            'activity_count' => [
                'key' => 'activity_count',
                'label' => '# Activities',
                'type' => 'number',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => false,
                'width' => '120px',
                'alignment' => 'center',
            ],

            'created' => [
                'key' => 'created',
                'label' => 'Created',
                'type' => 'datetime',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => false,
                'width' => '170px',
                'alignment' => 'left',
            ],

            'modified' => [
                'key' => 'modified',
                'label' => 'Updated',
                'type' => 'datetime',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => false,
                'width' => '170px',
                'alignment' => 'left',
            ],
        ];
    }

    /**
     * Gatherings searchable columns definition
     *
     * @return array<int, string>
     */
    public static function getSearchableColumns(): array
    {
        return ['name', 'location'];
    }

    /**
     * System views for gatherings dv_grid.
     *
     * @param array<string, mixed> $options
     * @return array<string, array<string, mixed>>
     */
    public static function getSystemViews(array $options = []): array
    {
        $userTimezone = (string)($options['timezone'] ?? 'UTC');
        $boundaries = self::getSystemViewDateBoundaries($userTimezone);

        return [
            'sys-gatherings-this-month' => [
                'id' => 'sys-gatherings-this-month',
                'name' => __('This Month'),
                'description' => __('Gatherings overlapping the current calendar month'),
                'canManage' => false,
                'config' => [
                    'filters' => [
                        ['field' => 'start_date', 'operator' => 'dateRange', 'value' => [$boundaries['thisMonthStartLocal'], $boundaries['thisMonthEndLocal']]],
                    ],
                    'skipFilterColumns' => ['start_date', 'end_date'],
                ],
            ],
            'sys-gatherings-next-month' => [
                'id' => 'sys-gatherings-next-month',
                'name' => __('Next Month'),
                'description' => __('Gatherings scheduled for the next calendar month'),
                'canManage' => false,
                'config' => [
                    'filters' => [
                        ['field' => 'start_date', 'operator' => 'dateRange', 'value' => [$boundaries['nextMonthStartLocal'], $boundaries['nextMonthEndLocal']]],
                    ],
                    'skipFilterColumns' => ['start_date', 'end_date'],
                ],
            ],
            'sys-gatherings-future' => [
                'id' => 'sys-gatherings-future',
                'name' => __('Future'),
                'description' => __('Gatherings starting after next month'),
                'canManage' => false,
                'config' => [
                    'filters' => [
                        ['field' => 'start_date', 'operator' => 'gte', 'value' => $boundaries['nextMonthEndLocal']],
                    ],
                    'skipFilterColumns' => ['start_date'],
                ],
            ],
            'sys-gatherings-previous' => [
                'id' => 'sys-gatherings-previous',
                'name' => __('Previous'),
                'description' => __('Gatherings that ended before this month'),
                'canManage' => false,
                'config' => [
                    'filters' => [
                        ['field' => 'end_date', 'operator' => 'lt', 'value' => $boundaries['thisMonthStartLocal']],
                    ],
                    'skipFilterColumns' => ['end_date'],
                ],
            ],
        ];
    }
}