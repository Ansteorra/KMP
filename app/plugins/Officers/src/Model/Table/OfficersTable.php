<?php

declare(strict_types=1);

namespace Officers\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use App\KMP\StaticHelpers;
use App\Model\Entity\Warrant;
use Cake\I18n\DateTime;
use Officers\Model\Entity\Officer;
use App\Model\Table\BaseTable;

/**
 * Officers Table - Officer lifecycle management and ActiveWindow integration
 *
 * The OfficersTable provides comprehensive data management operations for officer
 * assignments within the Officers plugin. It handles complex temporal assignment
 * workflows, warrant integration, hierarchical relationship management, and
 * automated status transitions through ActiveWindow behavior integration. This
 * table serves as the core of the officer assignment system with deep integration
 * into KMP's authorization and organizational management frameworks.
 *
 * ## Key Features
 * - **ActiveWindow Integration**: Temporal assignment management with automatic status transitions
 * - **Warrant Lifecycle**: Complete warrant workflow integration with status tracking
 * - **Hierarchical Relationships**: Deputy and reporting relationship management
 * - **Assignment Management**: Officer assignment lifecycle with approval workflows
 * - **Status Automation**: Automated status updates based on temporal conditions
 * - **Branch Integration**: Branch-specific assignment scoping and management
 * - **Audit Trail**: Comprehensive assignment history and approval tracking
 * - **Permission Integration**: Deep integration with KMP authorization framework
 *
 * ## Database Structure
 * - **Table**: `officers_officers`
 * - **Primary Key**: `id` (auto-increment)
 * - **Display Field**: `status` (current assignment status)
 * - **Temporal Fields**: `start_on`, `expires_on` for ActiveWindow integration
 * - **Status Management**: Automated status transitions via ActiveWindow behavior
 *
 * ## Association Architecture
 * The table implements a comprehensive association structure supporting the complete
 * officer assignment ecosystem:
 *
 * ### Core Assignment Relationships
 * - **belongsTo Members**: Officer assignment to specific members
 * - **belongsTo Branches**: Branch context for assignment scoping
 * - **belongsTo Offices**: Office position being filled by assignment
 *
 * ### Approval and Revocation Tracking
 * - **belongsTo ApprovedBy**: Member who approved the assignment
 * - **belongsTo RevokedBy**: Member who revoked the assignment (optional)
 * - **Audit Integration**: Complete approval workflow tracking
 *
 * ### Hierarchical Relationship Management
 * - **belongsTo ReportsToOffices**: Office this assignment reports to
 * - **belongsTo ReportsToBranches**: Branch this assignment reports to
 * - **belongsTo DeputyToOffices**: Office this assignment serves as deputy
 * - **belongsTo DeputyToBranches**: Branch this assignment serves as deputy
 *
 * ### Current Relationship Tracking
 * - **hasMany ReportsToCurrently**: Current officers reporting to this assignment
 * - **hasMany DeputyToCurrently**: Current officers serving as deputies to this assignment
 * - **Temporal Awareness**: Automatically filters to current assignments
 *
 * ### Warrant Integration
 * - **hasOne CurrentWarrants**: Active warrant for this assignment
 * - **hasMany PendingWarrants**: Pending warrants awaiting approval
 * - **hasMany Warrants**: Complete warrant history for this assignment
 * - **Status Synchronization**: Warrant status integration with assignment status
 *
 * ## Behavior Integration
 * - **Timestamp**: Automatic created/modified timestamp management
 * - **Footprint**: User tracking for created_by/modified_by fields
 * - **ActiveWindow**: Temporal assignment management with automatic status transitions
 * - **BaseTable**: Inherits KMP table functionality including cache management
 *
 * ## ActiveWindow Configuration
 * The table leverages ActiveWindow behavior for sophisticated temporal management:
 * - **Automatic Status Updates**: Daily status checks and transitions
 * - **Temporal Queries**: Built-in support for current, upcoming, and previous finders
 * - **Status Synchronization**: Coordinated status updates across assignment lifecycle
 * - **Expiration Management**: Automatic handling of assignment expiration
 *
 * ## Validation Framework
 * The table implements comprehensive validation including:
 * - Member assignment validation with referential integrity
 * - Branch assignment validation for organizational consistency
 * - Office assignment validation with hierarchical constraints
 * - Temporal validation for assignment dates and durations
 * - Status validation for assignment lifecycle management
 * - Revocation validation for administrative oversight
 *
 * ## Automated Status Management
 * The table includes sophisticated status automation:
 * - **Daily Status Checks**: Automatic expiration and activation processing
 * - **Status Transitions**: Automated UPCOMING → CURRENT → EXPIRED transitions
 * - **Application Setting Integration**: Tracks last check date for optimization
 * - **Bulk Status Updates**: Efficient batch processing for status changes
 *
 * ## Query Enhancement
 * The table provides specialized query enhancement through `addDisplayConditionsAndFields()`:
 * - **Type-Specific Fields**: Dynamic field selection based on officer type
 * - **Complex Expressions**: Case expressions for status-dependent display
 * - **Association Loading**: Optimized containment for hierarchical data
 * - **Display Optimization**: Specialized formatting for different officer contexts
 *
 * ## Usage Patterns
 * ```php
 * // Standard officer operations
 * $officersTable = TableRegistry::getTableLocator()->get('Officers.Officers');
 * 
 * // Create new officer assignment
 * $officer = $officersTable->newEntity([
 *     'member_id' => $memberId,
 *     'office_id' => $officeId,
 *     'branch_id' => $branchId,
 *     'start_on' => '2025-01-01',
 *     'expires_on' => '2026-01-01',
 *     'status' => Officer::UPCOMING_STATUS
 * ]);
 * $officersTable->save($officer);
 * 
 * // Query current officers with relationships
 * $currentOfficers = $officersTable->find('current')
 *     ->contain(['Members', 'Offices', 'Branches', 'CurrentWarrants']);
 * ```
 *
 * ## Integration Points
 * - **Member Management**: Direct relationship with member assignment workflows
 * - **Office Management**: Integration with office hierarchy and role assignment
 * - **Branch Management**: Branch-specific assignment scoping and management
 * - **Warrant System**: Complete warrant lifecycle integration and synchronization
 * - **Authorization Framework**: Permission-based assignment access control
 * - **Reporting System**: Assignment analytics and organizational reporting
 * - **Administrative Interfaces**: Assignment management and approval workflows
 * - **Notification System**: Assignment lifecycle notification integration
 *
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $Members Member assignment relationship
 * @property \App\Model\Table\BranchesTable&\Cake\ORM\Association\BelongsTo $Branches Branch context relationship
 * @property \Officers\Model\Table\OfficesTable&\Cake\ORM\Association\BelongsTo $Offices Office position relationship
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $ApprovedBy Assignment approver relationship
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $RevokedBy Assignment revoker relationship
 * @property \Officers\Model\Table\OfficesTable&\Cake\ORM\Association\BelongsTo $ReportsToOffices Reporting office relationship
 * @property \App\Model\Table\BranchesTable&\Cake\ORM\Association\BelongsTo $ReportsToBranches Reporting branch relationship
 * @property \Officers\Model\Table\OfficesTable&\Cake\ORM\Association\BelongsTo $DeputyToOffices Deputy office relationship
 * @property \App\Model\Table\BranchesTable&\Cake\ORM\Association\BelongsTo $DeputyToBranches Deputy branch relationship
 * @property \App\Model\Table\WarrantsTable&\Cake\ORM\Association\HasOne $CurrentWarrants Active warrant relationship
 * @property \App\Model\Table\WarrantsTable&\Cake\ORM\Association\HasMany $PendingWarrants Pending warrants relationship
 * @property \App\Model\Table\WarrantsTable&\Cake\ORM\Association\HasMany $Warrants Complete warrant history
 * @property \Officers\Model\Table\OfficersTable&\Cake\ORM\Association\HasMany $ReportsToCurrently Current direct reports
 * @property \Officers\Model\Table\OfficersTable&\Cake\ORM\Association\HasMany $DeputyToCurrently Current deputy assignments
 *
 * @method \Officers\Model\Entity\Officer newEmptyEntity()
 * @method \Officers\Model\Entity\Officer newEntity(array $data, array $options = [])
 * @method array<\Officers\Model\Entity\Officer> newEntities(array $data, array $options = [])
 * @method \Officers\Model\Entity\Officer get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \Officers\Model\Entity\Officer findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \Officers\Model\Entity\Officer patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\Officers\Model\Entity\Officer> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \Officers\Model\Entity\Officer|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \Officers\Model\Entity\Officer saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\Officers\Model\Entity\Officer>|\Cake\Datasource\ResultSetInterface<\Officers\Model\Entity\Officer>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\Officers\Model\Entity\Officer>|\Cake\Datasource\ResultSetInterface<\Officers\Model\Entity\Officer> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\Officers\Model\Entity\Officer>|\Cake\Datasource\ResultSetInterface<\Officers\Model\Entity\Officer>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\Officers\Model\Entity\Officer>|\Cake\Datasource\ResultSetInterface<\Officers\Model\Entity\Officer> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @see \Officers\Model\Entity\Officer For officer entity documentation
 * @see \Officers\Model\Table\OfficesTable For office management operations
 * @see \App\Model\Table\BaseTable For inherited table functionality
 * @see \App\Behavior\ActiveWindowBehavior For temporal assignment management
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
     * Adds display conditions and fields to the query based on the officer type.
     *
     * @param \Cake\ORM\Query\SelectQuery $q The query object.
     * @param string $type The type of officers to retrieve (current, upcoming, previous).
     * @return \Cake\ORM\Query\SelectQuery The modified query object.
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
}
