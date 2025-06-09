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
     * @param \App\Model\Entity\Member $user The user entity.
     * @param int|null $branchId The branch ID to check permissions for.
     * @return int[] List of office IDs the user can work with.
     */
    public function officesMemberCanWork(Member $user, int|null $branchId): array
    {
        // Early returns for edge cases

        // Superusers can work with all offices
        if ($user->isSuperUser()) {
            return $this->getAllOfficeIds();
        }

        // Check if user has global officer permissions
        if ($this->hasGlobalOfficerPermissions($user)) {
            return $this->getAllOfficeIds();
        }
        // If no branch ID is provided, return empty array
        if ($branchId === null) {
            return [];
        }

        // Get user's current officer positions
        $userOfficerPositions = $this->getUserOfficerPositions($user);
        if (empty($userOfficerPositions)) {
            return [];
        }

        // Calculate accessible offices based on permissions
        return $this->calculateAccessibleOffices($user, $userOfficerPositions, $branchId);
    }

    /**
     * Get all office IDs efficiently.
     *
     * @return int[]
     */
    private function getAllOfficeIds(): array
    {
        $results = $this->find()
            ->select(['id'])
            ->orderBy(['id'])
            ->enableHydration(false)
            ->toArray();

        return array_column($results, 'id');
    }

    /**
     * Check if user has global officer permissions.
     *
     * @param \App\Model\Entity\Member $user The user entity.
     * @return bool
     */
    private function hasGlobalOfficerPermissions(Member $user): bool
    {
        $officersTbl = TableRegistry::getTableLocator()->get('Officers.Officers');
        $newOfficer = $officersTbl->newEmptyEntity();

        return $user->checkCan('workWithAllOfficers', $newOfficer, null, true);
    }

    /**
     * Get user's current officer positions with relevant data.
     *
     * @param \App\Model\Entity\Member $user The user entity.
     * @return array
     */
    private function getUserOfficerPositions(Member $user): array
    {
        $officersTbl = TableRegistry::getTableLocator()->get('Officers.Officers');

        return $officersTbl->find('current')
            ->where(['member_id' => $user->id])
            ->select(['id', 'office_id', 'branch_id'])
            ->enableHydration(false)
            ->toArray();
    }

    /**
     * Calculate accessible offices based on user permissions and positions.
     *
     * @param \App\Model\Entity\Member $user The user entity.
     * @param array $userOfficerPositions User's current officer positions.
     * @param int $branchId The branch ID to check permissions for.
     * @return int[]
     */
    private function calculateAccessibleOffices(Member $user, array $userOfficerPositions, int $branchId): array
    {
        $accessibleOfficeIds = [];
        $permissionCache = [];

        foreach ($userOfficerPositions as $position) {
            $accessibleOfficeIds = array_merge(
                $accessibleOfficeIds,
                $this->getOfficesForPosition($user, $position, $branchId, $permissionCache)
            );
        }

        return array_unique($accessibleOfficeIds);
    }

    /**
     * Get accessible offices for a specific user position.
     *
     * @param \App\Model\Entity\Member $user The user entity.
     * @param array $position The user's officer position.
     * @param int $branchId The branch ID.
     * @param array &$permissionCache Permission cache for optimization.
     * @return int[]
     */
    private function getOfficesForPosition(Member $user, array $position, int $branchId, array &$permissionCache): array
    {
        $officeIds = [];
        $officeId = $position['office_id'];

        // Cache key for permissions
        $cacheKey = $position['id'] . '_' . $branchId;

        // Get permissions for this position (with caching)
        if (!isset($permissionCache[$cacheKey])) {
            $permissionCache[$cacheKey] = $this->getPositionPermissions($user, $position, $branchId);
        }
        $permissions = $permissionCache[$cacheKey];

        // Add offices based on permissions
        if ($permissions['deputies']) {
            $officeIds = array_merge($officeIds, $this->getDeputyOffices($officeId));
        }

        if ($permissions['directReports']) {
            $officeIds = array_merge($officeIds, $this->getDirectReportOffices($officeId));
        }

        if ($permissions['reportingTree']) {
            $officeIds = array_merge($officeIds, $this->getReportingTreeOffices($officeId));
        }

        return $officeIds;
    }

    /**
     * Get permissions for a user's position.
     *
     * @param \App\Model\Entity\Member $user The user entity.
     * @param array $position The user's officer position.
     * @param int $branchId The branch ID.
     * @return array
     */
    private function getPositionPermissions(Member $user, array $position, int $branchId): array
    {
        return [
            'deputies' => $user->checkCan('workWithOfficerDeputies', $position, $branchId, true),
            'directReports' => $user->checkCan('workWithOfficerDirectReports', $position, $branchId, true),
            'reportingTree' => $user->checkCan('workWithOfficerReportingTree', $position, $branchId, true),
        ];
    }

    /**
     * Get deputy office IDs for a given office.
     *
     * @param int $officeId The office ID.
     * @return int[]
     */
    private function getDeputyOffices(int $officeId): array
    {
        $results = $this->find()
            ->where(['deputy_to_id' => $officeId])
            ->select(['id'])
            ->enableHydration(false)
            ->toArray();

        return array_column($results, 'id');
    }

    /**
     * Get direct report office IDs for a given office.
     *
     * @param int $officeId The office ID.
     * @return int[]
     */
    private function getDirectReportOffices(int $officeId): array
    {
        $results = $this->find()
            ->where([
                'OR' => [
                    'deputy_to_id' => $officeId,
                    'reports_to_id' => $officeId,
                ],
            ])
            ->select(['id'])
            ->enableHydration(false)
            ->toArray();

        return array_column($results, 'id');
    }

    /**
     * Get all offices in the reporting tree for a given office using breadth-first traversal.
     *
     * @param int $rootOfficeId The root office ID.
     * @return int[]
     */
    private function getReportingTreeOffices(int $rootOfficeId): array
    {
        $allOfficeIds = [];
        $visited = [];
        $toVisit = [$rootOfficeId];

        while (!empty($toVisit)) {
            $currentLevelResults = $this->find()
                ->where([
                    'OR' => [
                        'deputy_to_id IN' => $toVisit,
                        'reports_to_id IN' => $toVisit,
                    ],
                ])
                ->select(['id'])
                ->enableHydration(false)
                ->toArray();

            $currentLevelIds = array_column($currentLevelResults, 'id');
            $nextLevel = [];

            foreach ($currentLevelIds as $officeId) {
                if (!isset($visited[$officeId])) {
                    $visited[$officeId] = true;
                    $allOfficeIds[] = $officeId;
                    $nextLevel[] = $officeId;
                }
            }

            $toVisit = $nextLevel;
        }

        return $allOfficeIds;
    }
}
