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
use Officers\Model\Entity\Officer;

/**
 * Offices Table - Hierarchical office structure and permission-based access
 *
 * Manages office positions with hierarchical deputy/reporting relationships,
 * warrant requirements, role assignments, and branch-scoped access control.
 *
 * @property \Officers\Model\Table\DepartmentsTable&\Cake\ORM\Association\BelongsTo $Departments
 * @property \App\Model\Table\RolesTable&\Cake\ORM\Association\BelongsTo $GrantsRole
 * @property \Officers\Model\Table\OfficesTable&\Cake\ORM\Association\BelongsTo $DeputyTo
 * @property \Officers\Model\Table\OfficesTable&\Cake\ORM\Association\BelongsTo $ReportsTo
 * @property \Officers\Model\Table\OfficesTable&\Cake\ORM\Association\HasMany $Deputies
 * @property \Officers\Model\Table\OfficesTable&\Cake\ORM\Association\HasMany $DirectReports
 * @property \Officers\Model\Table\OfficersTable&\Cake\ORM\Association\HasMany $Officers
 * @property \Officers\Model\Table\OfficersTable&\Cake\ORM\Association\HasMany $CurrentOfficers
 * @property \Officers\Model\Table\OfficersTable&\Cake\ORM\Association\HasMany $UpcomingOfficers
 * @property \Officers\Model\Table\OfficersTable&\Cake\ORM\Association\HasMany $PreviousOfficers
 *
 * @method \Officers\Model\Entity\Office newEmptyEntity()
 * @method \Officers\Model\Entity\Office newEntity(array $data, array $options = [])
 * @method \Officers\Model\Entity\Office get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \Officers\Model\Entity\Office findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \Officers\Model\Entity\Office patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \Officers\Model\Entity\Office|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \Officers\Model\Entity\Office saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 *
 * @see /docs/5.1-officers-plugin.md
 */
class OfficesTable extends BaseTable
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
     * Configure validation rules for office entities.
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
     * Configure database-level integrity rules.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['name']), ['errorField' => 'name']);
        $rules->add($rules->existsIn(['department_id'], 'Departments'), ['errorField' => 'department_id']);

        return $rules;
    }

    /**
     * Get offices accessible to a user based on permissions and hierarchical position.
     *
     * Super users and those with `workWithAllOfficers` permission get all offices.
     * Other users get offices based on their current officer positions and
     * hierarchical permissions (deputies, direct reports, reporting tree).
     *
     * @param \App\Model\Entity\Member $user The user to check access for
     * @param int|null $branchId Branch context for permission checking
     * @return int[] Office IDs the user can work with
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
     * Get all office IDs for super users and global permission holders.
     *
     * @return int[] All office IDs
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
     * Check if user has global officer management permissions.
     *
     * @param \App\Model\Entity\Member $user The user to check
     * @return bool True if user has workWithAllOfficers permission
     */
    private function hasGlobalOfficerPermissions(Member $user): bool
    {
        $officersTbl = TableRegistry::getTableLocator()->get('Officers.Officers');
        $newOfficer = $officersTbl->newEmptyEntity();

        return $user->checkCan('workWithAllOfficers', $newOfficer, null, true);
    }

    /**
     * Get user's current officer positions.
     *
     * @param \App\Model\Entity\Member $user The user to get positions for
     * @return array Current officer position entities
     */
    private function getUserOfficerPositions(Member $user): array
    {
        $officersTbl = TableRegistry::getTableLocator()->get('Officers.Officers');

        return $officersTbl->find('current')
            ->where(['member_id' => $user->id])
            ->select(['id', 'office_id', 'branch_id'])
            ->toArray();
    }

    /**
     * Calculate accessible offices based on user positions and permissions.
     *
     * @param \App\Model\Entity\Member $user The user entity
     * @param array $userOfficerPositions User's current officer positions
     * @param int $branchId Branch context for permission checking
     * @return int[] Unique office IDs the user can access
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
     * @param \Officers\Model\Entity\Officer $position The user's officer position.
     * @param int $branchId The branch ID.
     * @param array &$permissionCache Permission cache for optimization.
     * @return int[]
     */
    private function getOfficesForPosition(Member $user, Officer $position, int $branchId, array &$permissionCache): array
    {
        $officeIds = [];
        $officeId = $position->office_id;

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
     * @param \Officers\Model\Entity\Officer $position The user's officer position.
     * @param int $branchId The branch ID.
     * @return array
     */
    private function getPositionPermissions(Member $user, Officer $position, int $branchId): array
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
     * Return the office IDs that are reachable from a given office through deputy or reporting relationships in breadth-first order.
     *
     * @param int $rootOfficeId The starting office ID whose reporting tree will be traversed; the returned list excludes this root.
     * @return int[] An array of office IDs encountered in breadth-first order, excluding the root office.
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

    /**
     * Determine the appropriate branch ID for an office's reports_to_branch_id.
     *
     * Finds a branch starting from $startBranchId (and moving up the parent chain if necessary)
     * that is compatible with the branch types allowed for the specified reports-to office.
     *
     * @param int $startBranchId The branch where the officer is being hired.
     * @param int|null $reportsToOfficeId The ID of the office this officer reports to, or null.
     * @return int|null The branch ID compatible with the reports-to office, or null if no reports-to office was provided.
     */
    public function findCompatibleBranchForOffice(int $startBranchId, ?int $reportsToOfficeId): ?int
    {
        // If no reporting office, no branch needed
        if ($reportsToOfficeId === null) {
            return null;
        }

        // Get the office to check its branch_types
        $office = $this->get($reportsToOfficeId, ['fields' => ['id', 'applicable_branch_types']]);

        // If office has no branch type restrictions, use the parent branch
        if (empty($office->applicable_branch_types)) {
            $branchTable = TableRegistry::getTableLocator()->get('Branches');
            $branch = $branchTable->get($startBranchId, ['fields' => ['id', 'parent_id']]);
            return $branch->parent_id;
        }

        // Get the branch types the office is compatible with
        $compatibleBranchTypes = $office->branch_types;

        // Traverse up the branch hierarchy looking for a compatible branch
        $branchTable = TableRegistry::getTableLocator()->get('Branches');
        $currentBranchId = $startBranchId;
        $lastValidBranchId = $startBranchId; // Fallback to top if nothing matches

        while ($currentBranchId !== null) {
            $currentBranch = $branchTable->get($currentBranchId, ['fields' => ['id', 'type', 'parent_id']]);

            // Check if this branch type is compatible with the office
            if (in_array($currentBranch->type, $compatibleBranchTypes)) {
                return $currentBranchId;
            }

            // Remember this as a potential fallback
            $lastValidBranchId = $currentBranchId;

            // Move up to parent
            $currentBranchId = $currentBranch->parent_id;
        }

        // If we didn't find a match, return the top of the hierarchy
        return $lastValidBranchId;
    }
}
