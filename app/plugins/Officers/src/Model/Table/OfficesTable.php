<?php

declare(strict_types=1);

namespace Officers\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use App\Model\Table\BaseTable;
use Cake\ORM\TableRegistry;
use App\Model\Entity\Member;

/**
 * Offices Model
 *
 * @property \App\Model\Table\DepartmentsTable&\Cake\ORM\Association\BelongsTo $Departments
 * @property \App\Model\Table\OfficersTable&\Cake\ORM\Association\HasMany $Officers
 *
 * @method \App\Model\Entity\Office newEmptyEntity()
 * @method \App\Model\Entity\Office newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\Office> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Office get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\Office findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\Office patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\Office> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Office|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\Office saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\Office>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Office>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Office>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Office> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Office>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Office>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Office>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Office> deleteManyOrFail(iterable $entities, array $options = [])
 */
class OfficesTable extends BaseTable
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

        $this->setTable('officers_offices');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->belongsTo('Departments', [
            'className' => 'Officers.Departments',
            'foreignKey' => 'department_id',
        ]);
        $this->belongsTo("GrantsRole", [
            "className" => "Roles",
            "foreignKey" => "grants_role_id",
            "joinType" => "LEFT",
        ]);
        $this->belongsTo('DeputyTo', [
            'className' => 'Officers.Offices',
            'foreignKey' => 'deputy_to_id',
            'joinType' => 'LEFT',
        ]);
        $this->belongsTo('ReportsTo', [
            'className' => 'Officers.Offices',
            'foreignKey' => 'reports_to_id',
            'joinType' => 'LEFT',
        ]);
        $this->hasMany('Deputies', [
            'className' => 'Officers.Offices',
            'foreignKey' => 'deputy_to_id',
        ]);
        $this->hasMany('DirectReports', [
            'className' => 'Officers.Offices',
            'foreignKey' => 'reports_to_id',
        ]);
        $this->hasMany("Officers", [
            "className" => "Officers.Officers",
            "foreignKey" => "office_id"
        ]);
        $this->hasMany("CurrentOfficers", [
            "className" => "Officers.Officers",
            "foreignKey" => "office_id",
            "finder" => "current",
        ]);
        $this->hasMany("UpcomingOfficers", [
            "className" => "Officers.Officers",
            "foreignKey" => "office_id",
            "finder" => "upcoming",
        ]);
        $this->hasMany("PreviousOfficers", [
            "className" => "Officers.Officers",
            "foreignKey" => "office_id",
            "finder" => "previous",
        ]);
        $this->addBehavior("Timestamp");
        $this->addBehavior('Muffin/Footprint.Footprint');
        $this->addBehavior("Muffin/Trash.Trash");
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
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmptyString('name')
            ->add('name', 'unique', ['rule' => 'validateUnique', 'provider' => 'table']);

        $validator
            ->integer('department_id')
            ->notEmptyString('department_id');

        $validator
            ->boolean('requires_warrant')
            ->notEmptyString('requires_warrant');

        $validator
            ->boolean('only_one_per_branch')
            ->notEmptyString('only_one_per_branch');

        $validator
            ->integer('deputy_to_id')
            ->allowEmptyString('deputy_to_id');

        $validator
            ->integer('reports_to_id')
            ->allowEmptyString('reports_to_id');

        $validator
            ->integer('grants_role_id')
            ->allowEmptyString('grants_role_id');

        $validator
            ->integer('term_length')
            ->requirePresence('term_length', 'create')
            ->notEmptyString('term_length');

        $validator
            ->date('deleted')
            ->allowEmptyDate('deleted');

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
        $rules->add($rules->isUnique(['name']), ['errorField' => 'name']);
        $rules->add($rules->existsIn(['department_id'], 'Departments'), ['errorField' => 'department_id']);

        return $rules;
    }

    /**
     * Get a list of office IDs the member can work with, based on permissions and branch.
     *
     * @param User $user The user entity.
     * @param int $branchId The branch ID to check permissions for.
     * @return int[] List of office IDs the user can work with.
     */
    public function officesMemberCanWork(Member $user, int|null $branchId): array
    {
        // Superusers can work with all offices
        if ($user->isSuperUser()) {
            return $this->find('list', [
                'keyField' => 'id',
                'valueField' => 'id',
            ])->toArray();
        }

        $officersTbl = TableRegistry::getTableLocator()->get('Officers.Officers');
        $userOffices = $officersTbl->find('current')
            ->where(['member_id' => $user->id])
            ->select(['id', 'office_id', 'branch_id'])
            ->all();

        $canHireOffices = [];
        $visited = [];

        if (empty($userOffices) || $branchId == null) {
            // No offices found or branch ID is null, return empty array
            $newOfficer = $officersTbl->newEmptyEntity();
            if ($user->checkCan('workWithAllOfficers', $newOfficer, null, true)) {
                $this->find()
                    ->select(['id'])
                    ->all();
                foreach ($userOffices as $userOffice) {
                    $canHireOffices[$userOffice->office_id] = true;
                }
                return array_keys($canHireOffices);
            }
            return [];
        }

        foreach ($userOffices as $userOffice) {
            // workWithOfficerDeputies permission
            if ($user->checkCan('workWithOfficerDeputies', $userOffice, $branchId, true)) {
                $deputies = $this->find()
                    ->where(['deputy_to_id' => $userOffice->office_id])
                    ->select(['id'])
                    ->all();
                foreach ($deputies as $deputy) {
                    $canHireOffices[$deputy->id] = true;
                }
            }
            // workWithOfficerDirectReports permission
            if ($user->checkCan('workWithOfficerDirectReports', $userOffice, $branchId, true)) {
                $directs = $this->find()
                    ->where([
                        'OR' => [
                            'deputy_to_id' => $userOffice->office_id,
                            'reports_to_id' => $userOffice->office_id,
                        ],
                    ])
                    ->select(['id'])
                    ->all();
                foreach ($directs as $direct) {
                    $canHireOffices[$direct->id] = true;
                }
            }
            // workWithOfficerReportingTree permission
            if ($user->checkCan('workWithOfficerReportingTree', $userOffice, $branchId, true)) {
                $toVisit = [$userOffice->office_id];
                while ($toVisit) {
                    $nextLevel = $this->find()
                        ->where([
                            'OR' => [
                                'deputy_to_id IN' => $toVisit,
                                'reports_to_id IN' => $toVisit,
                            ],
                        ])
                        ->select(['id'])
                        ->all();
                    $newIds = [];
                    foreach ($nextLevel as $office) {
                        if (!isset($visited[$office->id])) {
                            $canHireOffices[$office->id] = true;
                            $visited[$office->id] = true;
                            $newIds[] = $office->id;
                        }
                    }
                    $toVisit = $newIds;
                }
            }
        }
        return array_keys($canHireOffices);
    }
}