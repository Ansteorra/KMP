<?php

declare(strict_types=1);

namespace Awards\KMP\GridColumns;

use App\KMP\GridColumns\BaseGridColumns;
use Awards\Model\Entity\Recommendation;
use Cake\I18n\DateTime;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\TableRegistry;

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
                'condition' => [
                    'bestowal_linked' => false,
                ],
                'dataAttributes' => [
                    'controller' => 'outlet-btn',
                    'action' => 'click->outlet-btn#fireNotice',
                    'outlet-btn-btn-data-value' => [
                        'id' => 'id',
                    ],
                ],
            ],
            'bestowal' => [
                'key' => 'bestowal',
                'type' => 'link',
                'label' => '',
                'icon' => 'bi-award-fill',
                'class' => 'btn-sm btn btn-outline-primary',
                'permission' => 'view',
                'title' => 'Open linked bestowal',
                'ariaLabel' => 'Open linked bestowal',
                'condition' => [
                    'bestowal_viewable' => true,
                ],
                'url' => [
                    'plugin' => 'Awards',
                    'controller' => 'Bestowals',
                    'action' => 'view',
                    'idField' => 'bestowal_id',
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
            'workflow-decision' => [
                'key' => 'workflow-decision',
                'type' => 'modal',
                'label' => '',
                'icon' => 'bi-check2-circle',
                'class' => 'btn-sm btn btn-success',
                'modalTarget' => '#recommendationWorkflowDecisionModal',
                'title' => 'Approve or decline recommendation workflow',
                'ariaLabel' => 'Approve or decline recommendation workflow',
                'condition' => [
                    'can_workflow_decide' => true,
                ],
                'dataAttributes' => [
                    'controller' => 'outlet-btn',
                    'action' => 'click->outlet-btn#fireNotice',
                    'outlet-btn-btn-data-value' => [
                        'id' => 'id',
                        'approvalId' => 'pending_approval_id',
                        'approverConfig' => 'pending_approval_approver_config',
                        'requiredCount' => 'pending_approval_required_count',
                        'approvedCount' => 'pending_approval_approved_count',
                    ],
                ],
            ],
            'request-feedback' => [
                'key' => 'request-feedback',
                'type' => 'modal',
                'label' => '',
                'icon' => 'bi-chat-left-text',
                'class' => 'btn-sm btn btn-outline-primary',
                'modalTarget' => '#requestRecommendationFeedbackModal',
                'permission' => 'requestFeedback',
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

            'group_children_count' => [
                'key' => 'group_children_count',
                'label' => '',
                'type' => 'number',
                'sortable' => false,
                'filterable' => false,
                'defaultVisible' => true,
                'width' => '80px',
                'alignment' => 'center',
                'description' => 'Total recommendations in this group (including the head)',
                'clickAction' => 'toggleSubRow:group-children',
                'clickActionUrl' => '/awards/recommendations/group-children/:id',
                'cellRenderer' => function ($value, $row, $view) {
                    $count = (int)($value ?? 0);
                    if ($count === 0) {
                        return '';
                    }

                    return '<span class="badge bg-info">' . $count . '</span>';
                },
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
                'clickActionPermission' => static function ($row, $identity): bool {
                    $memberId = is_array($row) ? ($row['member_id'] ?? null) : ($row->member_id ?? null);
                    $memberId = is_numeric($memberId) ? (int)$memberId : null;

                    return $memberId !== null
                        && $memberId > 0
                        && $identity !== null
                        && method_exists($identity, 'checkCan')
                        && $identity->checkCan('view', 'Members');
                },
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
                'defaultVisible' => true,
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
                'queryField' => 'Domains.id',
                'filterOptionsSource' => 'Awards.Domains',
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

            'level_name' => [
                'key' => 'level_name',
                'label' => 'Award Level',
                'type' => 'relation',
                'sortable' => false,
                'searchable' => false,
                'filterable' => true,
                'filterType' => 'dropdown',
                'filterOptionsSource' => 'Awards.Levels',
                'defaultVisible' => false,
                'width' => '140px',
                'alignment' => 'left',
                'renderField' => 'award.level.name',
                'queryField' => 'Levels.id',
                'description' => 'Award precedence level',
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
                'filterable' => true,
                'filterType' => 'dropdown',
                'filterOptionsSource' => [
                    'method' => 'getGatheringsFilterOptions',
                    'class' => 'Awards\\KMP\\GridColumns\\RecommendationsGridColumns',
                ],
                // Custom filter handler: this column requires complex filtering logic
                // that queries across multiple tables. The trait will automatically
                // call this handler instead of applying a simple WHERE clause.
                'customFilterHandler' => [
                    'method' => 'applyGatheringsFilter',
                    'class' => 'Awards\\KMP\\GridColumns\\RecommendationsGridColumns',
                ],
                'defaultVisible' => true,
                'exportable' => true,
                'width' => '180px',
                'alignment' => 'left',
                'description' => 'Gatherings linked to recommendation or member attendance (shared with crown/kingdom)',
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

            'approval_queue' => [
                'key' => 'approval_queue',
                'label' => 'Approval Workflow',
                'type' => 'relation',
                'sortable' => true,
                'searchable' => false,
                'filterable' => true,
                'filterType' => 'text',
                'defaultVisible' => true,
                'width' => '160px',
                'alignment' => 'left',
                'renderField' => 'current_approval_run.current_step_label',
                'queryField' => 'CurrentApprovalRun.current_step_label',
                'description' => 'Current workflow approval step, when this recommendation is under active approval',
            ],

            'approval_queue_present' => [
                'key' => 'approval_queue_present',
                'label' => 'Has Approval Workflow',
                'type' => 'number',
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'is-populated',
                'defaultVisible' => false,
                'exportable' => false,
                'queryField' => 'CurrentApprovalRun.id',
                'description' => 'Whether this recommendation has an active approval workflow',
            ],

            'bestowal_linked' => [
                'key' => 'bestowal_linked',
                'label' => 'Linked to Bestowal',
                'type' => 'number',
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'is-populated',
                'defaultVisible' => false,
                'exportable' => false,
                'queryField' => 'Recommendations.bestowal_id',
                'description' => 'Whether this recommendation has already been converted to a bestowal',
            ],
            'bestowal_given' => [
                'key' => 'bestowal_given',
                'label' => 'Bestowal Given',
                'type' => 'string',
                'sortable' => false,
                'filterable' => false,
                'defaultVisible' => false,
                'exportable' => false,
                'queryField' => 'Bestowals.lifecycle_status',
                'description' => 'Whether the linked bestowal has already been given',
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
                'cellRenderer' => [self::class, 'renderAssignedGathering'],
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

            'branch_type' => [
                'key' => 'branch_type',
                'label' => 'Award Scope',
                'type' => 'relation',
                'sortable' => false,
                'searchable' => false,
                'filterable' => true,
                'filterType' => 'dropdown',
                'defaultVisible' => false,
                'width' => '120px',
                'alignment' => 'left',
                // Uses AwardBranch alias to avoid conflicts with member's Branches
                'renderField' => 'award.award_branch.type',
                // queryField tells the filter pipeline to use AwardBranch.type instead of Recommendations.branch_type
                // Filtering uses innerJoinWith('Awards.AwardBranch') added in controller
                'queryField' => 'AwardBranch.type',
                'filterOptions' => [
                    ['value' => 'Kingdom', 'label' => 'Kingdom'],
                    ['value' => 'Principality', 'label' => 'Principality'],
                    ['value' => 'Local Group', 'label' => 'Local Group'],
                ],
                'description' => 'Scope of the awarding entity (Kingdom, Principality, Local Group)',
            ],
        ];
    }

    /**
     * Get system views for recommendations
     *
     * Defines workflow-centric system views and their filter configurations.
     *
     * Supported contexts (via $options['context']):
     * - index (default): main recommendations grid
     * - memberSubmitted: member profile "Submitted Award Recs" tab
     * - recsForMember: member profile "Recs For Member" tab
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getSystemViews(array $options = []): array
    {
        $context = $options['context'] ?? 'index';

        if ($context === 'memberSubmitted') {
            return [
                'sys-recs-submitted-by' => [
                    'id' => 'sys-recs-submitted-by',
                    'name' => __('Submitted By Me'),
                    'description' => __('Recommendations you have submitted'),
                    'canManage' => false,
                    'config' => [
                        'filters' => [],
                        'columns' => [
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

        if ($context === 'recsForMember') {
            return [
                'sys-recs-for-member' => [
                    'id' => 'sys-recs-for-member',
                    'name' => __('Recommendations For Member'),
                    'description' => __('Recommendations for this member'),
                    'canManage' => false,
                    'config' => [
                        'filters' => [],
                        'columns' => [
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

        $coreColumns = [
            'group_children_count',
            'created',
            'member_sca_name',
            'op_links',
            'branch_id',
            'domain_name',
            'award_name',
            'reason',
            'gatherings',
            'notes',
        ];

        return [
            'sys-recs-in-approval' => [
                'id' => 'sys-recs-in-approval',
                'name' => __('Pending Review'),
                'description' => __('Recommendations currently routed through an approval workflow'),
                'canManage' => false,
                'config' => [
                    'filters' => [],
                    'columns' => array_merge($coreColumns, [
                        'approval_queue',
                    ]),
                ],
            ],
            'sys-recs-needs-my-approval' => [
                'id' => 'sys-recs-needs-my-approval',
                'name' => __('My Queue'),
                'description' => __('Recommendations currently waiting for your approval decision'),
                'canManage' => false,
                'config' => [
                    'filters' => [],
                    'columns' => array_merge($coreColumns, [
                        'approval_queue',
                    ]),
                ],
            ],
            'sys-recs-approved-by-me' => [
                'id' => 'sys-recs-approved-by-me',
                'name' => __('Approved by Me'),
                'description' => __('Recommendations in active approval workflows that you have approved'),
                'canManage' => false,
                'config' => [
                    'filters' => [],
                    'columns' => array_merge($coreColumns, [
                        'approval_queue',
                    ]),
                ],
            ],
            'sys-recs-converted' => [
                'id' => 'sys-recs-converted',
                'name' => __('Converted to Bestowals'),
                'description' => __('Recommendations now managed through a bestowal workflow'),
                'canManage' => false,
                'config' => [
                    'filters' => [],
                    'columns' => [
                        'member_sca_name',
                        'branch_id',
                        'domain_name',
                        'award_name',
                        'gatherings',
                        'notes',
                    ],
                    'skipFilterColumns' => ['bestowal_linked'],
                ],
            ],
            'sys-recs-archived' => [
                'id' => 'sys-recs-archived',
                'name' => __('Archived'),
                'description' => __(
                    'Closed recommendation records and recommendations whose linked bestowals were given',
                ),
                'canManage' => false,
                'config' => [
                    'filters' => [],
                    'columns' => array_diff(array_merge($coreColumns, [
                        'status',
                        'state',
                        'close_reason',
                        'given',
                    ]), ['reason', 'gatherings', 'notes']), // Remove reason and gatherings from archived view to reduce clutter
                    'skipFilterColumns' => ['state'],
                ],
            ],
            'sys-recs-all' => [
                'id' => 'sys-recs-all',
                'name' => __('All / Audit'),
                'description' => __('All recommendations, including archival state and status data'),
                'canManage' => false,
                'config' => [
                    'filters' => [],
                    'columns' => array_merge($coreColumns, [
                        'status',
                        'state',
                        'approval_queue',
                        'close_reason',
                        'assigned_gathering',
                    ]),
                ],
            ],
        ];
    }

    /**
     * Return recommendation states considered archived for system-view filtering.
     *
     * @return array<int, string>
     */
    public static function getArchivedStates(): array
    {
        return self::statesForStatuses(Recommendation::getStatuses(), ['Closed']);
    }

    /**
     * @param array<string, array<int, string>> $statuses
     * @param array<int, string> $statusNames
     * @return array<int, string>
     */
    private static function statesForStatuses(array $statuses, array $statusNames): array
    {
        $states = [];
        foreach ($statusNames as $statusName) {
            foreach ($statuses[$statusName] ?? [] as $state) {
                $states[] = $state;
            }
        }

        return array_values(array_unique($states));
    }

    /**
     * @param array<string, array<int, string>> $statuses
     * @param array<int, string> $preferredStates
     * @param array<int, string> $fallbackStatuses
     * @return array<int, string>
     */
    private static function configuredStates(array $statuses, array $preferredStates, array $fallbackStatuses): array
    {
        $configuredStates = [];
        foreach ($statuses as $states) {
            foreach ($states as $state) {
                $configuredStates[$state] = true;
            }
        }

        $states = [];
        foreach ($preferredStates as $state) {
            if (isset($configuredStates[$state])) {
                $states[] = $state;
            }
        }

        if ($states !== []) {
            return $states;
        }

        return self::statesForStatuses($statuses, $fallbackStatuses);
    }

    /**
     * Get state filter options based on user permissions
     *
     * Returns state options that respect the canViewHidden permission.
     * States marked as is_hidden in the database are excluded if user
     * lacks the ViewHidden permission.
     *
     * @param bool $canViewHidden Whether the user can view hidden states
     * @return array<array<string, string>> Filter options for state dropdown
     */
    public static function getStateFilterOptions(bool $canViewHidden = false): array
    {
        $statuses = Recommendation::getStatuses();
        $hiddenStates = $canViewHidden ? [] : Recommendation::getHiddenStates();

        $options = [];
        foreach ($statuses as $states) {
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
        return Recommendation::getHiddenStates();
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

    /**
     * Get gatherings filter options for recommendations grid.
     *
     * Returns a list of gatherings relevant to award recommendations:
     * - All future gatherings that include at least one award-capable activity
     *   (gatherings_gathering_activities -> award_gathering_activities)
     * - All past gatherings that have scheduled or given awards
     *
     * @return array<array<string, string>> Filter options for gatherings dropdown
     */
    public static function getGatheringsFilterOptions(): array
    {
        $gatheringsTable = TableRegistry::getTableLocator()->get('Gatherings');
        $now = new DateTime();

        // Query 1: Future gatherings with at least one award-capable activity.
        $futureGatherings = $gatheringsTable->find()
            ->select(['Gatherings.id', 'Gatherings.name', 'Gatherings.start_date', 'Gatherings.cancelled_at'])
            ->innerJoin(
                ['GatheringsGatheringActivities' => 'gatherings_gathering_activities'],
                ['GatheringsGatheringActivities.gathering_id = Gatherings.id'],
            )
            ->innerJoin(
                ['AwardGatheringActivities' => 'award_gathering_activities'],
                [
                    'AwardGatheringActivities.gathering_activity_id = '
                        . 'GatheringsGatheringActivities.gathering_activity_id',
                ],
            )
            ->where(['Gatherings.start_date >=' => $now])
            ->orderBy(['Gatherings.start_date' => 'ASC'])
            ->groupBy(['Gatherings.id'])
            ->limit(100)
            ->all()
            ->toArray();

        // Query 2a: Past gatherings with scheduled/given awards assigned by gathering_id.
        $pastGatheringsWithRecAssigned = $gatheringsTable->find()
            ->select(['Gatherings.id', 'Gatherings.name', 'Gatherings.start_date', 'Gatherings.cancelled_at'])
            ->innerJoin(
                ['Recommendations' => 'awards_recommendations'],
                ['Recommendations.gathering_id = Gatherings.id'],
            )
            ->where([
                'Gatherings.start_date <' => $now,
                'OR' => [
                    'Recommendations.status IN' => ['Scheduling', 'To Give'],
                    'Recommendations.given IS NOT' => null,
                    'Recommendations.state' => 'Given',
                ],
            ])
            ->orderBy(['Gatherings.start_date' => 'DESC'])
            ->groupBy(['Gatherings.id'])
            ->all()
            ->toArray();

        // Query 2b: Past gatherings linked through recommendation events with scheduled/given awards.
        $pastGatheringsWithRecEvents = $gatheringsTable->find()
            ->select(['Gatherings.id', 'Gatherings.name', 'Gatherings.start_date', 'Gatherings.cancelled_at'])
            ->innerJoin(
                ['RecommendationEvents' => 'awards_recommendations_events'],
                ['RecommendationEvents.event_id = Gatherings.id'],
            )
            ->innerJoin(
                ['Recommendations' => 'awards_recommendations'],
                ['Recommendations.id = RecommendationEvents.recommendation_id'],
            )
            ->where([
                'Gatherings.start_date <' => $now,
                'OR' => [
                    'Recommendations.status IN' => ['Scheduling', 'To Give'],
                    'Recommendations.given IS NOT' => null,
                    'Recommendations.state' => 'Given',
                ],
            ])
            ->orderBy(['Gatherings.start_date' => 'DESC'])
            ->groupBy(['Gatherings.id'])
            ->all()
            ->toArray();

        // Merge all gatherings, removing duplicates by ID
        $allGatherings = [];

        // Add future gatherings
        foreach ($futureGatherings as $gathering) {
            $allGatherings[$gathering->id] = $gathering;
        }

        // Add past gatherings with scheduled/given awards assigned by gathering_id.
        foreach ($pastGatheringsWithRecAssigned as $gathering) {
            if (!isset($allGatherings[$gathering->id])) {
                $allGatherings[$gathering->id] = $gathering;
            }
        }

        // Add past gatherings linked through recommendation events.
        foreach ($pastGatheringsWithRecEvents as $gathering) {
            if (!isset($allGatherings[$gathering->id])) {
                $allGatherings[$gathering->id] = $gathering;
            }
        }

        // Sort by start_date (future first ascending, then past descending)
        usort($allGatherings, function ($a, $b) use ($now) {
            $aIsFuture = $a->start_date >= $now;
            $bIsFuture = $b->start_date >= $now;

            // Future gatherings come first
            if ($aIsFuture && !$bIsFuture) {
                return -1;
            }
            if (!$aIsFuture && $bIsFuture) {
                return 1;
            }

            // Within same group, sort by date
            if ($aIsFuture) {
                // Future: ascending (soonest first)
                return $a->start_date <=> $b->start_date;
            } else {
                // Past: descending (most recent first)
                return $b->start_date <=> $a->start_date;
            }
        });

        // Build the options array
        $options = [];
        foreach ($allGatherings as $gathering) {
            $dateStr = $gathering->start_date ? $gathering->start_date->toDateString() : '';
            $isCancelled = $gathering->cancelled_at !== null;
            $label = $isCancelled ? '[CANCELLED] ' : '';
            $label .= $gathering->name;
            if ($dateStr) {
                $label .= ' (' . $dateStr . ')';
            }

            $options[] = [
                'value' => (string)$gathering->id,
                'label' => $label,
            ];
        }

        return $options;
    }

    /**
     * Apply gatherings filter to recommendations query.
     *
     * Custom filter handler that filters recommendations to include those where:
     * - The gathering is linked via awards_recommendations_events (recommendation events)
     * - OR the recommendation's member is attending the gathering with share_with_crown or share_with_kingdom
     *
     * This method is called automatically by DataverseGridTrait when a gatherings
     * filter is applied (either from query params or saved view config).
     *
     * @param \Cake\ORM\Query\SelectQuery $query The query to filter
     * @param array|string $filterValue One or more gathering IDs to filter by
     * @param array $context Additional context (tableName, columnKey, columnMeta)
     * @return \Cake\ORM\Query\SelectQuery The filtered query
     */
    public static function applyGatheringsFilter($query, $filterValue, array $context = []): SelectQuery
    {
        // Normalize to array
        if (!is_array($filterValue)) {
            $gatheringIds = [$filterValue];
        } else {
            $gatheringIds = $filterValue;
        }

        // Filter out empty values and ensure integers
        $gatheringIds = array_filter(array_map('intval', $gatheringIds));

        if (empty($gatheringIds)) {
            return $query;
        }

        $tableLocator = TableRegistry::getTableLocator();
        $recommendationsTable = $tableLocator->get('Awards.Recommendations');
        $attendanceTable = $tableLocator->get('GatheringAttendances');

        // Find recommendation IDs that match either:
        // 1. Have the gathering linked in awards_recommendations_events
        // 2. Have a member who is attending the gathering with share_with_crown or share_with_kingdom

        // Subquery 1: Recommendations linked via awards_recommendations_events join table
        $linkedRecIds = $recommendationsTable->find()
            ->select(['Recommendations.id'])
            ->matching('Gatherings', function ($q) use ($gatheringIds) {
                return $q->where(['Gatherings.id IN' => $gatheringIds]);
            })
            ->distinct()
            ->all()
            ->extract('id')
            ->toArray();

        // Subquery 2: Members attending these gatherings with share_with_crown or share_with_kingdom
        $attendingMemberIds = $attendanceTable->find()
            ->select(['member_id'])
            ->where([
                'gathering_id IN' => $gatheringIds,
                'OR' => [
                    'share_with_crown' => true,
                    'share_with_kingdom' => true,
                ],
            ])
            ->distinct()
            ->all()
            ->extract('member_id')
            ->toArray();

        // Build the OR condition: rec_id in linked recs OR member_id in attending members
        $conditions = ['OR' => ['Recommendations.gathering_id IN' => $gatheringIds]];

        if (!empty($linkedRecIds)) {
            $conditions['OR']['Recommendations.id IN'] = $linkedRecIds;
        }

        if (!empty($attendingMemberIds)) {
            $conditions['OR']['Recommendations.member_id IN'] = $attendingMemberIds;
        }

        // If neither condition has results, return query that matches nothing
        if (empty($conditions['OR'])) {
            $query->where(['Recommendations.id' => -1]);
        } else {
            $query->where($conditions);
        }

        return $query;
    }

    /**
     * Custom cell renderer for assigned gathering display.
     *
     * Shows [CANCELLED] prefix for cancelled gatherings with visual warning styling.
     *
     * @param mixed $value The cell value (gathering name)
     * @param object|array $row The full row data
     * @param \App\View\AppView $view The view instance
     * @return string Rendered HTML
     */
    public static function renderAssignedGathering($value, $row, $view): string
    {
        if (empty($row->assigned_gathering)) {
            return '';
        }

        $name = htmlspecialchars($row->assigned_gathering->name ?? '');
        $isCancelled = !empty($row->assigned_gathering->cancelled_at);

        if ($isCancelled) {
            return '<span class="text-danger fw-bold">[CANCELLED]</span> ' . $name;
        }

        return $name;
    }
}
