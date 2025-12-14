<?php

declare(strict_types=1);

namespace Activities\KMP\GridColumns;

use App\KMP\GridColumns\BaseGridColumns;

/**
 * Authorization Approver Rollup Grid Columns
 *
 * Defines the aggregated approver queue columns for the authorization approvals index grid.
 */
class AuthorizationApproverRollupGridColumns extends BaseGridColumns
{
    /**
     * Get row actions for the rollup grid
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
                'icon' => 'bi-binoculars-fill',
                'class' => 'btn btn-sm btn-secondary',
                'permission' => 'view',
                'url' => [
                    'plugin' => 'Activities',
                    'controller' => 'AuthorizationApprovals',
                    'action' => 'view',
                    'idField' => 'id',
                ],
            ],
        ];
    }

    /**
     * Get column metadata for the authorization approver rollup grid
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getColumns(): array
    {
        return [
            'approver_name' => [
                'key' => 'approver_name',
                'label' => 'Approver',
                'type' => 'string',
                'sortable' => true,
                'filterable' => false,
                'searchable' => true,
                'queryField' => 'Approvers.sca_name',
                'defaultVisible' => true,
                'required' => true,
                'width' => '240px',
                'alignment' => 'left',
                'clickAction' => 'navigate:/activities/authorization-approvals/view/:id',
            ],

            'last_login' => [
                'key' => 'last_login',
                'label' => 'Last Login',
                'type' => 'datetime',
                'sortable' => true,
                'filterable' => false,
                'queryField' => 'Approvers.last_login',
                'defaultVisible' => true,
                'width' => '180px',
                'alignment' => 'left',
            ],

            'pending_count' => [
                'key' => 'pending_count',
                'label' => 'Pending',
                'type' => 'number',
                'sortable' => true,
                'filterable' => false,
                'queryField' => 'pending_count',
                'defaultVisible' => true,
                'width' => '110px',
                'alignment' => 'center',
            ],

            'approved_count' => [
                'key' => 'approved_count',
                'label' => 'Approved',
                'type' => 'number',
                'sortable' => true,
                'filterable' => false,
                'queryField' => 'approved_count',
                'defaultVisible' => true,
                'width' => '110px',
                'alignment' => 'center',
            ],

            'denied_count' => [
                'key' => 'denied_count',
                'label' => 'Denied',
                'type' => 'number',
                'sortable' => true,
                'filterable' => false,
                'queryField' => 'denied_count',
                'defaultVisible' => true,
                'width' => '110px',
                'alignment' => 'center',
            ],
        ];
    }

    /**
     * Expand searchable columns for normalized approver lookups
     *
     * @return array<int, string>
     */
    public static function getSearchableColumns(): array
    {
        return [
            'Approvers.sca_name',
            'Approvers.email_address',
        ];
    }
}
