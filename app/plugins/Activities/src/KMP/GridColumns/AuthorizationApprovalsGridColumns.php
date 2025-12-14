<?php

declare(strict_types=1);

namespace Activities\KMP\GridColumns;

use App\KMP\GridColumns\BaseGridColumns;

/**
 * Grid column definitions for Authorization Approvals listing.
 *
 * Used for the authorization approval queue views (myQueue and view actions).
 */
class AuthorizationApprovalsGridColumns extends BaseGridColumns
{
    /**
     * Get all available columns for the authorization approvals grid
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getColumns(): array
    {
        return [
            'requester_name' => [
                'key' => 'requester_name',
                'queryField' => 'Members.sca_name',
                'renderField' => 'authorization.member.sca_name',
                'label' => 'Requester',
                'type' => 'relation',
                'sortable' => true,
                'filterable' => false,
                'searchable' => true,
                'defaultVisible' => true,
                'clickAction' => 'navigate:/members/view/:authorization.member_id',
            ],
            'requested_on' => [
                'key' => 'requested_on',
                'queryField' => 'AuthorizationApprovals.requested_on',
                'label' => 'Request Date',
                'type' => 'date',
                'sortable' => true,
                'filterable' => true,
                'defaultVisible' => true,
                'filterType' => 'dateRange',
            ],
            'responded_on' => [
                'key' => 'responded_on',
                'queryField' => 'AuthorizationApprovals.responded_on',
                'label' => 'Response Date',
                'type' => 'date',
                'sortable' => true,
                'filterable' => true,
                'defaultVisible' => false,
                'filterType' => 'is-populated',
                'filterOptions' => [
                    ['value' => 'yes', 'label' => 'Yes'],
                    ['value' => 'no', 'label' => 'No'],
                ],
            ],
            'activity_name' => [
                'key' => 'activity_name',
                'queryField' => 'Activities.name',
                'renderField' => 'authorization.activity.name',
                'label' => 'Authorization',
                'type' => 'relation',
                'sortable' => true,
                'filterable' => false,
                'searchable' => false,
                'defaultVisible' => true,
            ],
            'background_check_expires_on' => [
                'key' => 'background_check_expires_on',
                'queryField' => 'Members.background_check_expires_on',
                'renderField' => 'authorization.member.background_check_expires_on',
                'label' => 'Background Check Exp',
                'type' => 'date',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => true,
            ],
            'approved' => [
                'key' => 'approved',
                'queryField' => 'AuthorizationApprovals.approved',
                'label' => 'Approved',
                'type' => 'boolean',
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'dropdown',
                'filterOptions' => [
                    ['value' => '1', 'label' => 'Yes'],
                    ['value' => '0', 'label' => 'No'],
                ],
                'defaultVisible' => false,
            ],
            'approver_notes' => [
                'key' => 'approver_notes',
                'queryField' => 'AuthorizationApprovals.approver_notes',
                'label' => 'Denial Reason',
                'type' => 'string',
                'sortable' => false,
                'filterable' => false,
                'searchable' => false,
                'defaultVisible' => false,
            ],
        ];
    }

    /**
     * Get columns for the "pending" queue view
     *
     * @return array<string>
     */
    public static function getPendingViewColumns(): array
    {
        return [
            'requester_name',
            'requested_on',
            'activity_name',
        ];
    }

    /**
     * Get columns for the "approved" queue view
     *
     * @return array<string>
     */
    public static function getApprovedViewColumns(): array
    {
        return [
            'requester_name',
            'requested_on',
            'responded_on',
            'activity_name',
        ];
    }

    /**
     * Get columns for the "denied" queue view
     *
     * @return array<string>
     */
    public static function getDeniedViewColumns(): array
    {
        return [
            'requester_name',
            'requested_on',
            'responded_on',
            'activity_name',
            'approver_notes',
        ];
    }

    /**
     * Get system views for authorization approval queues
     *
     * Matches controller query logic in getQueueSystemViewCounts():
     * - Pending: responded_on IS NULL
     * - Approved: responded_on IS NOT NULL AND approved = true
     * - Denied: responded_on IS NOT NULL AND approved = false
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getSystemViews(array $options = []): array
    {
        return [
            'pending' => [
                'id' => 'pending',
                'name' => __('Pending'),
                'description' => __('Pending authorization requests awaiting approval'),
                'canManage' => false,
                'config' => [
                    'columns' => self::getPendingViewColumns(),
                    'filters' => [
                        [
                            'field' => 'responded_on',
                            'operator' => 'is-populated',
                            'value' => 'no',
                        ],
                    ],
                ],
            ],
            'approved' => [
                'id' => 'approved',
                'name' => __('Approved'),
                'description' => __('Previously approved authorization requests'),
                'canManage' => false,
                'config' => [
                    'columns' => self::getApprovedViewColumns(),
                    'filters' => [
                        [
                            'field' => 'responded_on',
                            'operator' => 'is-populated',
                            'value' => 'yes',
                        ],
                        [
                            'field' => 'approved',
                            'operator' => 'eq',
                            'value' => '1',
                        ],
                    ],
                ],
            ],
            'denied' => [
                'id' => 'denied',
                'name' => __('Denied'),
                'description' => __('Previously denied authorization requests'),
                'canManage' => false,
                'config' => [
                    'columns' => self::getDeniedViewColumns(),
                    'filters' => [
                        [
                            'field' => 'responded_on',
                            'operator' => 'is-populated',
                            'value' => 'yes',
                        ],
                        [
                            'field' => 'approved',
                            'operator' => 'eq',
                            'value' => '0',
                        ],
                    ],
                ],
            ],
        ];
    }
}