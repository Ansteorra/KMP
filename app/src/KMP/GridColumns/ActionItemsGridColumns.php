<?php
declare(strict_types=1);

namespace App\KMP\GridColumns;

use App\Model\Entity\ActionItem;

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
                'filterable' => false,
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
                'filterable' => false,
                'searchable' => false,
                'defaultVisible' => true,
                'width' => '120px',
                'alignment' => 'center',
                'queryField' => 'ActionItems.is_gating',
                'skipAutoFilter' => true,
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
                'icon' => 'bi-check2',
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
