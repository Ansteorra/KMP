<?php

declare(strict_types=1);

namespace App\KMP;

use ArrayAccess;

use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\Entity;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;
use Cake\ORM\Query\SelectQuery;
use App\Model\Entity\Permission;
use App\Model\Entity\Warrant;
use App\Model\Entity\Member;
use Cake\Cache\Cache;

class PermissionsLoader
{
    /**
     * Get the permissions for a member
     *
     * @param int $memberId
     * @return array
     */
    public static function getPermissions(int $memberId): array
    {
        $branchTable = TableRegistry::getTableLocator()->get(
            "Branches",
        );
        $permissionsTable = TableRegistry::getTableLocator()->get(
            "Permissions",
        );


        $query = $permissionsTable
            ->find();
        $query = self::validPermissionClauses($query)
            ->select([
                "Permissions.id",
                "Permissions.name",
                "Permissions.scoping_rule",
                "Permissions.is_super_user",
                "MemberRoles.branch_id",
            ])
            ->where(["Members.id" => $memberId])
            ->distinct()
            ->all()
            ->toArray();
        //merge permissions with the same permission id and different branch ids
        $permissions = [];
        foreach ($query as $permission) {
            $branch_id = $permission->_matchingData["MemberRoles"]->branch_id;
            if (isset($permissions[$permission->id])) {
                switch ($permission->scoping_rule) {
                    case Permission::SCOPE_GLOBAL:
                        break;
                    case Permission::SCOPE_BRANCH_ONLY:
                        $permissions[$permission->id]->branch_ids[] = $branch_id;
                        break;
                    case Permission::SCOPE_BRANCH_AND_CHILDREN:
                        $decendents = $branchTable->getAllDecendentIds($branch_id);
                        $decendents[] = $branch_id;
                        $idList = array_merge(
                            $permissions[$permission->id]->branch_ids,
                            $decendents,
                        );
                        $idList = array_unique($idList);
                        $permissions[$permission->id]->branch_ids = $idList;
                        break;
                }
            } else {
                $permissions[$permission->id] = (object)[
                    "id" => $permission->id,
                    "name" => $permission->name,
                    "scoping_rule" => $permission->scoping_rule,
                    "is_super_user" => $permission->is_super_user,
                    "branch_ids" => [],
                ];
                switch ($permission->scoping_rule) {
                    case Permission::SCOPE_GLOBAL:
                        $permissions[$permission->id]->branch_ids = null;
                        break;
                    case Permission::SCOPE_BRANCH_ONLY:
                        $permissions[$permission->id]->branch_ids = [$branch_id];
                        break;
                    case Permission::SCOPE_BRANCH_AND_CHILDREN:
                        $decendents = $branchTable->getAllDecendentIds($branch_id);
                        $decendents[] = $branch_id;
                        $permissions[$permission->id]->branch_ids = $decendents;
                        break;
                }
            }
        }
        return $permissions;
    }

    public static function getMembersWithPermissionsQuery(int $permissionId, int $branch_id): SelectQuery
    {
        $permissionsTable = TableRegistry::getTableLocator()->get(
            "Permissions",
        );
        $memberTable = TableRegistry::getTableLocator()->get(
            "Members",
        );
        $branchTable = TableRegistry::getTableLocator()->get(
            "Branches",
        );
        $permission = $permissionsTable->get($permissionId);
        $subquery = $permissionsTable
            ->find()->cache("permissions_members" . $permissionId, 'permissions');
        $subquery = self::validPermissionClauses($subquery)
            ->where(["Permissions.id" => $permissionId])
            ->select(["Members.id"])
            ->distinct();

        if ($permission->scoping_rule == Permission::SCOPE_BRANCH_ONLY) {
            $subquery = $subquery->where(["MemberRoles.branch_id" => $branch_id]);
        }
        if ($permission->scoping_rule == Permission::SCOPE_BRANCH_AND_CHILDREN) {
            $parents = $branchTable->getAllParents($branch_id);
            $parents[] = $branch_id;
            $subquery = $subquery->where(["MemberRoles.branch_id IN " => $parents]);
        }
        $query = $memberTable->find()
            ->where(["Members.id IN" => $subquery]);
        return $query;
    }

