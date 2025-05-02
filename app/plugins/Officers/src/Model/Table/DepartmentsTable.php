<?php

declare(strict_types=1);

namespace Officers\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use App\Model\Table\BaseTable;
use Cake\ORM\TableRegistry;

/**
 * Departments Model
 *
 * @property \App\Model\Table\OfficesTable&\Cake\ORM\Association\HasMany $Offices
 *
 * @method \App\Model\Entity\Department newEmptyEntity()
 * @method \App\Model\Entity\Department newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\Department> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Department get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\Department findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\Department patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\Department> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Department|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\Department saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\Department>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Department>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Department>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Department> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Department>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Department>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Department>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Department> deleteManyOrFail(iterable $entities, array $options = [])
 */
class DepartmentsTable extends BaseTable
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

        $this->setTable('officers_departments');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->hasMany('Offices', [
            'className' => 'Officers.Offices',
            'foreignKey' => 'department_id',
        ]);
        $this->addBehavior("Timestamp");
        $this->addBehavior('Muffin/Footprint.Footprint');
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

        return $rules;
    }

    public function departmentsMemberCanWork($user)
    {
        if (empty($user->id)) {
            return [];
        }
        $emptyDepartment = $this->newEmptyEntity();
        $canSeeAllDepartments = $user->checkCan('seeAllDepartments', $emptyDepartment);
        if ($user->isSuperUser() || $user->checkCan('seeAllDepartments', $emptyDepartment)) {
            $notList = $this->find('all')->select(['id', 'name'])->orderBy(["name"])->toArray();
            $returnList = [];
            foreach ($notList as $key => $department) {
                $returnList[$department->id] = $department->name;;
            }
            return $returnList;
        }
        $officesTable = TableRegistry::getTableLocator()->get('Officers.Offices');
        $officers = $officesTable->CurrentOfficers->find('all')
            ->where(['member_id' => $user->id])
            ->contain([
                'Offices' => function (SelectQuery $q) {
                    return $q->select(['Offices.id']);
                },
                'Offices.Departments' => function (SelectQuery $q) {
                    return $q->select(['Departments.id', 'Departments.name']);
                }
            ])->select(['Departments.name', 'Departments.id'])->distinct(['Departments.name', 'Departments.id'])->toArray();
        $returnList = [];
        foreach ($officers as $key => $officer) {
            $returnList[$officer->office->department->id] = $officer->office->department->name;;
        }
        return $returnList;
    }
}
