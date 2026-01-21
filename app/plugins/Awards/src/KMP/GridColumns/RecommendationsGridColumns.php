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

            'branch_type' => [
                'key' => 'branch_type',
                'label' => 'Award Level',
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
                'description' => 'Branch type of the awarding entity (Kingdom, Principality, Barony)',
            ],
        ];
    }

    /**
     * Get system views for recommendations
     *
     * Defines the status-based tabs and their filter configurations.
     *
     * Supported contexts (via $options['context']):
     * - index (default): main recommendations grid
     * - memberSubmitted: member profile "Submitted Award Recs" tab
     * - recsForMember: member profile "Recs For Member" tab
     * - gatheringAwards: gathering detail "Awards" tab
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

        if ($context === 'gatheringAwards') {
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
                            'branch_id',
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

        return [
            'sys-recs-all' => [
                'id' => 'sys-recs-all',
                'name' => __('All'),
                'description' => __('All recommendations'),
                'canManage' => false,
                'config' => [
                    'filters' => [],
                    'columns' => [
                        'created',
                        'member_sca_name',
                        'op_links',
                        'branch_id',
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
                    'columns' => [
                        'created',
                        'member_sca_name',
                        'op_links',
                        'branch_id',
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
                    'columns' => [
                        'created',
                        'member_sca_name',
                        'branch_id',
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
                    'columns' => [
                        'created',
                        'member_sca_name',
                        'branch_id',
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
                    'columns' => [
                        'created',
                        'member_sca_name',
                        'branch_id',
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

    /**
     * Get gatherings filter options for recommendations grid.
     *
     * Returns a list of gatherings relevant to award recommendations:
     * - All future gatherings (starting from today)
     * - Past gatherings that have recommendations linked to them (via awards_recommendations_events)
     *   or have member attendance with share_with_crown or share_with_kingdom
     * - Limited to 6 months of back-looking for performance
     *
     * @return array<array<string, string>> Filter options for gatherings dropdown
     */
    public static function getGatheringsFilterOptions(): array
    {
        $gatheringsTable = \Cake\ORM\TableRegistry::getTableLocator()->get('Gatherings');
        $now = new \Cake\I18n\DateTime();
        $sixMonthsAgo = $now->modify('-6 months');

        // Query 1: Future gatherings (starting from today)
        $futureGatherings = $gatheringsTable->find()
            ->select(['id', 'name', 'start_date'])
            ->where(['start_date >=' => $now])
            ->orderBy(['start_date' => 'ASC'])
            ->limit(100)
            ->all()
            ->toArray();

        // Query 2: Past gatherings that have recommendations linked via awards_recommendations_events
        $pastGatheringsWithRecs = $gatheringsTable->find()
            ->select(['Gatherings.id', 'Gatherings.name', 'Gatherings.start_date'])
            ->innerJoin(
                ['RecEvents' => 'awards_recommendations_events'],
                ['RecEvents.gathering_id = Gatherings.id']
            )
            ->where([
                'Gatherings.start_date <' => $now,
                'Gatherings.start_date >=' => $sixMonthsAgo,
            ])
            ->orderBy(['Gatherings.start_date' => 'DESC'])
            ->group(['Gatherings.id'])
            ->limit(50)
            ->all()
            ->toArray();

        // Query 3: Past gatherings with relevant member attendance (share_with_crown or share_with_kingdom)
        $pastGatheringsWithAttendance = $gatheringsTable->find()
            ->select(['Gatherings.id', 'Gatherings.name', 'Gatherings.start_date'])
            ->innerJoin(
                ['Attendance' => 'gathering_attendances'],
                ['Attendance.gathering_id = Gatherings.id']
            )
            ->where([
                'Gatherings.start_date <' => $now,
                'Gatherings.start_date >=' => $sixMonthsAgo,
                'OR' => [
                    'Attendance.share_with_crown' => true,
                    'Attendance.share_with_kingdom' => true,
                ],
            ])
            ->orderBy(['Gatherings.start_date' => 'DESC'])
            ->group(['Gatherings.id'])
            ->limit(50)
            ->all()
            ->toArray();

        // Merge all gatherings, removing duplicates by ID
        $allGatherings = [];

        // Add future gatherings
        foreach ($futureGatherings as $gathering) {
            $allGatherings[$gathering->id] = $gathering;
        }

        // Add past gatherings with recommendations
        foreach ($pastGatheringsWithRecs as $gathering) {
            if (!isset($allGatherings[$gathering->id])) {
                $allGatherings[$gathering->id] = $gathering;
            }
        }

        // Add past gatherings with attendance
        foreach ($pastGatheringsWithAttendance as $gathering) {
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
            $label = $gathering->name;
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
     * @param string|array $filterValue One or more gathering IDs to filter by
     * @param array $context Additional context (tableName, columnKey, columnMeta)
     * @return \Cake\ORM\Query\SelectQuery The filtered query
     */
    public static function applyGatheringsFilter($query, $filterValue, array $context = [])
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

        $tableLocator = \Cake\ORM\TableRegistry::getTableLocator();
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
}
