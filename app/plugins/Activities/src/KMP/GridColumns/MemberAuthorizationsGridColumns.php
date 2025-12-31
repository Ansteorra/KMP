<?php

declare(strict_types=1);

namespace Activities\KMP\GridColumns;

use App\KMP\GridColumns\BaseGridColumns;
use Activities\Model\Entity\Authorization;
use Cake\I18n\FrozenDate;
use Cake\I18n\DateTime;

/**
 * Grid column definitions for Member Authorizations listing.
 *
 * Used to display authorizations on member profile page with tabs for
 * current, pending, and previous authorizations.
 */
class MemberAuthorizationsGridColumns extends BaseGridColumns
{
    /**
     * Get all available columns for the member authorizations grid
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getColumns(): array
    {
        return [
            'activity_name' => [
                'key' => 'activity_name',
                'queryField' => 'Activities.name',
                'renderField' => 'activity.name',
                'label' => 'Authorization',
                'type' => 'relation',
                'sortable' => true,
                'filterable' => false,
                'searchable' => true,
                'defaultVisible' => true,
            ],
            'start_on' => [
                'key' => 'start_on',
                'queryField' => 'Authorizations.start_on',
                'label' => 'Start Date',
                'type' => 'date',
                'sortable' => true,
                'filterable' => true,
                'defaultVisible' => true,
                'filterType' => 'date-range',
            ],
            'expires_on' => [
                'key' => 'expires_on',
                'queryField' => 'Authorizations.expires_on',
                'label' => 'End Date',
                'type' => 'date',
                'sortable' => true,
                'filterable' => true,
                'defaultVisible' => true,
                'filterType' => 'date-range',
            ],
            'start_on_populated' => [
                'key' => 'start_on',
                'queryField' => 'Authorizations.start_on',
                'label' => 'Start Date',
                'type' => 'date',
                'sortable' => true,
                'filterable' => true,
                'defaultVisible' => false,
                'filterType' => 'is-populated',
            ],
            'expires_on_populated' => [
                'key' => 'expires_on',
                'queryField' => 'Authorizations.expires_on',
                'label' => 'End Date',
                'type' => 'date',
                'sortable' => true,
                'filterable' => true,
                'defaultVisible' => false,
                'filterType' => 'is-populated',
            ],
            'requested_on' => [
                'key' => 'requested_on',
                'queryField' => 'CurrentPendingApprovals.requested_on',
                'renderField' => 'current_pending_approval.requested_on',
                'label' => 'Requested Date',
                'type' => 'date',
                'sortable' => true,
                'filterable' => false,
                'defaultVisible' => false,

            ],
            'responded_on' => [
                'key' => 'responded_on',
                'queryField' => 'CurrentPendingApprovals.responded_on',
                'renderField' => 'current_pending_approval.responded_on',
                'label' => 'Responded',
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
            'approver_sca_name' => [
                'key' => 'approver_sca_name',
                'queryField' => 'Approvers.sca_name',
                'renderField' => 'current_pending_approval.approver.sca_name',
                'label' => 'Assigned To',
                'type' => 'relation',
                'sortable' => false,
                'filterable' => false,
                'searchable' => false,
                'defaultVisible' => false,
            ],
            'revoked_reason' => [
                'key' => 'revoked_reason',
                'queryField' => 'Authorizations.revoked_reason',
                'label' => 'Reason',
                'type' => 'string',
                'sortable' => false,
                'filterable' => false,
                'searchable' => false,
                'defaultVisible' => false,
            ],
        ];
    }

    /**
     * Get columns for the "current" (active) authorizations view
     *
     * @return array<string>
     */
    public static function getCurrentViewColumns(): array
    {
        return ['activity_name', 'start_on', 'expires_on'];
    }

    /**
     * Get columns for the "pending" authorizations view
     *
     * @return array<string>
     */
    public static function getPendingViewColumns(): array
    {
        return ['activity_name', 'requested_on', 'approver_sca_name'];
    }

    /**
     * Get columns for the "previous" authorizations view
     *
     * @return array<string>
     */
    public static function getPreviousViewColumns(): array
    {
        return ['activity_name', 'start_on', 'expires_on', 'revoked_reason'];
    }

    /**
     * Get system views for member authorizations
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getSystemViews(array $options = []): array
    {
        $today = FrozenDate::today();
        //we need to make today the last moment of the day to include authorizations that expire today
        $today = DateTime::createFromFormat('Y-m-d H:i:s', $today->format('Y-m-d') . ' 23:59:59');
        $todayString = $today->format('Y-m-d H:i:s');
        return [
            'current' => [
                'id' => 'current',
                'name' => __('Active'),
                'description' => __('Currently active authorizations'),
                'canManage' => false,
                'config' => [
                    'columns' => self::getCurrentViewColumns(),
                    'filters' => [
                        [
                            'field' => 'start_on',
                            'operator' => 'lt',
                            'value' => $todayString,
                        ],
                        [
                            'field' => 'expires_on',
                            'operator' => 'gt',
                            'value' => $todayString,
                        ]
                    ],
                ],
            ],
            'pending' => [
                'id' => 'pending',
                'name' => __('Pending'),
                'description' => __('Authorization requests awaiting approval'),
                'canManage' => false,
                'config' => [
                    'columns' => self::getPendingViewColumns(),
                    'filters' => [
                        [
                            'field' => 'start_on_populated',
                            'operator' => 'is-populated',
                            'value' => 'no',
                        ],
                        [
                            'field' => 'expires_on_populated',
                            'operator' => 'is-populated',
                            'value' => 'no',
                        ]
                    ],
                ],
            ],
            'previous' => [
                'id' => 'previous',
                'name' => __('Previous'),
                'description' => __('Expired, revoked, or denied authorizations'),
                'canManage' => false,
                'config' => [
                    'columns' => self::getPreviousViewColumns(),
                    'filters' => [
                        [
                            'field' => 'expires_on',
                            'operator' => 'lt',
                            'value' => $todayString,
                        ],
                    ],
                ],
            ],
        ];
    }
}