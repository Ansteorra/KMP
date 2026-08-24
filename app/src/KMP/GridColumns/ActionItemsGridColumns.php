<?php
declare(strict_types=1);

namespace App\KMP\GridColumns;

use App\Model\Entity\ActionItem;
use App\Services\ActionItems\ActionItemService;
use Awards\Model\Entity\Bestowal;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\TableRegistry;

/**
 * Action Items Grid Column Metadata
 *
 * Defines column configuration for the "My To-Dos" Dataverse grid. Mirrors the
 * My Approvals grid: an "Open To-Dos" system view (items the member may act on)
 * and a "Completed" system view (to-dos the member has completed), with a modal
 * Complete / Reopen row action.
 */
class ActionItemsGridColumns extends BaseGridColumns
{
    public const NO_GATHERING_FILTER_VALUE = '__none__';

    public const MEMBER_SCOPE_ACTIONABLE = 'actionable';

    public const MEMBER_SCOPE_COMPLETED_BY_ME = 'completed_by_me';

    /**
     * Column definitions for the My To-Dos grid.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getColumns(): array
    {
        return [
            'title' => [
                'key' => 'title',
                'label' => 'To-Do',
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'searchable' => true,
                'defaultVisible' => true,
                'required' => true,
                'width' => '240px',
                'alignment' => 'left',
                'queryField' => 'ActionItems.title',
            ],

            'owner' => [
                'key' => 'owner',
                'label' => 'For',
                'type' => 'html',
                'sortable' => false,
                'filterable' => false,
                'searchable' => false,
                'defaultVisible' => true,
                'width' => '240px',
                'alignment' => 'left',
                'skipAutoFilter' => true,
            ],

            'requirement' => [
                'key' => 'requirement',
                'label' => 'Required?',
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'searchable' => false,
                'defaultVisible' => true,
                'width' => '120px',
                'alignment' => 'center',
                'queryField' => 'ActionItems.is_gating',
                'filterOptions' => [
                    ['value' => '1', 'label' => 'Required'],
                    ['value' => '0', 'label' => 'Optional'],
                ],
            ],

            'branch' => [
                'key' => 'branch',
                'label' => 'Branch',
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'searchable' => true,
                'defaultVisible' => false,
                'width' => '160px',
                'alignment' => 'left',
                'queryField' => 'Branches.name',
            ],

            'gathering' => [
                'key' => 'gathering',
                'label' => 'Gathering',
                'type' => 'relation',
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'dropdown',
                'searchable' => false,
                'defaultVisible' => false,
                'filterOnly' => true,
                'filterOptions' => [
                    ['value' => self::NO_GATHERING_FILTER_VALUE, 'label' => 'None'],
                ],
                'customFilterHandler' => [
                    'class' => self::class,
                    'method' => 'applyGatheringFilter',
                ],
                'description' => 'Gathering assigned to the to-do owner, or none',
            ],

            'status_label' => [
                'key' => 'status_label',
                'label' => 'Status',
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'defaultVisible' => true,
                'width' => '130px',
                'alignment' => 'center',
                'queryField' => 'ActionItems.status',
                'filterOptions' => [
                    ['value' => ActionItem::STATUS_OPEN, 'label' => 'Open'],
                    ['value' => ActionItem::STATUS_COMPLETED, 'label' => 'Completed'],
                    ['value' => ActionItem::STATUS_CANCELLED, 'label' => 'Cancelled'],
                ],
            ],

            'member_scope' => [
                'key' => 'member_scope',
                'label' => 'Your involvement',
                'type' => 'string',
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'dropdown',
                'searchable' => false,
                'defaultVisible' => false,
                'filterOnly' => true,
                'lockedFilter' => true,
                'showInFilterMenu' => false,
                'exportable' => false,
                'filterOptions' => [
                    ['value' => self::MEMBER_SCOPE_ACTIONABLE, 'label' => 'You can complete'],
                    ['value' => self::MEMBER_SCOPE_COMPLETED_BY_ME, 'label' => 'Completed by you'],
                ],
                'customFilterHandler' => [
                    'class' => self::class,
                    'method' => 'applyMemberScopeFilter',
                ],
                'description' => 'Current member relationship used by My To-Dos system views',
            ],

            'completed_at' => [
                'key' => 'completed_at',
                'label' => 'Completed',
                'type' => 'datetime',
                'sortable' => true,
                'filterable' => true,
                'defaultVisible' => false,
                'width' => '160px',
                'alignment' => 'left',
                'filterType' => 'date-range',
                'queryField' => 'ActionItems.completed_at',
            ],

            'modified' => [
                'key' => 'modified',
                'label' => 'Last Updated',
                'type' => 'datetime',
                'sortable' => true,
                'filterable' => true,
                'defaultVisible' => false,
                'width' => '160px',
                'alignment' => 'left',
                'filterType' => 'date-range',
            ],
        ];
    }

    /**
     * Apply the symbolic current-member scope stored with a To-Do view.
     *
     * @param \Cake\ORM\Query\SelectQuery $query Action items query.
     * @param mixed $filterValue Saved symbolic filter value.
     * @param array<string, mixed> $context Grid filter context.
     * @return \Cake\ORM\Query\SelectQuery Filtered query.
     */
    public static function applyMemberScopeFilter(
        SelectQuery $query,
        mixed $filterValue,
        array $context = [],
    ): SelectQuery {
        $values = is_array($filterValue) ? $filterValue : [$filterValue];
        $value = isset($values[0]) ? (string)$values[0] : '';
        $identity = $context['identity'] ?? null;
        $memberId = is_object($identity) && method_exists($identity, 'getIdentifier')
            ? (int)$identity->getIdentifier()
            : (is_object($identity) ? (int)($identity->id ?? 0) : 0);
        if ($memberId <= 0) {
            return $query->where(['1 = 0']);
        }

        if ($value === self::MEMBER_SCOPE_ACTIONABLE) {
            return (new ActionItemService())->applyOpenCandidateScopeForMember($query, $memberId);
        }

        if ($value === self::MEMBER_SCOPE_COMPLETED_BY_ME) {
            return $query->where([
                'ActionItems.status' => ActionItem::STATUS_COMPLETED,
                'ActionItems.completed_by' => $memberId,
            ]);
        }

        return $query->where(['1 = 0']);
    }

