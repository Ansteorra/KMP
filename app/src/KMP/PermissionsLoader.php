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
use App\Model\Entity\Member;

class PermissionsLoader
{
    public static function getPermissions(int $member_id): array
    {
        $permissionsTable = TableRegistry::getTableLocator()->get(
            "Permissions",
        );
        $now = DateTime::now();

        $query = $permissionsTable
            ->find()
            ->contain([
                "Activities" => function (SelectQuery $q) {
                    return $q->select(["Activities.name"]);
                },
            ])
            ->innerJoinWith("Roles.Members")
            ->where(["Members.id" => $member_id])
            ->where([
                "OR" => [
                    "MemberRoles.expires_on IS " => null,
                    "MemberRoles.expires_on >" => DateTime::now(),
                ],
            ])
            ->where([
                "OR" => [
                    "Permissions.require_active_membership" => false,
                    "Members.membership_expires_on >" => DateTime::now(),
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
            ])
            ->distinct()
            ->all()
            ->toList();
        return $query;
    }

    public static function getCurrentActivityApprovers(
        $activity_id,
    ) {
        $memberTable = TableRegistry::getTableLocator()->get("Members");
        $now = DateTime::now();
        $validMemberStatuses = [
            Member::STATUS_ACTIVE,
            Member::STATUS_VERIFIED_MEMBERSHIP,
            Member::STATUS_VERIFIED_MINOR,
        ];
        $query = $memberTable
            ->find()
            ->where(["status IN " => $validMemberStatuses])
            ->select(["Members.id", "Members.sca_name", "Branches.name"])
            ->contain(["Branches"])
            ->innerJoinWith("Roles.Permissions")
            ->where([
                "OR" => [
                    "Permissions.activity_id" => $activity_id,
                    "Permissions.is_super_user" => true,
                ],
            ])
            ->where([
                "OR" => [
                    "Permissions.require_active_membership" => false,
                    "Members.membership_expires_on >" => DateTime::now(),
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
            ])
            ->distinct();
        return $query;
    }
}
