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
use Permission;
use App\Model\Entity\Warrant;
use App\Model\Entity\Member;

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
        $permissionsTable = TableRegistry::getTableLocator()->get(
            "Permissions",
        );


        $query = $permissionsTable
            ->find()->cache("permissions_" . $memberId, 'default');
        $query = self::validPermissionClauses($query)
            ->where(["Members.id" => $memberId])
            ->distinct()
            ->all()
            ->toList();
        return $query;
    }

    public static function getMembersWithPermissionsQuery(int $permissionId): SelectQuery
    {
        $permissionsTable = TableRegistry::getTableLocator()->get(
            "Permissions",
        );
        $memberTable = TableRegistry::getTableLocator()->get(
            "Members",
        );
        $subquery = $permissionsTable
            ->find()->cache("permissions_members" . $permissionId, 'default');
        $subquery = self::validPermissionClauses($subquery)
            ->where(["Permissions.id" => $permissionId])
            ->select(["Members.id"])
            ->distinct();
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
}