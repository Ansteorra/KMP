<?php

declare(strict_types=1);

namespace Awards\KMP\GridColumns;

use App\KMP\GridColumns\BaseGridColumns;
use App\KMP\StaticHelpers;
use Awards\Model\Entity\Recommendation;

/**
 * Recommendations Grid Column Metadata
 *
 * Defines the column configuration for the Award Recommendations Dataverse-style grid view.
 * Recommendations represent award suggestions that flow through a complex state machine
 * from submission through ceremonial presentation.
 *
 * This grid is used in multiple contexts:
 * - Recommendations index: All recommendations with status-based tabs
 * - Member submitted recs tab: Recommendations submitted by a specific member
 * - Recs for member tab: Recommendations for a specific member
 * - Gathering awards tab: Recommendations scheduled for a specific gathering
 */
class RecommendationsGridColumns extends BaseGridColumns
{
    /**
     * Get row actions for recommendations grid
     *
     * Returns action configurations for recommendations.
     * Edit is modal-based, View is a link.
     * State changes are handled through bulk selection modal, not row actions.
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
                'class' => 'btn-sm btn btn-primary edit-rec',
                'modalTarget' => '#editRecommendationModal',
                'permission' => 'edit',
                'dataAttributes' => [
                    'controller' => 'outlet-btn',
                    'action' => 'click->outlet-btn#fireNotice',
                    'outlet-btn-btn-data-value' => [
                        'id' => 'id',
                    ],
                ],
            ],
            'view' => [
                'key' => 'view',
                'type' => 'link',
                'label' => '',
                'icon' => 'bi-eye-fill',
                'class' => 'btn-sm btn btn-secondary',
                'permission' => 'view',
                'url' => [
                    'plugin' => 'Awards',
                    'controller' => 'Recommendations',
                    'action' => 'view',
                    'idField' => 'id',
                ],
            ],
        ];
    }

    /**
     * Get column metadata for recommendations grid
     *
     * Defines all available columns for the recommendations grid.
     * Different system views will show different subsets of these columns.
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
                'width' => '60px',
                'alignment' => 'right',
            ],

            'created' => [
                'key' => 'created',
                'label' => 'Submitted',
                'type' => 'date',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date-range',
                'defaultVisible' => true,
                'width' => '110px',
                'alignment' => 'left',
                'description' => 'Date recommendation was submitted',
            ],

            'member_sca_name' => [
                'key' => 'member_sca_name',
                'label' => 'For',
                'type' => 'string',
                'sortable' => true,
                'searchable' => true,
                'filterable' => false,
                'defaultVisible' => true,
                'width' => '180px',
                'alignment' => 'left',
                'clickAction' => 'navigate:/members/view/:member_id',
                'description' => 'SCA name of member being recommended',
            ],

            'member_for_herald' => [
                'key' => 'member_for_herald',
                'label' => 'For Herald',
                'type' => 'string',
                'sortable' => false,
                'searchable' => false,
                'filterable' => false,
                'defaultVisible' => false,
                'width' => '180px',
                'alignment' => 'left',
                'renderField' => 'member.name_for_herald',
                'exportOnly' => true,
                'description' => 'Herald-formatted name of member being recommended',
            ],

            'member_title' => [
                'key' => 'member_title',
                'label' => 'Title',
                'type' => 'string',
                'sortable' => false,
                'searchable' => false,
                'filterable' => false,
                'defaultVisible' => false,
                'width' => '100px',
                'alignment' => 'left',
                'renderField' => 'member.title',
                'exportOnly' => true,
                'description' => 'Title of member being recommended',
            ],

            'member_pronouns' => [
                'key' => 'member_pronouns',
                'label' => 'Pronouns',
                'type' => 'string',
                'sortable' => false,
                'searchable' => false,
                'filterable' => false,
                'defaultVisible' => false,
                'width' => '100px',
                'alignment' => 'left',
                'renderField' => 'member.pronouns',
                'exportOnly' => true,
                'description' => 'Pronouns of member being recommended',
            ],

            'member_pronunciation' => [
                'key' => 'member_pronunciation',
                'label' => 'Pronunciation',
                'type' => 'string',
                'sortable' => false,
                'searchable' => false,
                'filterable' => false,
                'defaultVisible' => false,
                'width' => '150px',
                'alignment' => 'left',
                'renderField' => 'member.pronunciation',
                'exportOnly' => true,
                'description' => 'Name pronunciation guide',
            ],

            'op_links' => [
                'key' => 'op_links',
                'label' => 'OP',
                'type' => 'html',
                'sortable' => false,
                'searchable' => false,
                'filterable' => false,
                'defaultVisible' => true,
                'exportable' => false,
                'width' => '80px',
                'alignment' => 'center',
                'description' => 'Order of Precedence external links',
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
                'width' => '150px',
                'alignment' => 'left',
                'renderField' => 'branch.name',
                'queryField' => 'Branches.name',
                'description' => 'Branch of member being recommended',
            ],

            'call_into_court' => [
                'key' => 'call_into_court',
                'label' => 'Call Into Court',
                'type' => 'string',
                'sortable' => true,
                'searchable' => false,
                'filterable' => true,
                'filterType' => 'dropdown',
                'defaultVisible' => false,
                'width' => '130px',
                'alignment' => 'left',
                'filterOptions' => [
                    ['value' => 'Never', 'label' => 'Never'],
                    ['value' => 'With Notice', 'label' => 'With Notice'],
                    ['value' => 'Without Notice', 'label' => 'Without Notice'],
                ],
                'description' => 'Court protocol preference',
            ],

            'court_availability' => [
                'key' => 'court_availability',
                'label' => 'Court Avail',
                'type' => 'string',
                'sortable' => true,
                'searchable' => false,
                'filterable' => true,
                'filterType' => 'dropdown',
                'defaultVisible' => false,
                'width' => '120px',
                'alignment' => 'left',
                'filterOptions' => [
                    ['value' => 'None', 'label' => 'None'],
                    ['value' => 'Morning', 'label' => 'Morning'],
                    ['value' => 'Evening', 'label' => 'Evening'],
                    ['value' => 'Any', 'label' => 'Any'],
                ],
                'description' => 'Court availability preference',
            ],

            'person_to_notify' => [
                'key' => 'person_to_notify',
                'label' => 'Person to Notify',
                'type' => 'string',
                'sortable' => false,
                'searchable' => true,
                'filterable' => false,
                'defaultVisible' => false,
                'width' => '150px',
                'alignment' => 'left',
                'description' => 'Contact person for ceremony coordination',
            ],

            'requester_sca_name' => [
                'key' => 'requester_sca_name',
                'label' => 'Submitted By',
                'type' => 'string',
                'sortable' => true,
                'searchable' => true,
                'filterable' => false,
                'defaultVisible' => false,
                'width' => '150px',
                'alignment' => 'left',
                'description' => 'SCA name of person who submitted recommendation',
            ],

            'contact_email' => [
                'key' => 'contact_email',
                'label' => 'Contact Email',
                'type' => 'email',
                'sortable' => false,
                'searchable' => true,
                'filterable' => false,
                'defaultVisible' => false,
                'width' => '180px',
                'alignment' => 'left',
                'description' => 'Contact email for workflow communication',
            ],

            'contact_number' => [
                'key' => 'contact_number',
                'label' => 'Contact Phone',
                'type' => 'string',
                'sortable' => false,
                'searchable' => false,
                'filterable' => false,
                'defaultVisible' => false,
                'width' => '130px',
                'alignment' => 'left',
                'description' => 'Contact phone number',
            ],

            'domain_name' => [
                'key' => 'domain_name',
                'label' => 'Domain',
                'type' => 'relation',
                'sortable' => true,
                'searchable' => false,
                'filterable' => true,
                'filterType' => 'dropdown',
                'defaultVisible' => true,
                'width' => '120px',
                'alignment' => 'left',
                'renderField' => 'award.domain.name',
                'queryField' => 'Domains.name',
                'description' => 'Award domain (e.g., Arts & Sciences, Combat)',
            ],

            'award_name' => [
                'key' => 'award_name',
                'label' => 'Award',
                'type' => 'relation',
                'sortable' => true,
                'searchable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'defaultVisible' => true,
                'width' => '150px',
                'alignment' => 'left',
                'renderField' => 'award.abbreviation',
                'queryField' => 'Awards.abbreviation',
                'description' => 'Award being recommended',
            ],

            'specialty' => [
                'key' => 'specialty',
                'label' => 'Specialty',
                'type' => 'string',
                'sortable' => false,
                'searchable' => true,
                'filterable' => false,
                'defaultVisible' => false,
                'width' => '120px',
                'alignment' => 'left',
                'description' => 'Award specialty or focus area',
            ],

            'reason' => [
                'key' => 'reason',
                'label' => 'Reason',
                'type' => 'html',
                'sortable' => false,
                'searchable' => true,
                'filterable' => false,
                'defaultVisible' => true,
                'width' => '250px',
                'alignment' => 'left',
                'description' => 'Justification for the recommendation',
            ],

            'gatherings' => [
                'key' => 'gatherings',
                'label' => 'Gatherings',
                'type' => 'html',
                'sortable' => false,
                'searchable' => false,
                'filterable' => false,
                'defaultVisible' => true,
                'exportable' => false,
                'width' => '180px',
                'alignment' => 'left',
                'description' => 'Member attendance at related gatherings',
            ],

            'notes' => [
                'key' => 'notes',
                'label' => 'Notes',
                'type' => 'html',
                'sortable' => false,
                'searchable' => false,
                'filterable' => false,
                'defaultVisible' => true,
                'exportable' => false,
                'width' => '150px',
                'alignment' => 'left',
                'collapsible' => true,
                'description' => 'Administrative notes on the recommendation',
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
                'alignment' => 'left',
                'filterOptions' => [
                    ['value' => 'In Progress', 'label' => 'In Progress'],
                    ['value' => 'Scheduling', 'label' => 'Scheduling'],
                    ['value' => 'To Give', 'label' => 'To Give'],
                    ['value' => 'Closed', 'label' => 'Closed'],
                ],
                'description' => 'High-level workflow category',
            ],

            'state' => [
                'key' => 'state',
                'label' => 'State',
                'type' => 'string',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'dropdown',
                'defaultVisible' => true,
                'width' => '140px',
                'alignment' => 'left',
                'description' => 'Specific workflow position',
                // Filter options are generated dynamically based on permissions
            ],

            'close_reason' => [
                'key' => 'close_reason',
                'label' => 'Close Reason',
                'type' => 'string',
                'sortable' => false,
                'searchable' => false,
                'filterable' => false,
                'defaultVisible' => false,
                'width' => '150px',
                'alignment' => 'left',
                'description' => 'Reason for recommendation closure',
            ],

            'assigned_gathering' => [
                'key' => 'assigned_gathering',
                'label' => 'Gathering',
                'type' => 'relation',
                'sortable' => true,
                'searchable' => false,
                'filterable' => true,
                'filterType' => 'dropdown',
                'defaultVisible' => true,
                'width' => '160px',
                'alignment' => 'left',
                'renderField' => 'assigned_gathering.name',
                'queryField' => 'AssignedGathering.name',
                'description' => 'Gathering where award is scheduled to be given',
            ],

            'state_date' => [
                'key' => 'state_date',
                'label' => 'State Date',
                'type' => 'date',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date-range',
                'defaultVisible' => false,
                'width' => '110px',
                'alignment' => 'left',
                'description' => 'Date of last state transition',
            ],

            'given' => [
                'key' => 'given',
                'label' => 'Given Date',
                'type' => 'date',
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date-range',
                'defaultVisible' => false,
                'width' => '110px',
                'alignment' => 'left',
                'description' => 'Date award was presented',
            ],

            'award_branch_type' => [
                'key' => 'award_branch_type',
                'label' => 'Award Level',
                'type' => 'string',
                'sortable' => false,
                'searchable' => false,
                'filterable' => true,
                'filterType' => 'dropdown',
                'defaultVisible' => false,
                'width' => '120px',
                'alignment' => 'left',
                'filterOptions' => [
                    ['value' => 'Kingdom', 'label' => 'Kingdom'],
                    ['value' => 'Principality', 'label' => 'Principality'],
                    ['value' => 'Barony', 'label' => 'Barony'],
                ],
                'description' => 'Branch type of the awarding entity',
            ],
        ];
    }

    /**
     * Get system views for recommendations
     *
     * Defines the status-based tabs and their filter configurations.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getSystemViews(): array
    {
        return [
            'sys-recs-all' => [
                'id' => 'sys-recs-all',
                'name' => __('All'),
                'description' => __('All recommendations'),
                'canManage' => false,
                'config' => [
                    'filters' => [],
                    'visibleColumns' => [
                        'created',
                        'member_sca_name',
                        'op_links',
                        'branch_name',
                        'domain_name',
                        'award_name',
                        'reason',
                        'gatherings',
                        'notes',
                        'status',
                        'state',
                        'close_reason',
                        'assigned_gathering',
                    ],
                ],
            ],
            'sys-recs-in-progress' => [
                'id' => 'sys-recs-in-progress',
                'name' => __('In Progress'),
                'description' => __('Recommendations in progress'),
                'canManage' => false,
                'config' => [
                    'filters' => [
                        ['field' => 'status', 'operator' => 'eq', 'value' => 'In Progress'],
                    ],
                    'visibleColumns' => [
                        'created',
                        'member_sca_name',
                        'op_links',
                        'branch_name',
                        'domain_name',
                        'award_name',
                        'reason',
                        'notes',
                        'state',
                    ],
                ],
            ],
            'sys-recs-scheduling' => [
                'id' => 'sys-recs-scheduling',
                'name' => __('Scheduling'),
                'description' => __('Recommendations being scheduled'),
                'canManage' => false,
                'config' => [
                    'filters' => [
                        ['field' => 'status', 'operator' => 'eq', 'value' => 'Scheduling'],
                    ],
                    'visibleColumns' => [
                        'created',
                        'member_sca_name',
                        'branch_name',
                        'call_into_court',
                        'court_availability',
                        'person_to_notify',
                        'award_name',
                        'gatherings',
                        'notes',
                        'state',
                        'assigned_gathering',
                    ],
                ],
            ],
            'sys-recs-to-give' => [
                'id' => 'sys-recs-to-give',
                'name' => __('To Give'),
                'description' => __('Recommendations ready to give'),
                'canManage' => false,
                'config' => [
                    'filters' => [
                        ['field' => 'status', 'operator' => 'eq', 'value' => 'To Give'],
                    ],
                    'visibleColumns' => [
                        'created',
                        'member_sca_name',
                        'branch_name',
                        'call_into_court',
                        'court_availability',
                        'person_to_notify',
                        'award_name',
                        'reason',
                        'notes',
                        'state',
                        'assigned_gathering',
                    ],
                ],
            ],
            'sys-recs-closed' => [
                'id' => 'sys-recs-closed',
                'name' => __('Closed'),
                'description' => __('Closed recommendations'),
                'canManage' => false,
                'config' => [
                    'filters' => [
                        ['field' => 'status', 'operator' => 'eq', 'value' => 'Closed'],
                    ],
                    'visibleColumns' => [
                        'created',
                        'member_sca_name',
                        'branch_name',
                        'award_name',
                        'reason',
                        'notes',
                        'state',
                        'close_reason',
                        'assigned_gathering',
                        'state_date',
                        'given',
                    ],
                ],
            ],
        ];
    }

    /**
     * Get system views for "Submitted By Member" context
     *
     * Used when viewing recommendations submitted by a specific member.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getSubmittedByMemberViews(): array
    {
        return [
            'sys-recs-submitted-by' => [
                'id' => 'sys-recs-submitted-by',
                'name' => __('Submitted By Me'),
                'description' => __('Recommendations you have submitted'),
                'canManage' => false,
                'config' => [
                    'filters' => [],
                    'visibleColumns' => [
                        'created',
                        'member_sca_name',
                        'award_name',
                        'reason',
                        'gatherings',
                    ],
                ],
            ],
        ];
    }

    /**
     * Get system views for "Recs For Member" context
     *
     * Used when viewing recommendations for a specific member.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getRecsForMemberViews(): array
    {
        return [
            'sys-recs-for-member' => [
                'id' => 'sys-recs-for-member',
                'name' => __('Recommendations For Member'),
                'description' => __('Recommendations for this member'),
                'canManage' => false,
                'config' => [
                    'filters' => [],
                    'visibleColumns' => [
                        'created',
                        'member_sca_name',
                        'requester_sca_name',
                        'award_name',
                        'reason',
                        'gatherings',
                        'state',
                        'close_reason',
                        'assigned_gathering',
                        'given',
                    ],
                ],
            ],
        ];
    }

    /**
     * Get system views for "Gathering Awards" context
     *
     * Used when viewing recommendations scheduled for a specific gathering.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getGatheringAwardsViews(): array
    {
        return [
            'sys-recs-gathering' => [
                'id' => 'sys-recs-gathering',
                'name' => __('Gathering Awards'),
                'description' => __('Awards scheduled for this gathering'),
                'canManage' => false,
                'config' => [
                    'filters' => [],
                    'columns' => [
                        'member_sca_name',
                        'op_links',
                        'branch_name',
                        'domain_name',
                        'award_name',
                        'reason',
                        'status',
                        'state',
                    ],
                ],
            ],
        ];
    }

    /**
     * Get state filter options based on user permissions
     *
     * Returns state options that respect the canViewHidden permission.
     * States configured in Awards.RecommendationStatesRequireCanViewHidden
     * are excluded if user lacks the ViewHidden permission.
     *
     * @param bool $canViewHidden Whether the user can view hidden states
     * @return array<array<string, string>> Filter options for state dropdown
     */
    public static function getStateFilterOptions(bool $canViewHidden = false): array
    {
        $statuses = Recommendation::getStatuses();
        $hiddenStates = $canViewHidden ? [] : (StaticHelpers::getAppSetting(
            'Awards.RecommendationStatesRequireCanViewHidden'
        ) ?? []);

        $options = [];
        foreach ($statuses as $status => $states) {
            foreach ($states as $state) {
                if (!in_array($state, $hiddenStates)) {
                    $options[] = ['value' => $state, 'label' => $state];
                }
            }
        }

        return $options;
    }

    /**
     * Get hidden states that require ViewHidden permission
     *
     * @return array<string> List of state values that require special permission
     */
    public static function getHiddenStates(): array
    {
        return StaticHelpers::getAppSetting(
            'Awards.RecommendationStatesRequireCanViewHidden'
        ) ?? ['No Action'];
    }

    /**
     * Get exportable columns for CSV export
     *
     * Returns columns that should be included in CSV exports.
     * Excludes columns marked with exportable = false.
     *
     * @return array<string> Exportable column keys
     */
    public static function getExportableColumns(): array
    {
        $exportable = [];
        foreach (static::getColumns() as $key => $column) {
            if (($column['exportable'] ?? true) !== false) {
                $exportable[] = $key;
            }
        }
        return $exportable;
    }
}