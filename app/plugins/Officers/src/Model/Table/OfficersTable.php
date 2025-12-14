<?php

declare(strict_types=1);

namespace Officers\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use App\KMP\StaticHelpers;
use App\Model\Entity\Warrant;
use Cake\I18n\DateTime;
use Officers\Model\Entity\Officer;
use App\Model\Table\BaseTable;

/**
 * Officers Table - Officer assignment lifecycle management
 *
 * Manages officer assignments with ActiveWindow temporal behavior, warrant integration,
 * and hierarchical reporting relationships. Performs daily status checks for automatic
 * UPCOMING → CURRENT → EXPIRED transitions.
 *
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $Members
 * @property \App\Model\Table\BranchesTable&\Cake\ORM\Association\BelongsTo $Branches
 * @property \Officers\Model\Table\OfficesTable&\Cake\ORM\Association\BelongsTo $Offices
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $ApprovedBy
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $RevokedBy
 * @property \Officers\Model\Table\OfficesTable&\Cake\ORM\Association\BelongsTo $ReportsToOffices
 * @property \App\Model\Table\BranchesTable&\Cake\ORM\Association\BelongsTo $ReportsToBranches
 * @property \Officers\Model\Table\OfficesTable&\Cake\ORM\Association\BelongsTo $DeputyToOffices
 * @property \App\Model\Table\BranchesTable&\Cake\ORM\Association\BelongsTo $DeputyToBranches
 * @property \App\Model\Table\WarrantsTable&\Cake\ORM\Association\HasOne $CurrentWarrants
 * @property \App\Model\Table\WarrantsTable&\Cake\ORM\Association\HasMany $PendingWarrants
 * @property \App\Model\Table\WarrantsTable&\Cake\ORM\Association\HasMany $Warrants
 * @property \Officers\Model\Table\OfficersTable&\Cake\ORM\Association\HasMany $ReportsToCurrently
 * @property \Officers\Model\Table\OfficersTable&\Cake\ORM\Association\HasMany $DeputyToCurrently
 *
 * @method \Officers\Model\Entity\Officer newEmptyEntity()
 * @method \Officers\Model\Entity\Officer newEntity(array $data, array $options = [])
 * @method \Officers\Model\Entity\Officer get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \Officers\Model\Entity\Officer findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \Officers\Model\Entity\Officer patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \Officers\Model\Entity\Officer|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \Officers\Model\Entity\Officer saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 *
 * @see /docs/5.1-officers-plugin.md
 */
