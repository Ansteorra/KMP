<?php
declare(strict_types=1);

namespace App\KMP\GridColumns;

use App\KMP\TimezoneHelper as TzHelper;
use App\Model\Entity\ActiveWindowBaseEntity;
use App\Model\Entity\Warrant;
use Cake\ORM\Query\SelectQuery;
use DateTime;
use DateTimeZone;

/**
 * Warrants Grid Column Metadata
 *
 * Defines the column configuration for the warrants Dataverse-style grid view POC.
 * Columns follow the same structure as MembersGridColumns to reuse shared helpers
 * from BaseGridColumns.
 */
class WarrantsGridColumns extends BaseGridColumns
{
    public const SCOPE_CURRENT = 'current';

    public const SCOPE_PENDING = 'pending';

    public const SCOPE_UPCOMING = 'upcoming';

    public const SCOPE_PREVIOUS = 'previous';

    /**
     * Get column metadata for warrants grid
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

            'name' => [
                'key' => 'name',
                'label' => 'Warrant',
                'type' => 'string',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => true,
                'width' => '220px',
                'alignment' => 'left',
            ],

            'member_id' => [
                'key' => 'member_id',
                'label' => 'Member',
                'type' => 'relation',
                'sortable' => true,
                'searchable' => true,
                'defaultVisible' => true,
                'width' => '200px',
                'alignment' => 'left',
                'renderField' => 'member.sca_name',
                'queryField' => 'Members.sca_name',
                'description' => 'Member receiving the warrant',
            ],

            'entity_type' => [
                'key' => 'entity_type',
                'label' => 'Type',
                'type' => 'string',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => false,
                'width' => '160px',
                'alignment' => 'left',
            ],

            'start_on' => [
                'key' => 'start_on',
                'label' => 'Starts',
                'type' => 'date',
                'sortable' => true,
                'filterable' => true,
                'defaultVisible' => true,
                'filterType' => 'date-range',
                'width' => '140px',
                'alignment' => 'left',
            ],

            'expires_on' => [
                'key' => 'expires_on',
                'label' => 'Expires',
                'type' => 'date',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date-range',
                'defaultVisible' => true,
                'width' => '140px',
                'alignment' => 'left',
            ],

            'status' => [
                'key' => 'status',
                'label' => 'Status',
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'defaultVisible' => true,
                'width' => '140px',
                'alignment' => 'center',
                'filterOptions' => [
                    [
                        'value' => ActiveWindowBaseEntity::CURRENT_STATUS,
                        'label' => ActiveWindowBaseEntity::CURRENT_STATUS,
                    ],
                    [
                        'value' => ActiveWindowBaseEntity::UPCOMING_STATUS,
                        'label' => ActiveWindowBaseEntity::UPCOMING_STATUS,
                    ],
                    [
                        'value' => ActiveWindowBaseEntity::EXPIRED_STATUS,
                        'label' => ActiveWindowBaseEntity::EXPIRED_STATUS,
                    ],
                    [
                        'value' => ActiveWindowBaseEntity::DEACTIVATED_STATUS,
                        'label' => ActiveWindowBaseEntity::DEACTIVATED_STATUS,
                    ],
                    [
                        'value' => ActiveWindowBaseEntity::RELEASED_STATUS,
                        'label' => ActiveWindowBaseEntity::RELEASED_STATUS,
                    ],
                    [
                        'value' => ActiveWindowBaseEntity::REPLACED_STATUS,
                        'label' => ActiveWindowBaseEntity::REPLACED_STATUS,
                    ],
                    [
                        'value' => ActiveWindowBaseEntity::CANCELLED_STATUS,
                        'label' => ActiveWindowBaseEntity::CANCELLED_STATUS,
                    ],
                    ['value' => Warrant::PENDING_STATUS, 'label' => Warrant::PENDING_STATUS],
                    ['value' => Warrant::DECLINED_STATUS, 'label' => Warrant::DECLINED_STATUS],
                ],
            ],

            'warrant_scope' => [
                'key' => 'warrant_scope',
                'label' => 'Warrant state',
                'type' => 'string',
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'dropdown',
                'defaultVisible' => false,
                'filterOnly' => true,
                'lockedFilter' => true,
                'showInFilterMenu' => false,
                'exportable' => false,
                'filterOptions' => [
                    ['value' => self::SCOPE_CURRENT, 'label' => 'Currently effective'],
                    ['value' => self::SCOPE_PENDING, 'label' => 'Pending approval'],
                    ['value' => self::SCOPE_UPCOMING, 'label' => 'Starts in the future'],
                    ['value' => self::SCOPE_PREVIOUS, 'label' => 'Expired or inactive'],
                ],
                'customFilterHandler' => [
                    'class' => self::class,
                    'method' => 'applyWarrantScopeFilter',
                ],
                'description' => 'Dynamic effective-date and lifecycle scope used by warrant system views',
            ],

            'revoker_id' => [
                'key' => 'revoker_id',
                'label' => 'Revoked By',
                'type' => 'relation',
                'sortable' => false,
                'filterable' => false,
                'defaultVisible' => false,
                'width' => '200px',
                'alignment' => 'left',
                'renderField' => 'revoked_by.sca_name',
                'queryField' => 'RevokedBy.sca_name',
            ],

            'revoked_reason' => [
                'key' => 'revoked_reason',
                'label' => 'Revoked Reason',
                'type' => 'string',
                'sortable' => false,
                'filterable' => true,
                'defaultVisible' => false,
                'width' => '240px',
                'alignment' => 'left',
            ],
        ];

        return $columns;
    }

    /**
     * Apply a dynamic, serializable warrant system-view scope.
     *
     * @param \Cake\ORM\Query\SelectQuery $query Warrants query.
     * @param mixed $filterValue Saved symbolic filter value.
     * @param array<string, mixed> $context Grid filter context.
     * @return \Cake\ORM\Query\SelectQuery Filtered query.
     */
    public static function applyWarrantScopeFilter(
        SelectQuery $query,
        mixed $filterValue,
        array $context = [],
    ): SelectQuery {
        $values = is_array($filterValue) ? $filterValue : [$filterValue];
        $value = isset($values[0]) ? (string)$values[0] : '';
        $kingdomTimezone = TzHelper::getAppTimezone() ?? 'UTC';
        $today = new DateTime('today', new DateTimeZone($kingdomTimezone));
        $todayString = $today->format('Y-m-d');
        $tomorrowString = (clone $today)->modify('+1 day')->format('Y-m-d');
        $tableName = (string)($context['tableName'] ?? 'Warrants');

        if ($value === self::SCOPE_CURRENT) {
            return $query->where([
                $tableName . '.status' => Warrant::CURRENT_STATUS,
                $tableName . '.start_on <=' => $todayString,
                $tableName . '.expires_on >=' => $todayString,
            ]);
        }

        if ($value === self::SCOPE_PENDING) {
            return $query->where([$tableName . '.status' => Warrant::PENDING_STATUS]);
        }

        if ($value === self::SCOPE_UPCOMING) {
            return $query->where([
                $tableName . '.status' => Warrant::CURRENT_STATUS,
                $tableName . '.start_on >=' => $tomorrowString,
            ]);
        }

        if ($value === self::SCOPE_PREVIOUS) {
            return $query->where([
                'OR' => [
                    $tableName . '.expires_on <' => $todayString,
                    $tableName . '.status IN' => [
                        Warrant::DEACTIVATED_STATUS,
                        Warrant::EXPIRED_STATUS,
                        Warrant::DECLINED_STATUS,
                    ],
                ],
            ]);
        }

        return $query->where(['1 = 0']);
    }

