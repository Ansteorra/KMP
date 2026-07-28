<?php
declare(strict_types=1);

namespace Activities\Controller;

use Cake\I18n\DateTime;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\TableRegistry;

/**
 * Activities Plugin Reports Controller
 *
 * Generates authorization reports with branch-scoped analytics, temporal filtering,
 * and activity-specific reporting for administrative oversight and compliance monitoring.
 *
 * @property \Activities\Model\Table\AuthorizationsTable $Authorizations
 * @package Activities\Controller
 */

class ReportsController extends AppController
{
    /**
     * Initialize controller with authorization settings.
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        // Future: Implement model-level authorization for standardized access control
        //$this->Authorization->authorizeModel('index','add','searchMembers','addPermission','deletePermission');
    }

    /**
     * Generate authorization report with member counts, activity rollups, and detailed listings.
     *
     * @return void
     */
    public function authorizations()
    {
        // Authorization validation - ensure user has permission to access reports
        $this->authorizeCurrentUrl();

        // Initialize variables for report generation
        $distincMemberCount = 0;

        // Load Activities table for activity selection and filtering
        $ActivitiesTbl = TableRegistry::getTableLocator()->get('Activities.Activities');
        $activitiesList = $ActivitiesTbl->find('list')->orderBy(['name' => 'ASC'])->toArray();

        // Default to all activities if none specified
        $default_activities = [];
        foreach ($activitiesList as $activityId => $activityName) {
            $default_activities[] = $activityId;
        }

        // Load Branches table for organizational hierarchy filtering
        $branchesTbl = TableRegistry::getTableLocator()->get('Branches');
        $branchesList = $branchesTbl->find('treeList', spacer: '-')->toArray();

        // Default validity date (tomorrow) for authorization checking
        $validOn = DateTime::now()->addDays(1);

        // Initialize result containers
        $memberRollup = [];
        $memberListQuery = [];
        $activities = [];

        // Process query parameters if provided
        if ($this->request->getQuery('validOn')) {
            // Extract filter parameters
            $activities = array_values(array_filter(
                array_map('intval', (array)$this->request->getQuery('activities', [])),
                static fn(int $activityId): bool => $activityId > 0,
            ));
            if ($activities === []) {
                $activities = $default_activities;
            }
            $filterBranch = (int)$this->request->getQuery('branches');

            // Calculate valid branches including children in hierarchy
            $validBranches = $branchesTbl->find('children', for: $filterBranch)->all()->extract('id')->toArray();
            $validBranches[] = $filterBranch; // Include parent branch

            // Parse validity date
            $validOn = (new DateTime($this->request->getQuery('validOn')))->addDays(1);

            // Load Authorizations table for data queries
            $authTbl = TableRegistry::getTableLocator()->get('Activities.Authorizations');

            // Calculate distinct member count with authorization filters
            $distinctMemberQuery = $authTbl->find()
                ->select('member_id')
                ->contain(['Members' => function ($q) use ($validBranches) {
                    return $q->select(['id'])->where(['branch_id IN' => $validBranches]);
                }])
                ->where([
                    'activity_id IN' => $activities,
                ]);
            $distincMemberCount = $this->setValidFilter($distinctMemberQuery, $validOn)
                ->distinct('member_id')
                ->count();

            // Generate detailed member listing with authorization details
            $memberListQuery = $authTbl->find('all')
                ->contain(['Activities' => function ($q) {
                    return $q->select(['name']);
                }, 'Members' => function ($q) use ($validBranches) {
                    return $q->select(['membership_number', 'sca_name', 'id', 'branch_id'])
                        ->where(['branch_id IN' => $validBranches]);
                }, 'Members.Branches' => function ($q) {
                    return $q->select(['name']);
                }])
                ->where([
                    'activity_id IN' => $activities,
                ]);
            $memberListQuery = $this->setValidFilter($memberListQuery, $validOn)
                ->orderBy(['Activities.name' => 'ASC', 'Members.sca_name' => 'ASC'])
                ->all();

            // Generate statistical rollup by activity type
            $authTypes = $authTbl->find('all')
                ->innerJoinWith('Activities')
                ->innerJoinWith('Members', function ($q) use ($validBranches) {
                    return $q->where(['Members.branch_id IN' => $validBranches]);
                });
            $memberRollup = $authTypes
                ->select([
                    'auth' => 'Activities.name',
                    'count' => $authTypes->func()->count('Authorizations.member_id'),
                ])
                ->where([
                    'Authorizations.activity_id IN' => $activities,
                ]);
            $memberRollup = $this->setValidFilter($memberRollup, $validOn)
                ->groupBy(['Activities.name'])
                ->all();
        }

        // Adjust validity date for display (subtract the added day)
        $validOn = $validOn->subDays(1);

        // Use default activities if none selected
        if (!$activities) {
            $activities = $default_activities;
        }

        // Set template variables for view rendering
        $this->set(compact(
            'activitiesList', // Available activities for filter selection
            'branchesList', // Branch hierarchy for organizational filtering
            'distincMemberCount', // Total unique authorized members
            'validOn', // Target date for report validity
            'memberRollup', // Statistical summary by activity type
            'memberListQuery', // Detailed member authorization listings
            'activities', // Selected activity IDs for filtering
        ));
    }

    /**
     * Apply temporal validity filter to authorization query.
     *
     * @param \Cake\ORM\Query\SelectQuery $query The base query object to filter
     * @param \Cake\I18n\DateTime $validOn The target date for validity checking
     * @return \Cake\ORM\Query\SelectQuery The modified query with temporal filters applied
     */
    protected function setValidFilter(SelectQuery $query, DateTime $validOn): SelectQuery
    {
        return $query->where([
            'OR' => [
                'Authorizations.start_on <=' => $validOn,
                'Authorizations.start_on IS' => null,
            ],
        ])->where([
            'OR' => [
                'Authorizations.expires_on >=' => $validOn,
                'Authorizations.expires_on IS' => null,
            ],
        ]);
    }
}
