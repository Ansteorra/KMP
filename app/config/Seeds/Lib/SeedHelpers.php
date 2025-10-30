<?php

declare(strict_types=1);


use Cake\ORM\TableRegistry;

class SeedHelpers
{
    /**
     * @var bool Flag to indicate if we're in test fixture loading mode
     */
    private static bool $testMode = false;

    /**
     * @var array Static lookup maps for test fixtures
     * These IDs match the auto-increment order of fixture loading
     */
    private static array $testLookups = [
        'branches' => ['Kingdom' => 1],
        'roles' => ['Admin' => 1, 'TestSuperUser' => 2],
        'permissions' => [
            'Is Super User' => 1,
            'Can Manage Roles' => 2,
            'Can Do All But Is Not A Super User' => 3,
        ],
        'members' => [
            'admin@test.com' => 1,
            'Admin von Admin' => 1,
            'testsuper@test.com' => 2,
            'Test Super User' => 2,
        ],
    ];

    /**
     * Activate test fixture mode so lookup methods return values from static test mappings instead of querying the database.
     */
    public static function enableTestMode(): void
    {
        self::$testMode = true;
    }

    /**
     * Disable test mode
     */
    public static function disableTestMode(): void
    {
        self::$testMode = false;
    }

    /**
         * Store or update a static lookup value used during test-mode seeding.
         *
         * @param string $table The table namespace/key grouping for the lookup (e.g., 'Roles', 'Branches').
         * @param string $key The lookup key within the table (for example a name or identifier).
         * @param int $value The ID value to return when the lookup is used in test mode.
         */
    public static function setTestLookup(string $table, string $key, int $value): void
    {
        self::$testLookups[$table][$key] = $value;
    }

    /**
     * Resolve an ActivityGroup's primary key from its name.
     *
     * @param string $name The activity group name to look up.
     * @return int The matching ActivityGroup id.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException If no ActivityGroup with the given name exists.
     */
    public static function getActivityGroupId(string $name): int
    {
        $activityGroupsTable = TableRegistry::getTableLocator()->get('Activities.ActivityGroups');
        $activityGroup = $activityGroupsTable->find()->where(['name' => $name])->firstOrFail();
        return $activityGroup->id;
    }

    /**
     * Resolve the ID of a role given its name.
     *
     * When a role name is provided, returns the associated role ID; if the name is null, returns null.
     * In test mode the method will return a predefined lookup value when available instead of querying the database.
     *
     * @param string|null $name The role name to look up.
     * @return int|null `int` role ID if found, `null` if `$name` is null.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException If no role with the given name exists.
     */
    public static  function getRoleId(?string $name): ?int
    {
        if ($name === null) {
            return null;
        }

        // Test mode: use static lookup
        if (self::$testMode && isset(self::$testLookups['roles'][$name])) {
            return self::$testLookups['roles'][$name];
        }

        $rolesTable = TableRegistry::getTableLocator()->get('Roles');
        $role = $rolesTable->find()->where(['name' => $name])->firstOrFail();
        return $role->id;
    }

    /**
     * Resolve the ID for a permission by its name.
     *
     * If test mode is enabled and a static lookup exists for the given name, that value is returned; otherwise the Permissions table is queried.
     *
     * @param string $name The permission name to resolve.
     * @return int The ID of the matching permission.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException If no permission with the given name exists.
     */
    public static  function getPermissionId(string $name): int
    {
        // Test mode: use static lookup
        if (self::$testMode && isset(self::$testLookups['permissions'][$name])) {
            return self::$testLookups['permissions'][$name];
        }

        $permissionsTable = TableRegistry::getTableLocator()->get('Permissions');
        $permission = $permissionsTable->find()->where(['name' => $name])->firstOrFail();
        return $permission->id;
    }

    /**
     * Resolve a member's numeric ID from an email address or SCA name.
     *
     * When test mode is enabled and a static test lookup exists for the provided key,
     * the mapped ID from the test lookups is returned. Otherwise the function finds
     * the member by matching either email_address or sca_name and returns its id.
     *
     * @param string $emailOrScaName The member's email address or SCA name to look up.
     * @return int The resolved member ID.
     */
    public static  function getMemberId(string $emailOrScaName): int
    {
        // Test mode: use static lookup
        if (self::$testMode && isset(self::$testLookups['members'][$emailOrScaName])) {
            return self::$testLookups['members'][$emailOrScaName];
        }

        $membersTable = TableRegistry::getTableLocator()->get('Members');
        $member = $membersTable->find()->where([
            'OR' => ['email_address' => $emailOrScaName, 'sca_name' => $emailOrScaName]
        ])->firstOrFail();
        return $member->id;
    }

    /**
     * Resolves a branch name to its ID, using a static test lookup when test mode is enabled.
     *
     * @param string|null $name The branch name to resolve, or `null` to return `null`.
     * @return int|null The branch's ID if found; `null` when `$name` is `null`.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException If no branch with the given name exists.
     */
    public static function getBranchIdByName(?string $name): ?int
    {
        if ($name === null) {
            return null;
        }

        // Test mode: use static lookup
        if (self::$testMode && isset(self::$testLookups['branches'][$name])) {
            return self::$testLookups['branches'][$name];
        }

        $branchesTable = TableRegistry::getTableLocator()->get('Branches');
        $branch = $branchesTable->find()->where(['name' => $name])->select(['id'])->firstOrFail();
        return $branch->id;
    }

    public static function getMemberRoleId(int $memberId, int $roleId): ?int
    {
        $memberRolesTable = TableRegistry::getTableLocator()->get('MemberRoles');
        $memberRole = $memberRolesTable->find()
            ->where(['member_id' => $memberId, 'role_id' => $roleId])
            ->first(); // Use first() instead of firstOrFail() as it might not exist yet if created in the same seed run
        return $memberRole ? $memberRole->id : null;
    }

    public static function getDomainId(string $name): int
    {
        $domainsTable = TableRegistry::getTableLocator()->get('Awards.Domains');
        $domain = $domainsTable->find()->where(['name' => $name])->firstOrFail();
        return $domain->id;
    }

    public static function getDepartmentIdByName(string $name): int
    {
        $departmentsTable = \Cake\ORM\TableRegistry::getTableLocator()->get('Officers.Departments');
        $department = $departmentsTable->find()->where(['name' => $name])->firstOrFail();
        return $department->id;
    }

    public static function getOfficeIdByName(string $name): int
    {
        $officesTable = \Cake\ORM\TableRegistry::getTableLocator()->get('Officers.Offices');
        $office = $officesTable->find()->where(['name' => $name])->firstOrFail();
        return $office->id;
    }

    public static function getMemberRoleByMemberAndRoleName(int $memberId, string $roleName): ?int
    {
        $rolesTable = TableRegistry::getTableLocator()->get('Roles');
        $role = $rolesTable->find()->where(['name' => $roleName])->firstOrFail();
        return self::getMemberRoleId($memberId, $role->id);
    }
}