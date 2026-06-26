<?php
declare(strict_types=1);

namespace Awards\KMP\GridColumns;

use App\KMP\GridColumns\BaseGridColumns;
use App\Model\Entity\ActionItem;
use Awards\Model\Entity\Bestowal;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\TableRegistry;

/**
 * Grid column metadata for award bestowal Dataverse-style grids.
 *
 * Used on the bestowals index and gathering-scoped bestowal tabs.
 */
class BestowalsGridColumns extends BaseGridColumns
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function getRowActions(): array
    {
        return [
            'view' => [
                'key' => 'view',
                'type' => 'link',
                'label' => '',
                'icon' => 'bi-eye-fill',
                'class' => 'btn-sm btn btn-secondary',
                'permission' => 'view',
                'url' => [
                    'plugin' => 'Awards',
                    'controller' => 'Bestowals',
                    'action' => 'view',
                    'idField' => 'id',
                ],
            ],
            'edit' => [
                'key' => 'edit',
                'type' => 'modal',
                'label' => '',
                'icon' => 'bi-pencil-fill',
                'class' => 'btn-sm btn btn-primary edit-bestowal',
                'modalTarget' => '#editBestowalModal',
                'permission' => 'edit',
                'dataAttributes' => [
                    'controller' => 'outlet-btn',
                    'action' => 'click->outlet-btn#fireNotice',
                    'outlet-btn-btn-data-value' => [
                        'id' => 'id',
                    ],
                ],
            ],
            'todos' => [
                'key' => 'todos',
                'type' => 'modal',
                'label' => '',
                'icon' => 'bi-check2-square',
                'class' => 'btn-sm btn btn-outline-secondary todos-bestowal',
                'modalTarget' => '#bestowalTodosModal',
                'permission' => 'view',
                'dataAttributes' => [
                    'controller' => 'outlet-btn',
                    'action' => 'click->outlet-btn#fireNotice',
                    'outlet-btn-btn-data-value' => [
                        'id' => 'id',
                    ],
                ],
            ],
        ];
    }

    /**
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
                'width' => '60px',
                'alignment' => 'right',
            ],
            'member_sca_name' => [
                'key' => 'member_sca_name',
                'label' => __('Member'),
                'type' => 'string',
                'sortable' => true,
                'searchable' => true,
                'filterable' => false,
                'defaultVisible' => true,
                'width' => '180px',
                'alignment' => 'left',
                'renderField' => 'member_sca_name',
                'queryField' => 'Bestowals.member_sca_name',
                'clickAction' => 'navigate:/members/view/:member_id',
                'clickActionPermission' => static function ($row, $identity): bool {
                    $memberId = is_array($row) ? ($row['member_id'] ?? null) : ($row->member_id ?? null);
                    $memberId = is_numeric($memberId) ? (int)$memberId : null;

                    return $memberId !== null
                        && $memberId > 0
                        && $identity !== null
                        && method_exists($identity, 'checkCan')
                        && $identity->checkCan('view', 'Members');
                },
            ],
            'awards' => [
                'key' => 'awards',
                'label' => __('Award'),
                'type' => 'html',
                'sortable' => false,
                'filterable' => false,
                'searchable' => false,
                'defaultVisible' => true,
                'width' => '200px',
                'alignment' => 'left',
                'description' => __('Award selected for this bestowal'),
            ],
            'lifecycle_status' => [
                'key' => 'lifecycle_status',
                'label' => __('Lifecycle'),
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'defaultVisible' => true,
                'width' => '120px',
                'alignment' => 'left',
                'queryField' => 'Bestowals.lifecycle_status',
                'description' => __('Open, given, or cancelled lifecycle of the bestowal'),
            ],
            'todos_summary' => [
                'key' => 'todos_summary',
                'label' => __('To-Dos'),
                'type' => 'html',
                'sortable' => false,
                'searchable' => false,
                'filterable' => true,
                'filterType' => 'dropdown',
                'filterOptionsSource' => [
                    'method' => 'getTodoRemainingFilterOptions',
                    'class' => self::class,
                ],
                'customFilterHandler' => [
                    'method' => 'applyTodoRemainingFilter',
                    'class' => self::class,
                ],
                'exportable' => false,
                'defaultVisible' => true,
                'width' => '150px',
                'alignment' => 'left',
                'description' => __('Preparation checks (to-dos) and their completion state'),
            ],
            'court_slot' => [
                'key' => 'court_slot',
                'label' => __('Court Slot'),
                'type' => 'html',
                'sortable' => false,
                'filterable' => false,
                'defaultVisible' => true,
                'width' => '160px',
                'alignment' => 'left',
                'description' => __('Event Schedule court session linked for heralds'),
            ],
            'stack_rank' => [
                'key' => 'stack_rank',
                'label' => __('Stack Rank'),
                'type' => 'number',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => false,
                'width' => '90px',
                'alignment' => 'center',
                'description' => __('Reserved for future court stack ordering'),
            ],
            'herald_notes_preview' => [
                'key' => 'herald_notes_preview',
                'label' => __('Herald Notes'),
                'type' => 'html',
                'sortable' => false,
                'filterable' => false,
                'searchable' => true,
                'defaultVisible' => true,
                'width' => '220px',
                'alignment' => 'left',
                'queryField' => 'Bestowals.herald_notes',
                'description' => __('Preview of herald notes'),
            ],
            'recommendation_reasons' => [
                'key' => 'recommendation_reasons',
                'label' => __('Recommendation Reasons'),
                'type' => 'html',
                'sortable' => false,
                'filterable' => false,
                'searchable' => false,
                'defaultVisible' => true,
                'exportable' => false,
                'width' => '170px',
                'alignment' => 'left',
                'collapsible' => true,
                'description' => __('Linked recommendation reasons for court and herald notes'),
            ],
            'gathering_name' => [
                'key' => 'gathering_name',
                'label' => __('Gathering'),
                'type' => 'string',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => false,
                'width' => '180px',
                'alignment' => 'left',
                'renderField' => 'gathering.name',
                'queryField' => 'Gatherings.name',
            ],
            'source' => [
                'key' => 'source',
                'label' => __('Source'),
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'defaultVisible' => false,
                'width' => '110px',
                'alignment' => 'left',
            ],
            'created' => [
                'key' => 'created',
                'label' => __('Created'),
                'type' => 'date',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date-range',
                'defaultVisible' => false,
                'width' => '110px',
                'alignment' => 'left',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, array<string, mixed>>
     */
    public static function getSystemViews(array $options = []): array
    {
        $context = $options['context'] ?? 'index';
        $gatheringColumns = [
            'member_sca_name',
            'awards',
            'lifecycle_status',
            'todos_summary',
            'court_slot',
            'herald_notes_preview',
            'recommendation_reasons',
        ];

        if ($context === 'gatheringBestowals') {
            return [
                'sys-bestowals-gathering' => [
                    'id' => 'sys-bestowals-gathering',
                    'name' => __('Gathering Bestowals'),
                    'description' => __('Bestowals scheduled for this gathering'),
                    'canManage' => false,
                    'config' => [
                        'filters' => [],
                        'columns' => $gatheringColumns,
                    ],
                ],
            ];
        }

        $views = [
            'sys-bestowals-active' => [
                'id' => 'sys-bestowals-active',
                'name' => __('Active Bestowals'),
                'description' => __('Open bestowals that have not yet been given or cancelled'),
                'canManage' => false,
                'config' => [
                    'filters' => [
                        ['field' => 'lifecycle_status', 'operator' => 'eq', 'value' => Bestowal::LIFECYCLE_OPEN],
                    ],
                    'columns' => array_merge($gatheringColumns, ['gathering_name', 'source']),
                ],
            ],
            'sys-bestowals-all' => [
                'id' => 'sys-bestowals-all',
                'name' => __('All / Audit'),
                'description' => __('All bestowals across every lifecycle status'),
                'canManage' => false,
                'config' => [
                    'filters' => [],
                    'columns' => array_merge($gatheringColumns, ['gathering_name', 'source']),
                ],
            ],
            'sys-bestowals-completed' => [
                'id' => 'sys-bestowals-completed',
                'name' => __('Completed'),
                'description' => __('Bestowals marked as given'),
                'canManage' => false,
                'config' => [
                    'filters' => [
                        ['field' => 'lifecycle_status', 'operator' => 'eq', 'value' => Bestowal::LIFECYCLE_GIVEN],
                    ],
                    'columns' => array_merge($gatheringColumns, ['gathering_name', 'source', 'created']),
                ],
            ],
            'sys-bestowals-cancelled' => [
                'id' => 'sys-bestowals-cancelled',
                'name' => __('Cancelled'),
                'description' => __('Bestowals that were cancelled'),
                'canManage' => false,
                'config' => [
                    'filters' => [
                        ['field' => 'lifecycle_status', 'operator' => 'eq', 'value' => Bestowal::LIFECYCLE_CANCELLED],
                    ],
                    'columns' => array_merge($gatheringColumns, ['gathering_name', 'source', 'created']),
                ],
            ],
        ];

        return $views;
    }

    /**
     * Dropdown filter options for the to-do summary column.
     *
     * Because different awards can carry different to-do templates (e.g. kingdom vs
     * baronial), the options combine path-agnostic states ("remaining" / "complete")
     * with per-check options keyed on the shared template item key, so a single
     * "Open: Has scroll" filter matches that check across every path that defines it.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function getTodoRemainingFilterOptions(): array
    {
        $options = [
            ['value' => '__remaining_any', 'label' => __('Has any remaining To Dos')],
            ['value' => '__remaining', 'label' => __('Has any required remaining To Dos')],
            ['value' => '__complete', 'label' => __('Has Completed All To Dos')],
        ];

        $items = TableRegistry::getTableLocator()
            ->get('Awards.BestowalTodoTemplateItems')
            ->find()
            ->select(['item_key', 'label'])
            ->innerJoinWith('BestowalTodoTemplates', function ($q) {
                return $q->where(['BestowalTodoTemplates.is_active' => true]);
            })
            ->where(['BestowalTodoTemplateItems.item_key IS NOT' => null])
            ->orderBy(['BestowalTodoTemplateItems.sort_order' => 'ASC', 'BestowalTodoTemplateItems.label' => 'ASC'])
            ->all();

        $seen = [];
        foreach ($items as $item) {
            $key = (string)$item->item_key;
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $options[] = ['value' => 'open:' . $key, 'label' => __('Open: {0}', (string)$item->label)];
        }

        return $options;
    }

    /**
     * Custom filter handler narrowing bestowals by their open/remaining to-do checks.
     *
     * Supported values:
     * - `__remaining_any`: bestowals with at least one open to-do (required or optional).
     * - `__remaining`: bestowals with at least one open gating (required) to-do.
     * - `__complete`: bestowals with no open to-do at all (every to-do done/cancelled).
     * - `open:<item_key>`: bestowals with that specific check still open.
     *
     * @param \Cake\ORM\Query\SelectQuery $query The bestowals query to filter
     * @param array|string $filterValue Selected filter value
     * @param array<string, mixed> $context Trait-supplied context (unused)
     * @return \Cake\ORM\Query\SelectQuery The filtered query
     */
    public static function applyTodoRemainingFilter($query, $filterValue, array $context = []): SelectQuery
    {
        $value = is_array($filterValue) ? (string)reset($filterValue) : (string)$filterValue;
        if ($value === '') {
            return $query;
        }

        $actionItems = TableRegistry::getTableLocator()->get('ActionItems');
        $entityType = Bestowal::ACTION_ITEM_ENTITY_TYPE;

        $openSubquery = function (bool $gatingOnly) use ($actionItems, $entityType): SelectQuery {
            $conditions = [
                'ActionItems.entity_type' => $entityType,
                'ActionItems.status' => ActionItem::STATUS_OPEN,
            ];
            if ($gatingOnly) {
                $conditions['ActionItems.is_gating'] = true;
            }

            return $actionItems->find()
                ->select(['ActionItems.entity_id'])
                ->where($conditions);
        };

        if ($value === '__remaining_any') {
            return $query->where(['Bestowals.id IN' => $openSubquery(false)]);
        }

        if ($value === '__remaining') {
            return $query->where(['Bestowals.id IN' => $openSubquery(true)]);
        }

        if ($value === '__complete') {
            return $query->where(['Bestowals.id NOT IN' => $openSubquery(false)]);
        }

        if (str_starts_with($value, 'open:')) {
            $checkKey = substr($value, 5);
            if ($checkKey === '') {
                return $query;
            }

            $subquery = $actionItems->find()
                ->select(['ActionItems.entity_id'])
                ->where([
                    'ActionItems.entity_type' => $entityType,
                    'ActionItems.status' => ActionItem::STATUS_OPEN,
                    'ActionItems.source_ref' => $checkKey,
                ]);

            return $query->where(['Bestowals.id IN' => $subquery]);
        }

        return $query;
    }
}