class OfficersTable extends BaseTable
{
    /**
     * Initialize method
     *
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('officers_officers');
        $this->setDisplayField('status');
        $this->setPrimaryKey('id');

        $this->belongsTo('Members', [
            'foreignKey' => 'member_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('Branches', [
            'foreignKey' => 'branch_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('Offices', [
            'className' => 'Officers.Offices',
            'foreignKey' => 'office_id',
            'joinType' => 'INNER',
        ]);

        $this->belongsTo("ApprovedBy", [
            "className" => "Members",
            "foreignKey" => "approver_id",
            "joinType" => "INNER",
            "propertyName" => "approved_by",
        ]);
        $this->belongsTo("RevokedBy", [
            "className" => "Members",
            "foreignKey" => "revoker_id",
            "joinType" => "LEFT",
            "propertyName" => "revoked_by",
        ]);
        $this->belongsTo('ReportsToOffices', [
            'className' => 'Officers.Offices',
            'foreignKey' => 'reports_to_office_id',
            'joinType' => 'LEFT',
        ]);
        $this->belongsTo('ReportsToBranches', [
            'className' => 'Branches',
            'foreignKey' => 'reports_to_branch_id',
            'joinType' => 'LEFT',
        ]);
        $this->belongsTo('DeputyToOffices', [
            'className' => 'Officers.Offices',
            'foreignKey' => 'deputy_to_office_id',
            'joinType' => 'LEFT',
        ]);
        $this->belongsTo('DeputyToBranches', [
            'className' => 'Branches',
            'foreignKey' => 'deputy_to_branch_id',
            'joinType' => 'LEFT',
        ]);
        $now = DateTime::now();
        $this->hasOne("CurrentWarrants", [
            "className" => "Warrants",
            "foreignKey" => "entity_id",
            "conditions" => [
                "CurrentWarrants.entity_type" => "Officers.Officers",
                "CurrentWarrants.status" => Warrant::CURRENT_STATUS,
                "CurrentWarrants.start_on <=" => $now,
                "CurrentWarrants.expires_on >=" => $now
            ],
        ]);
        $this->hasMany("PendingWarrants", [
            "className" => "Warrants",
            "foreignKey" => "entity_id",
            "conditions" => [
                "PendingWarrants.entity_type" => "Officers.Officers",
                "PendingWarrants.status" => Warrant::PENDING_STATUS
            ],
        ]);


        $this->hasMany("Warrants", [
            "className" => "Warrants",
            "foreignKey" => "entity_id",
            "conditions" => [
                "Warrants.entity_type" => "Officers.Officers",
            ],
        ]);

        $this->hasMany('ReportsToCurrently', [
            'className' => 'Officers.Officers',
            "foreignKey" => ["office_id", 'branch_id'],
            "bindingKey" => ["reports_to_office_id", 'reports_to_branch_id'],
            'joinType' => 'LEFT',
            'conditions' => [
                'ReportsToCurrently.start_on <=' => $now,
                'ReportsToCurrently.expires_on >=' => $now,
                'ReportsToCurrently.status' => Officer::CURRENT_STATUS
            ]
        ]);
        $this->hasMany('DeputyToCurrently', [
            'className' => 'Officers.Officers',
            "foreignKey" => ["office_id", 'branch_id'],
            "bindingKey" => ["deputy_to_office_id", 'deputy_to_branch_id'],
            'joinType' => 'LEFT',
            'conditions' => [
                'DeputyToCurrently.start_on <=' => $now,
                'DeputyToCurrently.expires_on >=' => $now,
                'DeputyToCurrently.status' => Officer::CURRENT_STATUS
            ]
        ]);

        $this->addBehavior("Timestamp");
        $this->addBehavior('Muffin/Footprint.Footprint');
        $this->addBehavior("ActiveWindow");

        $lastOfficerExpCheck = new DateTime(StaticHelpers::getAppSetting("Officer.NextStatusCheck", DateTime::now()->subDays(1)->toDateString()), null, true);
        if ($lastOfficerExpCheck->isPast()) {
            $this->checkOfficerStatus();
            StaticHelpers::setAppSetting("Officer.NextStatusCheck", DateTime::now()->addDays(1)->toDateString(), null, true);
        }
    }

    protected function checkOfficerStatus(): void
    {
        $this->updateAll(
            ["status" => Officer::EXPIRED_STATUS],
            ["expires_on <=" => DateTime::now(), 'status IN' => [Officer::CURRENT_STATUS, Officer::UPCOMING_STATUS]]
        );
        $this->updateAll(
            ["status" => Officer::CURRENT_STATUS],
            ["start_on <=" => DateTime::now(), 'status ' => Officer::UPCOMING_STATUS]
        );
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('member_id')
            ->notEmptyString('member_id');

        $validator
            ->integer('branch_id')
            ->notEmptyString('branch_id');

        $validator
            ->integer('office_id')
            ->notEmptyString('office_id');

        $validator
            ->integer('granted_member_role_id')
            ->allowEmptyString('granted_member_role_id');

        $validator
            ->date('expires_on')
            ->allowEmptyDate('expires_on');

        $validator
            ->date('start_on')
            ->allowEmptyDate('start_on');

        $validator
            ->scalar('status')
            ->maxLength('status', 20)
            ->notEmptyString('status');

        $validator
            ->scalar('revoked_reason')
            ->maxLength('revoked_reason', 255)
            ->allowEmptyString('revoked_reason');

        $validator
            ->integer('revoker_id')
            ->allowEmptyString('revoker_id');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['member_id'], 'Members'), ['errorField' => 'member_id']);
        $rules->add($rules->existsIn(['branch_id'], 'Branches'), ['errorField' => 'branch_id']);
        $rules->add($rules->existsIn(['office_id'], 'Offices'), ['errorField' => 'office_id']);

        return $rules;
    }
    /**
     * Augments a SelectQuery with display-oriented fields, conditional expressions, containment, and ordering based on officer type.
     *
     * Adds computed display expressions for revoke reason and reporting descriptions, selects common officer fields, and configures related containment (Members, Offices, RevokedBy, warrants, reporting/deputy relations) appropriate for 'current', 'upcoming', or 'previous' officer listings.
     *
     * @param \Cake\ORM\Query\SelectQuery $q The query to modify.
     * @param string $type One of 'current', 'upcoming', or 'previous' to control which fields and associations are included.
     * @return \Cake\ORM\Query\SelectQuery The modified query with added selects, containments, and ordering suitable for display.
     */
    public function addDisplayConditionsAndFields($q, $type)
    {


        $rejectFragment = $q->func()->concat([
            'Released by ',
            "RevokedBy.sca_name" => 'identifier',
            " on ",
            "Officers.expires_on" => 'identifier',
            " note: ",
            "Officers.revoked_reason" => 'identifier'
        ]);

        $revokeReasonCase = $q->newExpr()
            ->case()
            ->when(['Officers.status' => Officer::RELEASED_STATUS])
            ->then($rejectFragment)
            ->when(['Officers.status' => Officer::REPLACED_STATUS])
            ->then("New Officer Took Over.")
            ->when(['Officers.status' => Officer::EXPIRED_STATUS])
            ->then("Officer Term Expired.")
            ->else($rejectFragment);


        $reportsToCase = $q->newExpr()
            ->case()
            ->when(['ReportsToOffices.id IS NULL'])
            ->then("Society")
            ->when(['current_report_to.id IS NOT NULL'])
            ->then($q->func()->concat([
                "ReportsToOffices.name" => 'identifier',
                " : ",
                "current_report_to.sca_name" => 'identifier',
            ]))
            ->when(['ReportsToOffices.id IS NOT NULL'])
            ->then($q->func()->concat([
                "Not Filed - ",
                "ReportsToBranches.name" => 'identifier',
                " : ",
                "ReportsToOffices.name" => 'identifier'
            ]))
            ->else("None");

        $fields = [
            "id",
            "member_id",
            "office_id",
            "branch_id",
            "Officers.start_on",
            "Officers.expires_on",
            "Officers.deputy_description",
            "Officers.email_address",
            "status",
            'reports_to_office_id',
            'reports_to_branch_id',
            'deputy_to_office_id',
            'deputy_to_branch_id',
            'Branches.name',
        ];

        $contain = [
            "Members" => function ($q) {
                return $q
                    ->select(["id", "sca_name"])
                    ->order(["sca_name" => "ASC"]);
            },
            "Offices" => function ($q) {
                return $q
                    ->select(["id", "name", "requires_warrant", "deputy_to_id", "reports_to_id"]);
            },

            "RevokedBy" => function ($q) {
                return $q
                    ->select(["id", "sca_name"]);
            },
        ];


        if ($type === 'current' || $type === 'upcoming') {
            //$fields['reports_to'] = $reportsToCase;
            $fields[] = "ReportsToBranches.name";
            $fields[] = "ReportsToOffices.name";
            $contain["ReportsToBranches"] = function ($q) {
                return $q
                    ->select(["id", "name"]);
            };
            $contain["ReportsToOffices"] = function ($q) {
                return $q
                    ->select(["id", "name"]);
            };
            $contain["DeputyToOffices"] = function ($q) {
                return $q
                    ->select(["id", "name"]);
            };
            $contain["CurrentWarrants"] = function ($q) {
                return $q
                    ->select(["id", "start_on", "expires_on"]);
            };
            $contain["PendingWarrants"] = function ($q) {
                return $q
                    ->select(["id", "start_on", "expires_on", "entity_id"]);
            };
            $contain["ReportsToCurrently"] = function ($q) {
                return $q
                    ->contain([
                        "Members" => function ($q) {
                            return $q
                                ->select(["id", "sca_name"]);
                        },
                        "Offices" => function ($q) {
                            return $q
                                ->select(["id", "name"]);
                        }
                    ])
                    ->select(["id", "office_id", "branch_id", "Members.sca_name", "Offices.name", "email_address"])
                    ->order(["sca_name" => "ASC"]);
            };
            $contain["DeputyToCurrently"] = function ($q) {
                return $q
                    ->contain([
                        "Members" => function ($q) {
                            return $q
                                ->select(["id", "sca_name"]);
                        },
                        "Offices" => function ($q) {
                            return $q
                                ->select(["id", "name"]);
                        }
                    ])
                    ->select(["id", "office_id", "branch_id", "Members.sca_name", "Offices.name", "email_address"])
                    ->order(["sca_name" => "ASC"]);
            };
        }

        if ($type === 'previous') {
            $fields['revoked_reason'] = $revokeReasonCase;
        }

        $query = $q
            ->select($fields);

        $query->contain($contain);
        $query->orderBy(["Officers.start_on" => "DESC", "Offices.name" => "ASC"]);

        return $query;
    }