    /**
     * Get default visible columns
     */
    public static function getDefaultVisibleColumns(): array
    {
        return array_filter(
            static::getColumns(),
            fn($col) => !empty($col['defaultVisible']),
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
     * System views for warrants dv_grid.
     *
     * @param array<string, mixed> $options
     * @return array<string, array<string, mixed>>
     */
    public static function getSystemViews(array $options = []): array
    {
        return [
            'sys-warrants-current' => [
                'id' => 'sys-warrants-current',
                'name' => __('Current'),
                'description' => __('Active warrants providing RBAC validation'),
                'canManage' => false,
                'config' => [
                    'filters' => [
                        ['field' => 'warrant_scope', 'operator' => 'eq', 'value' => self::SCOPE_CURRENT],
                    ],
                ],
            ],
            'sys-warrants-pending' => [
                'id' => 'sys-warrants-pending',
                'name' => __('Pending'),
                'description' => __('Warrants awaiting approval through roster system'),
                'canManage' => false,
                'config' => [
                    'filters' => [
                        ['field' => 'warrant_scope', 'operator' => 'eq', 'value' => self::SCOPE_PENDING],
                    ],
                ],
            ],
            'sys-warrants-upcoming' => [
                'id' => 'sys-warrants-upcoming',
                'name' => __('Upcoming'),
                'description' => __('Warrants scheduled to start in the future'),
                'canManage' => false,
                'config' => [
                    'filters' => [
                        ['field' => 'warrant_scope', 'operator' => 'eq', 'value' => self::SCOPE_UPCOMING],
                    ],
                ],
            ],
            'sys-warrants-previous' => [
                'id' => 'sys-warrants-previous',
                'name' => __('Previous'),
                'description' => __('Expired or deactivated warrants'),
                'canManage' => false,
                'config' => [
                    'columns' => ['name', 'member_id', 'start_on', 'expires_on', 'status'],
                    'filters' => [
                        ['field' => 'warrant_scope', 'operator' => 'eq', 'value' => self::SCOPE_PREVIOUS],
                    ],
                ],
            ],
        ];
    }
}