    protected static function validPermissionClauses(SelectQuery $q): SelectQuery
    {
        $now = DateTime::now();

        $warrantsTable = TableRegistry::getTableLocator()->get('Warrants');

        $warrantSubquery = $warrantsTable->find()
            ->select(['Warrants.member_role_id'])
            ->where([
                'Warrants.member_role_id = MemberRoles.id',
                'Warrants.start_on <' => $now,
                'Warrants.expires_on >' => $now,
                'Warrants.status' => Warrant::CURRENT_STATUS,
            ]);

        $q = $q->innerJoinWith("Roles.Members")
            ->where([
                "MemberRoles.start_on < " => DateTime::now(),
                "OR" => [
                    "MemberRoles.expires_on IS " => null,
                    "MemberRoles.expires_on >" => DateTime::now(),
                ],
            ])
            ->where([
                "OR" => [
                    "Permissions.require_active_membership" => false,
                    "AND" => [
                        "Members.status IN " => [
                            Member::STATUS_VERIFIED_MEMBERSHIP,
                            Member::STATUS_VERIFIED_MINOR,
                        ],
                        "Members.membership_expires_on >" => DateTime::now(),
                    ],
                ],
            ])
            ->where([
                "OR" => [
                    "Permissions.require_active_background_check" => false,
                    "Members.background_check_expires_on >" => DateTime::now(),
                ],
            ])
            ->where([
                "OR" => [
                    "Permissions.require_min_age" => 0,
                    "AND" => [
                        "Members.birth_year = " .
                            strval($now->year) .
                            " - Permissions.require_min_age",
                        "Members.birth_month <=" => $now->month,
                    ],
                    "Members.birth_year < " .
                        strval($now->year) .
                        " - Permissions.require_min_age",
                ],
            ]);
        $useWarrant = StaticHelpers::getAppSetting("KMP.RequireActiveWarrantForSecurity");
        if (strtolower($useWarrant) == 'yes') {
            $q = $q->where([
                "OR" => [
                    "Permissions.requires_warrant" => False,
                    "AND" => [
                        "Members.warrantable" => true,
                        "MemberRoles.id IN" => $warrantSubquery,
                    ],
                ],
            ]);
        }
        return $q;
    }

    public static function getApplicationPolicies(): array
    {
        // If not cached, load the policy classes.
        $paths = [];

        // App policy folder: assume app policies are in app/src/Policy
        $appPolicyPath = realpath(__DIR__ . '/../Policy');
        if ($appPolicyPath !== false) {
            $paths[] = $appPolicyPath;
        }

        // Plugin policy folders: assume plugins are in app/Plugins/*/src/Policy
        $pluginPolicyDirs = glob(__DIR__ . '/../../plugins/*/src/Policy', GLOB_ONLYDIR);
        foreach ($pluginPolicyDirs as $dir) {
            $realDir = realpath($dir);
            if ($realDir !== false) {
                $paths[] = $realDir;
            }
        }

        // Require all PHP files in each policy folder.
        foreach ($paths as $path) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path)
            );
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    require_once $file->getPathname();
                }
            }
        }

        $policyClasses = [];
        // Iterate all declared classes and pick those whose file is in one of the policy paths.
        foreach (get_declared_classes() as $class) {
            try {
                $reflector = new \ReflectionClass($class);
                $filename = $reflector->getFileName();
                if ($filename === false) {
                    continue;
                }

                foreach ($paths as $policyPath) {
                    if (strpos(realpath($filename), $policyPath) === 0) {
                        // Include all public methods (including inherited methods).
                        $methods = [];
                        foreach ($reflector->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                            // Skip methods that are not public.
                            if ($method->isPublic()) {
                                // Skip methods that are not callable.
                                if ($method->isStatic() || $method->isConstructor()) {
                                    continue;
                                }
                                //skip methods that do not start with "can"
                                $canResult = preg_match('/^can/', $method->getName());
                                if ($canResult === 0) {
                                    continue;
                                }
                                $methods[] = $method->getName();
                            }
                        }
                        //if methods is not empty, add to policyClasses
                        if (empty($methods)) {
                            continue;
                        }
                        //skip the class BasePolicy because everything inherits from it.
                        if ($class == "App\Policy\BasePolicy") {
                            continue;
                        }
                        $policyClasses[$class] = $methods;
                        break;
                    }
                }
            } catch (\ReflectionException $e) {
                // Skip classes that cannot be reflected.
                continue;
            }
        }
        return $policyClasses;
    }
}