    /**
     * Resolve the effective officers who should receive reports for a given officer by traversing the reporting office/branch hierarchy when offices may be vacant or configured to skip reporting.
     *
     * @param \Officers\Model\Entity\Officer $officer The officer assignment whose effective report recipients should be resolved; its `reports_to_office_id` and `reports_to_branch_id` determine the starting point.
     * @param string[] $visitedOffices Internal set of visited "office_branch" keys used to prevent circular traversal (for internal use; callers should not populate this).
     * @return \Officers\Model\Entity\Officer[] Array of officer entities that effectively receive reports for the given officer; empty array when none are found or when the resolution reaches the top-level (society). 
     */
    public function findEffectiveReportsTo(Officer $officer, array $visitedOffices = []): array
    {
        // Base case 1: No reporting office means this is top-level (Society)
        if (empty($officer->reports_to_office_id) || empty($officer->reports_to_branch_id)) {
            return [];
        }

        // Base case 2: Prevent circular references
        $officeKey = $officer->reports_to_office_id . '_' . $officer->reports_to_branch_id;
        if (in_array($officeKey, $visitedOffices)) {
            return [];
        }
        $visitedOffices[] = $officeKey;

        // Step 1: Look for current officers with EXACT match (office_id + branch_id)
        $now = DateTime::now();

        $reportingOfficers = $this->find()
            ->where([
                'Officers.office_id' => $officer->reports_to_office_id,
                'Officers.branch_id' => $officer->reports_to_branch_id,
                'Officers.status' => Officer::CURRENT_STATUS,
                'Officers.start_on <=' => $now,
                'Officers.expires_on >=' => $now,
            ])
            ->contain([
                'Members' => function ($q) {
                    return $q->select(['id', 'sca_name']);
                },
                'Offices' => function ($q) {
                    return $q->select(['id', 'name', 'can_skip_report', 'reports_to_id']);
                },
            ])
            ->all()
            ->toArray();

        // Base case 3: Found officers with exact match
        if (!empty($reportingOfficers)) {
            return $reportingOfficers;
        }

        // Step 2: No officers found in the reporting office
        // When an office is VACANT (no current officers), we traverse up the hierarchy
        // For vacant offices, we always look for the next level up

        // Load the reporting office to check hierarchy
        $officesTable = TableRegistry::getTableLocator()->get('Officers.Offices');
        $reportingOffice = $officesTable->get($officer->reports_to_office_id);

        // Step 3: Office is vacant - traverse up the hierarchy
        // We need to go up BOTH office hierarchy (reports_to_id) AND branch hierarchy (parent_id)

        // Check if there's a higher office level
        if (empty($reportingOffice->reports_to_id)) {
            // No higher office level - this is the top (Society level)
            return [];
        }

        // Get the parent branch for traversing branch hierarchy
        $branchesTable = TableRegistry::getTableLocator()->get('Branches');
        $currentBranch = $branchesTable->get($officer->reports_to_branch_id);

        // Get the parent branch ID (move up in branch hierarchy)
        $parentBranchId = $currentBranch->parent_id;

        if (empty($parentBranchId)) {
            // No parent branch - we've reached the top of the branch hierarchy
            return [];
        }

        // Create a temporary officer-like object for recursion
        // This represents an officer IN the next office/branch that we want to search
        // The reports_to_* fields tell us WHERE TO LOOK (which is this office/branch)
        $tempOfficer = $this->newEmptyEntity();
        $tempOfficer->reports_to_office_id = $reportingOffice->reports_to_id;  // LOOK IN this office
        $tempOfficer->reports_to_branch_id = $parentBranchId;                  // LOOK IN this branch

        // Recursively find officers at the next level
        return $this->findEffectiveReportsTo($tempOfficer, $visitedOffices);
    }
}
