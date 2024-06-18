<?php

declare(strict_types=1);

namespace Officers\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use App\KMP\StaticHelpers;
use Cake\I18n\DateTime;
use Officers\Model\Entity\Officer;

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
class OfficersTable extends Table
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

        $this->setTable('officers');
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
        $this->addBehavior("Timestamp");
        $this->addBehavior('Muffin/Footprint.Footprint');
        $this->addBehavior("ActiveWindow");

        $lastOfficerExpCheck = new DateTime(StaticHelpers::getAppSetting("Officer.NextStatusCheck", DateTime::now()->subDays(1)->toDateString()));
        if ($lastOfficerExpCheck->isPast()) {
            $this->checkOfficerStatus();
            StaticHelpers::setAppSetting("Officer.NextStatusCheck", DateTime::now()->addDays(1)->toDateString());
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
}