    /**
     * Filter to-dos by the gathering assigned to their polymorphic owner.
     *
     * Only Awards bestowals currently support gathering ownership. "None"
     * therefore includes generic owners, missing bestowals, and bestowals that
     * do not resolve to an active gathering.
     *
     * @param \Cake\ORM\Query\SelectQuery $query Action items query.
     * @param array|string $filterValue Selected gathering IDs or the none sentinel.
     * @param array<string, mixed> $context Trait-supplied context (unused).
     * @return \Cake\ORM\Query\SelectQuery Filtered query.
     */
    public static function applyGatheringFilter($query, $filterValue, array $context = []): SelectQuery
    {
        $values = is_array($filterValue) ? $filterValue : [$filterValue];
        $includeNone = false;
        $gatheringIds = [];

        foreach ($values as $value) {
            if ((string)$value === self::NO_GATHERING_FILTER_VALUE) {
                $includeNone = true;
                continue;
            }

            $gatheringId = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($gatheringId !== false) {
                $gatheringIds[(int)$gatheringId] = (int)$gatheringId;
            }
        }

        if (!$includeNone && $gatheringIds === []) {
            return $query->where(['1 = 0']);
        }

        $bestowals = TableRegistry::getTableLocator()->get('Awards.Bestowals');
        $conditions = [];

        if ($gatheringIds !== []) {
            $matchingBestowals = $bestowals->find()
                ->select(['Bestowals.id'])
                ->innerJoinWith('Gatherings')
                ->where(['Bestowals.gathering_id IN' => array_values($gatheringIds)]);
            $conditions[] = [
                'ActionItems.entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE,
                'ActionItems.entity_id IN' => $matchingBestowals,
            ];
        }

        if ($includeNone) {
            $bestowalsWithGatherings = $bestowals->find()
                ->select(['Bestowals.id'])
                ->innerJoinWith('Gatherings');
            $conditions[] = [
                'OR' => [
                    ['ActionItems.entity_type !=' => Bestowal::ACTION_ITEM_ENTITY_TYPE],
                    [
                        'ActionItems.entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE,
                        'ActionItems.entity_id NOT IN' => $bestowalsWithGatherings,
                    ],
                ],
            ];
        }

        return $query->where(['OR' => $conditions]);
    }

