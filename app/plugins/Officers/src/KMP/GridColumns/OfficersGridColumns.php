<?php
declare(strict_types=1);

namespace Officers\KMP\GridColumns;

use App\KMP\GridColumns\BaseGridColumns;
use App\Model\Entity\ActiveWindowBaseEntity;
use Officers\Model\Entity\Officer;

/**
 * Officers Grid Column Metadata
 *
 * Defines the column configuration for the Officers Dataverse-style grid view.
 * Officers represent member assignments to offices within branches, with
 * temporal management and warrant integration.
 *
 * This grid is used in multiple contexts:
 * - Officers index: All officers filtered by warrant status
 * - Member officers tab: Officers for a specific member
 * - Branch officers tab: Officers for a specific branch
 */
class OfficersGridColumns extends BaseGridColumns
{
    /**
     * Get row actions for officers grid
     *
     * Returns action configurations for Current/Upcoming officers.
     * Previous officers have no actions.
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
                'class' => 'btn-sm btn btn-primary edit-btn',
                'modalTarget' => '#editOfficerModal',
                'permission' => 'edit',
                'condition' => ['is_editable' => true],
                'dataAttributes' => [
                    'controller' => 'outlet-btn',
                    'action' => 'click->outlet-btn#fireNotice',
                    'outlet-btn-btn-data-value' => [
                        'id' => 'id',
                        'is_deputy' => 'office.is_deputy',
                        'email_address' => 'email_address',
                        'deputy_description' => 'deputy_description',
                    ],
                ],
                'statusFilter' => [
                    ActiveWindowBaseEntity::CURRENT_STATUS,
                    ActiveWindowBaseEntity::UPCOMING_STATUS,
                ],
            ],
            'requestWarrant' => [
                'key' => 'requestWarrant',
                'type' => 'postLink',
                'label' => 'Request Warrant',
                'icon' => null,
                'class' => 'btn-sm btn btn-warning',
                'permission' => 'requestWarrant',
                'condition' => ['warrant_state' => 'Missing'],
                'url' => [
                    'plugin' => 'Officers',
                    'controller' => 'Officers',
                    'action' => 'requestWarrant',
                    'idField' => 'id',
                ],
                'confirmMessage' => 'Are you sure you want to request a new warrant for {{member.sca_name}}?',
                'statusFilter' => [
                    ActiveWindowBaseEntity::CURRENT_STATUS,
                    ActiveWindowBaseEntity::UPCOMING_STATUS,
                ],
            ],
            'release' => [
                'key' => 'release',
                'type' => 'modal',
                'label' => 'Release',
                'icon' => null,
                'class' => 'btn-sm btn btn-danger revoke-btn',
                'modalTarget' => '#releaseModal',
                'permission' => 'release',
                'dataAttributes' => [
                    'controller' => 'outlet-btn',
                    'action' => 'click->outlet-btn#fireNotice',
                    'outlet-btn-btn-data-value' => [
                        'id' => 'id',
                    ],
                ],
                'statusFilter' => [
                    ActiveWindowBaseEntity::CURRENT_STATUS,
                    ActiveWindowBaseEntity::UPCOMING_STATUS,
                ],
            ],
        ];
    }

    /**
     * Get column metadata for officers grid
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

            'member_sca_name' => [
                'key' => 'member_sca_name',
                'label' => 'Member',
                'type' => 'relation',
                'sortable' => true,
                'searchable' => true,
                'filterable' => false,
                'defaultVisible' => true,
                'width' => '200px',
                'alignment' => 'left',
                'renderField' => 'member.sca_name',
                'queryField' => 'Members.sca_name',
                'clickAction' => 'navigate:/members/view/:member_id',
                'clickActionPermission' => [
                    'ability' => 'view',
                    'subjectField' => 'member',
                ],
                'description' => 'Assigned member SCA name',
            ],

            'office_name' => [
                'key' => 'office_name',
                'label' => 'Office',
                'type' => 'relation',
                'sortable' => true,
                'searchable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'defaultVisible' => true,
                'width' => '200px',
                'alignment' => 'left',
                'renderField' => 'office.name',
                'queryField' => 'Offices.name',
                'description' => 'Office position held',
                'cellRenderer' => function ($value, $row, $view) {
                    $officeName = $row->office->name ?? null;
                    if ($officeName === null) {
                        return '<span class="text-muted">—</span>';
                    }
                    if (!empty($row->deputy_description)) {
                        $officeName .= ' (' . $row->deputy_description . ')';
                    }

                    return h($officeName);
                },
                'exportValue' => function ($entity, $columnKey, $columnMeta) {
                    $officeName = $entity->office->name ?? '';
                    if (!empty($entity->deputy_description)) {
                        $officeName .= ' (' . $entity->deputy_description . ')';
                    }

                    return $officeName;
                },
            ],

            'deputy_description' => [
                'key' => 'deputy_description',
                'label' => 'Deputy Role',
                'type' => 'string',
                'sortable' => false,
                'filterable' => false,
                'searchable' => true,
                'defaultVisible' => false,
                'width' => '180px',
                'alignment' => 'left',
                'description' => 'Custom description for deputy positions',
            ],

            'branch_name' => [
                'key' => 'branch_name',
                'label' => 'Branch',
                'type' => 'relation',
                'sortable' => true,
                'searchable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'defaultVisible' => true,
                'width' => '180px',
                'alignment' => 'left',
                'renderField' => 'branch.name',
                'queryField' => 'Branches.name',
                'description' => 'Branch where officer is assigned',
            ],

            'email_address' => [
                'key' => 'email_address',
                'label' => 'Contact',
                'type' => 'email',
                'sortable' => false,
                'filterable' => false,
                'searchable' => true,
                'defaultVisible' => true,
                'width' => '200px',
                'alignment' => 'left',
                'description' => 'Officer contact email address',
            ],

            'warrant_state' => [
                'key' => 'warrant_state',
                'label' => 'Warrant',
                'type' => 'badge',
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'dropdown',
                'defaultVisible' => true,
                'width' => '120px',
                'alignment' => 'center',
                'filterOptions' => [
                    ['value' => 'Active', 'label' => 'Active'],
                    ['value' => 'Pending', 'label' => 'Pending'],
                    ['value' => 'Missing', 'label' => 'Missing'],
                    ['value' => 'Not Required', 'label' => 'Not Required'],
                ],
                'description' => 'Current warrant status for this officer',
                'cellRenderer' => function ($value, $row, $view) {
                    $badgeClasses = [
                        'Active' => 'bg-success',
                        'Pending' => 'bg-warning text-dark',
                        'Missing' => 'bg-danger',
                        'Not Required' => 'bg-secondary',
                    ];
                    $class = $badgeClasses[$value] ?? 'bg-secondary';
                    $text = $value ?? 'Unknown';

                    return '<span class="badge ' . $class . '">' . h($text) . '</span>';
                },
            ],

            'member_warrant_summary' => [
                'key' => 'member_warrant_summary',
                'label' => 'Warrant',
                'type' => 'html',
                'sortable' => false,
                'filterable' => false,
                'defaultVisible' => false,
                'width' => '180px',
                'alignment' => 'left',
                'exportable' => false,
                'description' => 'Current or most recent warrant expiration; activate to show warrant history',
                'clickAction' => 'toggleSubRow:warrant-history',
                'clickActionUrl' => '/officers/officers/warrant-history/:id',
                'cellRenderer' => function ($value, $row, $view) {
                    $status = 'Missing';
                    $badgeClass = 'bg-danger';
                    $dateLabel = null;
                    $date = null;

                    if ($row->current_warrant !== null) {
                        $status = 'Current';
                        $badgeClass = 'bg-success';
                        $dateLabel = __('Expires');
                        $date = $row->current_warrant->expires_on;
                    } elseif (!empty($row->pending_warrants)) {
                        $pendingWarrant = $row->pending_warrants[0];
                        $status = 'Pending';
                        $badgeClass = 'bg-warning text-dark';
                        $dateLabel = __('Expires');
                        $date = $pendingWarrant->expires_on;
                    } elseif (!empty($row->warrants)) {
                        $latestWarrant = $row->warrants[0];
                        $status = $latestWarrant->status ?? 'Expired';
                        $badgeClass = 'bg-secondary';
                        $dateLabel = $status === 'Expired' ? __('Expired') : __('Ended');
                        $date = $latestWarrant->expires_on;
                    } elseif ($row->office?->requires_warrant !== true) {
                        $status = 'Not Required';
                        $badgeClass = 'bg-secondary';
                    }

                    $accessibleSummary = __('Show warrant history: {0}.', $status);
                    $compactDate = null;
                    if ($date !== null) {
                        $fullDate = $view->Timezone->date($date);
                        $compactDate = $view->Timezone->date($date, 'M j, Y');
                        $accessibleSummary = __('Show warrant history: {0}. {1} {2}.', $status, $dateLabel, $fullDate);
                    }

                    $summary = '<span class="visually-hidden">' . h($accessibleSummary) . '</span>';
                    $summary .= '<span class="d-inline-flex align-items-center gap-1 text-nowrap" aria-hidden="true">';
                    $summary .= '<span class="badge ' . h($badgeClass) . '">' . h($status) . '</span>';
                    if ($compactDate !== null) {
                        $summary .= '<span class="small text-body-secondary">'
                            . h(__('Exp. {0}', $compactDate))
                            . '</span>';
                    }
                    $summary .= '</span>';

                    return $summary;
                },
            ],

            'start_on' => [
                'key' => 'start_on',
                'label' => 'Start Date',
                'type' => 'date',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date-range',
                'defaultVisible' => true,
                'width' => '120px',
                'alignment' => 'left',
                'description' => 'Assignment start date',
            ],

            'expires_on' => [
                'key' => 'expires_on',
                'label' => 'End Date',
                'type' => 'date',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date-range',
                'defaultVisible' => true,
                'width' => '120px',
                'alignment' => 'left',
                'description' => 'Assignment expiration date',
            ],

            'status' => [
                'key' => 'status',
                'label' => 'Status',
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'defaultVisible' => true,
                'width' => '120px',
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
                        'value' => ActiveWindowBaseEntity::RELEASED_STATUS,
                        'label' => ActiveWindowBaseEntity::RELEASED_STATUS,
                    ],
                    [
                        'value' => ActiveWindowBaseEntity::REPLACED_STATUS,
                        'label' => ActiveWindowBaseEntity::REPLACED_STATUS,
                    ],
                    [
                        'value' => ActiveWindowBaseEntity::DEACTIVATED_STATUS,
                        'label' => ActiveWindowBaseEntity::DEACTIVATED_STATUS,
                    ],
                ],
                'description' => 'Current officer assignment status',
            ],

            'reports_to_list' => [
                'key' => 'reports_to_list',
                'label' => 'Reports To',
                'type' => 'html',
                'sortable' => false,
                'filterable' => false,
                'defaultVisible' => false,
                'width' => '200px',
                'alignment' => 'left',
                'exportable' => false,
                'description' => 'Officers this position reports to (with mailto links)',
            ],

            'revoked_reason' => [
                'key' => 'revoked_reason',
                'label' => 'Release Reason',
                'type' => 'string',
                'sortable' => false,
                'filterable' => false,
                'defaultVisible' => false,
                'width' => '200px',
                'alignment' => 'left',
                'description' => 'Reason for assignment revocation/release',
            ],
        ];
    }

    /**
     * System views for officers dv_grid.
     *
     * @param array<string, mixed> $options
     * @return array<string, array<string, mixed>>
     */
    public static function getSystemViews(array $options = []): array
    {
        $context = $options['context'] ?? null;

        $currentUpcomingColumns = match ($context) {
            'member' => [
                'office_name', 'branch_name', 'email_address', 'member_warrant_summary',
                'start_on', 'expires_on', 'reports_to_list',
            ],
            'branch' => [
                'member_sca_name', 'office_name', 'email_address', 'warrant_state',
                'start_on', 'expires_on', 'reports_to_list',
            ],
            default => [
                'member_sca_name', 'office_name', 'branch_name', 'email_address',
                'warrant_state', 'start_on', 'expires_on', 'status',
            ],
        };

        $previousColumns = match ($context) {
            'member' => [
                'office_name', 'branch_name', 'member_warrant_summary',
                'start_on', 'expires_on', 'revoked_reason',
            ],
            'branch' => ['member_sca_name', 'office_name', 'start_on', 'expires_on', 'revoked_reason'],
            default => [
                'member_sca_name', 'office_name', 'branch_name', 'start_on',
                'expires_on', 'revoked_reason', 'status',
            ],
        };

        return [
            'sys-officers-current' => [
                'id' => 'sys-officers-current',
                'name' => __('Current'),
                'description' => __('Active officer assignments'),
                'canManage' => false,
                'canFilter' => true,
                'config' => [
                    'filters' => [
                        ['field' => 'status', 'operator' => 'eq', 'value' => Officer::CURRENT_STATUS],
                    ],
                    'columns' => $currentUpcomingColumns,
                ],
            ],
            'sys-officers-upcoming' => [
                'id' => 'sys-officers-upcoming',
                'name' => __('Upcoming'),
                'description' => __('Future officer assignments'),
                'canManage' => false,
                'canFilter' => true,
                'config' => [
                    'filters' => [
                        ['field' => 'status', 'operator' => 'eq', 'value' => Officer::UPCOMING_STATUS],
                    ],
                    'columns' => $currentUpcomingColumns,
                ],
            ],
            'sys-officers-previous' => [
                'id' => 'sys-officers-previous',
                'name' => __('Previous'),
                'description' => __('Past officer assignments'),
                'canManage' => false,
                'canFilter' => true,
                'config' => [
                    'filters' => [
                        ['field' => 'status', 'operator' => 'in', 'value' => [
                            Officer::EXPIRED_STATUS,
                            Officer::DEACTIVATED_STATUS,
                            Officer::RELEASED_STATUS,
                            Officer::REPLACED_STATUS,
                        ]],
                    ],
                    'columns' => $previousColumns,
                ],
            ],
        ];
    }
}
