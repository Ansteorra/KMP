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
 * Departments Table - Departmental data management
 *
 * Manages departmental categorization, office relationships, and permission-based
 * department visibility within the Officers plugin.
 *
 * @property \Officers\Model\Table\OfficesTable&\Cake\ORM\Association\HasMany $Offices
 *
 * @method \Officers\Model\Entity\Department newEmptyEntity()
 * @method \Officers\Model\Entity\Department newEntity(array $data, array $options = [])
 * @method \Officers\Model\Entity\Department get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \Officers\Model\Entity\Department findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \Officers\Model\Entity\Department patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \Officers\Model\Entity\Department|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \Officers\Model\Entity\Department saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 *
 * @see /docs/5.1-officers-plugin.md
 */
class DepartmentsTable extends BaseTable
{
    /**
     * Initialize table configuration and associations.
     *
     * @param array<string, mixed> $config The configuration for the Table
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
     * Configure validation rules for department entities.
     *
     * @param \Cake\Validation\Validator $validator Validator instance
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
     * Configure database-level integrity rules.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['name']), ['errorField' => 'name']);

        return $rules;
    }

    /**
     * Get departments accessible to a user based on permissions and officer assignments.
     *
     * Returns all departments for super users or those with `seeAllDepartments` permission.
     * Standard users see only departments where they hold active officer positions.
     *
     * @param \App\Model\Entity\User $user The user to check access for
     * @return array<int, string> Associative array of department id => name
     */
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
                $returnList[$department->id] = $department->name;
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
            $returnList[$officer->office->department->id] = $officer->office->department->name;
        }
        return $returnList;
    }
}