    /**
     * Row actions for the My To-Dos grid.
     *
     * Complete is offered on open items; Reopen on completed items. Both open the
     * shared completion modal, which reads the row's id/title/mode from the
     * triggering button via Bootstrap's show.bs.modal event.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getRowActions(): array
    {
        return [
            'complete' => [
                'key' => 'complete',
                'type' => 'modal',
                'label' => 'Complete',
                'class' => 'btn btn-sm btn-success',
                'modalTarget' => '#todoCompleteModal',
                'statusFilter' => [ActionItem::STATUS_OPEN],
                'dataAttributes' => [
                    'todo-id' => '{{id}}',
                    'todo-title' => '{{title}}',
                    'todo-mode' => 'complete',
                    'todo-completion-form' => ['_rowField' => 'completion_form_data'],
                ],
            ],
            'reopen' => [
                'key' => 'reopen',
                'type' => 'modal',
                'label' => 'Reopen',
                'icon' => 'bi-arrow-counterclockwise',
                'class' => 'btn btn-sm btn-outline-secondary',
                'modalTarget' => '#todoCompleteModal',
                'statusFilter' => [ActionItem::STATUS_COMPLETED],
                'dataAttributes' => [
                    'todo-id' => '{{id}}',
                    'todo-title' => '{{title}}',
                    'todo-mode' => 'reopen',
                ],
            ],
        ];
    }

    /**
     * System views for the My To-Dos grid.
     *
     * @param array<string, mixed> $options Runtime context (unused).
     * @return array<string, array<string, mixed>>
     */
    public static function getSystemViews(array $options = []): array
    {
        return [
            'sys-todos-open' => [
                'id' => 'sys-todos-open',
                'name' => __('Open To-Dos'),
                'description' => __('To-dos waiting for you to act on'),
                'canManage' => false,
                'config' => [
                    'filters' => [
                        ['field' => 'status_label', 'operator' => 'eq', 'value' => ActionItem::STATUS_OPEN],
                        [
                            'field' => 'member_scope',
                            'operator' => 'eq',
                            'value' => self::MEMBER_SCOPE_ACTIONABLE,
                        ],
                    ],
                    'columns' => [
                        ['key' => 'title', 'visible' => true, 'order' => 0],
                        ['key' => 'owner', 'visible' => true, 'order' => 1],
                        ['key' => 'requirement', 'visible' => true, 'order' => 2],
                        ['key' => 'branch', 'visible' => false, 'order' => 3],
                        ['key' => 'status_label', 'visible' => false, 'order' => 4],
                    ],
                ],
            ],
            'sys-todos-completed' => [
                'id' => 'sys-todos-completed',
                'name' => __('Completed'),
                'description' => __('To-dos you have completed'),
                'canManage' => false,
                'config' => [
                    'filters' => [
                        ['field' => 'status_label', 'operator' => 'eq', 'value' => ActionItem::STATUS_COMPLETED],
                        [
                            'field' => 'member_scope',
                            'operator' => 'eq',
                            'value' => self::MEMBER_SCOPE_COMPLETED_BY_ME,
                        ],
                    ],
                    'columns' => [
                        ['key' => 'title', 'visible' => true, 'order' => 0],
                        ['key' => 'owner', 'visible' => true, 'order' => 1],
                        ['key' => 'completed_at', 'visible' => true, 'order' => 2],
                        ['key' => 'requirement', 'visible' => false, 'order' => 3],
                    ],
                ],
            ],
        ];
    }
}
