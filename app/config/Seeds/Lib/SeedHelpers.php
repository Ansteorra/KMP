<?php

declare(strict_types=1);


use Cake\ORM\TableRegistry;

class SeedHelpers
{

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
        $rolesTable = TableRegistry::getTableLocator()->get('Roles');
        $role = $rolesTable->find()->where(['name' => $name])->firstOrFail();
        return $role->id;
    }

    public static  function getPermissionId(string $name): int
    {
        $permissionsTable = TableRegistry::getTableLocator()->get('Permissions');
        $permission = $permissionsTable->find()->where(['name' => $name])->firstOrFail();
        return $permission->id;
    }

    public static  function getMemberId(string $emailOrScaName): int
    {
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
