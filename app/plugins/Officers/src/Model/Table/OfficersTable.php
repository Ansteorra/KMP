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
 * Officers Model
 *
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $Members
 * @property \App\Model\Table\BranchesTable&\Cake\ORM\Association\BelongsTo $Branches
 * @property \App\Model\Table\OfficesTable&\Cake\ORM\Association\BelongsTo $Offices
 *
 * @method \App\Model\Entity\Officer newEmptyEntity()
 * @method \App\Model\Entity\Officer newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\Officer> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Officer get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\Officer findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\Officer patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\Officer> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Officer|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\Officer saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\Officer>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Officer>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Officer>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Officer> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Officer>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Officer>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Officer>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Officer> deleteManyOrFail(iterable $entities, array $options = [])
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