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
     * Enable test mode with static lookups
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
     * Set test lookup value
     */
    public static function setTestLookup(string $table, string $key, int $value): void
    {
        self::$testLookups[$table][$key] = $value;
    }

    public static function getActivityGroupId(string $name): int
    {
        $activityGroupsTable = TableRegistry::getTableLocator()->get('Activities.ActivityGroups');
        $activityGroup = $activityGroupsTable->find()->where(['name' => $name])->firstOrFail();
        return $activityGroup->id;
    }

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
