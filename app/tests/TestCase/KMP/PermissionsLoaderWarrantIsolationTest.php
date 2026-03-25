<?php
declare(strict_types=1);

namespace App\Test\TestCase\KMP;

use App\KMP\PermissionsLoader;
use App\Model\Entity\Warrant;
use App\Test\TestCase\BaseTestCase;
use Cake\Cache\Cache;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;

/**
 * Tests that warrant validation in PermissionsLoader correctly isolates
 * warrants to the specific member-role they belong to.
 *
 * Regression tests for the bug where having ANY active warrant for ANY role
 * would satisfy the warrant requirement for ALL roles.
 */
class PermissionsLoaderWarrantIsolationTest extends BaseTestCase
{
    protected $Members;
    protected $Warrants;
    protected $MemberRoles;
    protected $Permissions;
    protected $AppSettings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
        $this->Members = $this->getTableLocator()->get('Members');
        $this->Warrants = $this->getTableLocator()->get('Warrants');
        $this->MemberRoles = $this->getTableLocator()->get('MemberRoles');
        $this->Permissions = $this->getTableLocator()->get('Permissions');
        $this->AppSettings = $this->getTableLocator()->get('AppSettings');

        // Enable warrant enforcement for all tests in this class
        $this->AppSettings->setAppSetting(
            'KMP.RequireActiveWarrantForSecurity',
            'yes',
        );

        // Clear ALL caches to ensure fresh computation
        $this->clearPermissionCaches();
    }

    protected function tearDown(): void
    {
        // Restore warrant setting to default
        $this->AppSettings->setAppSetting(
            'KMP.RequireActiveWarrantForSecurity',
            'no',
        );
        $this->clearPermissionCaches();
        parent::tearDown();
    }

    /**
     * Clear all permission-related caches (APCu, permission groups, app settings).
     */
    private function clearPermissionCaches(): void
    {
        Cache::clearGroup('member_permissions');
        // Clear individual member permission cache keys
        foreach ([self::ADMIN_MEMBER_ID, self::TEST_MEMBER_EIRIK_ID, self::TEST_MEMBER_BRYCE_ID] as $mid) {
            Cache::delete('member_permissions' . $mid, 'member_permissions');
            Cache::delete('permissions_policies' . $mid, 'member_permissions');
        }
        // Clear app settings cache for warrant enforcement setting
        Cache::delete('app_setting_KMP.RequireActiveWarrantForSecurity', 'default');
    }

    /**
     * Helper: find a warrant-requiring permission that a member has via their roles.
     * Returns [permission, memberRole] or null.
     */
    private function findWarrantRequiringPermissionForMember(int $memberId): ?array
    {
        $memberRoles = $this->MemberRoles->find()
            ->where([
                'MemberRoles.member_id' => $memberId,
                'MemberRoles.revoker_id IS' => null,
            ])
            ->contain(['Roles.Permissions'])
            ->all();

        foreach ($memberRoles as $memberRole) {
            if (!$memberRole->role || empty($memberRole->role->permissions)) {
                continue;
            }
            foreach ($memberRole->role->permissions as $permission) {
                if ($permission->requires_warrant) {
                    return [$permission, $memberRole];
                }
            }
        }

        return null;
    }

    /**
     * Test: member WITH an active warrant for a role gets that role's permissions.
     */
    public function testMemberWithActiveWarrantGetsPermission(): void
    {
        // Eirik (2875) has active warrants — find a warrant-requiring permission
        $result = $this->findWarrantRequiringPermissionForMember(self::TEST_MEMBER_EIRIK_ID);
        if (!$result) {
            $this->markTestSkipped('No warrant-requiring permission found for Eirik');
        }
        [$permission, $memberRole] = $result;

        // Ensure there's an active warrant for this member_role
        $activeWarrant = $this->Warrants->find()
            ->where([
                'Warrants.member_id' => self::TEST_MEMBER_EIRIK_ID,
                'Warrants.member_role_id' => $memberRole->id,
                'Warrants.status' => Warrant::CURRENT_STATUS,
                'Warrants.start_on <' => DateTime::now(),
                'Warrants.expires_on >' => DateTime::now(),
            ])
            ->first();

        if (!$activeWarrant) {
            $this->markTestSkipped('No active warrant for Eirik\'s role — seed data may have changed');
        }

        // With warrant enforcement on, Eirik should still have this permission
        $permissions = PermissionsLoader::getPermissions(self::TEST_MEMBER_EIRIK_ID);
        $hasPermission = isset($permissions[$permission->id]);
        $this->assertTrue(
            $hasPermission,
            "Member with active warrant for role should have permission '{$permission->name}'",
        );
    }

    /**
     * REGRESSION TEST: member with warrant for Role A must NOT get permissions
     * from Role B that also requires a warrant, when Role B has no active warrant.
     *
     * This is the core bug scenario: a marshal with warrant for "Regional Officer"
     * should not be able to authorize rapier activities if their "Rapier Marshal"
     * role has no active warrant.
     */
    public function testWarrantForDifferentRoleDoesNotGrantPermission(): void
    {
        // Strategy: Find a member with an active warrant, then assign them a NEW role
        // that requires a warrant but give them NO warrant for it.
        // The member should NOT get the new role's permissions.

        $result = $this->findWarrantRequiringPermissionForMember(self::TEST_MEMBER_EIRIK_ID);
        if (!$result) {
            $this->markTestSkipped('No warrant-requiring permission found for Eirik');
        }

        // Find a DIFFERENT warrant-requiring permission that Eirik does NOT currently have
        $eirikPermIds = array_keys(PermissionsLoader::getPermissions(self::TEST_MEMBER_EIRIK_ID));

        $otherPermission = $this->Permissions->find()
            ->where([
                'requires_warrant' => true,
                'Permissions.id NOT IN' => !empty($eirikPermIds) ? $eirikPermIds : [0],
            ])
            ->first();

        if (!$otherPermission) {
            $this->markTestSkipped('No other warrant-requiring permission available');
        }

        // Find a role that grants this permission
        $rolePermLink = TableRegistry::getTableLocator()->get('RolesPermissions')
            ->find()
            ->where(['permission_id' => $otherPermission->id])
            ->first();

        if (!$rolePermLink) {
            $this->markTestSkipped('No role grants the target permission');
        }

        // Assign the role to Eirik (but do NOT create a warrant for it)
        $this->clearPermissionCaches();
        $newMemberRole = $this->MemberRoles->newEmptyEntity();
        $newMemberRole->member_id = self::TEST_MEMBER_EIRIK_ID;
        $newMemberRole->role_id = $rolePermLink->role_id;
        $newMemberRole->start_on = DateTime::now()->subDays(1);
        $newMemberRole->expires_on = DateTime::now()->addYears(1);
        $newMemberRole->approver_id = self::ADMIN_MEMBER_ID;
        $newMemberRole->branch_id = self::KINGDOM_BRANCH_ID;
        $saved = $this->MemberRoles->save($newMemberRole);
        $this->assertNotFalse($saved, 'Failed to save new member role: ' . json_encode($newMemberRole->getErrors()));

        // Clear cache after data change
        $this->clearPermissionCaches();

        // Eirik has an active warrant for his EXISTING role, but NOT for this new role.
        // With the bug fixed, the new role's permission should NOT appear.
        $permissions = PermissionsLoader::getPermissions(self::TEST_MEMBER_EIRIK_ID);
        $hasUnwarrantedPermission = isset($permissions[$otherPermission->id]);

        $this->assertFalse(
            $hasUnwarrantedPermission,
            "Member should NOT get permission '{$otherPermission->name}' from a role " .
            'that has no active warrant, even though they have warrants for other roles',
        );
    }

    /**
     * Test: expired warrant does not grant permission even if role is still active.
     */
    public function testExpiredWarrantDoesNotGrantPermission(): void
    {
        $result = $this->findWarrantRequiringPermissionForMember(self::TEST_MEMBER_EIRIK_ID);
        if (!$result) {
            $this->markTestSkipped('No warrant-requiring permission found for Eirik');
        }
        [$permission, $memberRole] = $result;

        // Expire ALL warrants for this member_role using ORM save (triggers cache invalidation)
        $warrants = $this->Warrants->find()
            ->where(['member_role_id' => $memberRole->id])
            ->all();

        foreach ($warrants as $warrant) {
            $warrant->status = 'Expired';
            $warrant->expires_on = DateTime::now()->subDays(1);
            $this->Warrants->save($warrant);
        }

        $this->clearPermissionCaches();

        $permissions = PermissionsLoader::getPermissions(self::TEST_MEMBER_EIRIK_ID);
        $hasPermission = isset($permissions[$permission->id]);

        $this->assertFalse(
            $hasPermission,
            "Member with expired warrant should NOT have permission '{$permission->name}'",
        );
    }

    /**
     * Test: getMembersWithPermissionsQuery only returns members with properly
     * warranted roles, not members who have warrants for unrelated roles.
     */
    public function testGetMembersWithPermissionRespectsWarrantIsolation(): void
    {
        // Find a warrant-requiring permission
        $warrantPerm = $this->Permissions->find()
            ->where(['requires_warrant' => true])
            ->first();

        if (!$warrantPerm) {
            $this->markTestSkipped('No warrant-requiring permission found');
        }

        // Get the list of members with this permission
        $query = PermissionsLoader::getMembersWithPermissionsQuery(
            $warrantPerm->id,
            self::KINGDOM_BRANCH_ID,
        );
        $memberIds = $query->all()->extract('id')->toArray();

        // For each returned member, verify they actually have an active warrant
        // for a role that grants this specific permission
        foreach ($memberIds as $memberId) {
            // Skip super users — they bypass normal permission checks
            if ($memberId === self::ADMIN_MEMBER_ID) {
                continue;
            }

            // Find member_roles for this member that grant this permission
            $relevantMemberRoles = $this->MemberRoles->find()
                ->innerJoinWith('Roles.Permissions', function ($q) use ($warrantPerm) {
                    return $q->where(['Permissions.id' => $warrantPerm->id]);
                })
                ->where([
                    'MemberRoles.member_id' => $memberId,
                    'MemberRoles.revoker_id IS' => null,
                ])
                ->all()
                ->extract('id')
                ->toArray();

            if (empty($relevantMemberRoles)) {
                continue; // Permission may come from super user role
            }

            // Verify at least one of these member_roles has an active warrant
            $activeWarrantCount = $this->Warrants->find()
                ->where([
                    'Warrants.member_id' => $memberId,
                    'Warrants.member_role_id IN' => $relevantMemberRoles,
                    'Warrants.status' => Warrant::CURRENT_STATUS,
                    'Warrants.start_on <' => DateTime::now(),
                    'Warrants.expires_on >' => DateTime::now(),
                ])
                ->count();

            $this->assertGreaterThan(
                0,
                $activeWarrantCount,
                "Member {$memberId} returned by getMembersWithPermissionsQuery for " .
                "'{$warrantPerm->name}' has no active warrant for the granting role",
            );
        }
    }

    /**
     * Test: when warrant enforcement is disabled, warrants are not checked.
     */
    public function testWarrantEnforcementDisabledBypassesCheck(): void
    {
        // Disable warrant enforcement
        $this->AppSettings->setAppSetting(
            'KMP.RequireActiveWarrantForSecurity',
            'no',
        );
        $this->clearPermissionCaches();

        $result = $this->findWarrantRequiringPermissionForMember(self::TEST_MEMBER_EIRIK_ID);
        if (!$result) {
            $this->markTestSkipped('No warrant-requiring permission found for Eirik');
        }
        [$permission, $memberRole] = $result;

        // Expire all warrants using ORM save
        $warrants = $this->Warrants->find()
            ->where(['member_role_id' => $memberRole->id])
            ->all();

        foreach ($warrants as $warrant) {
            $warrant->status = 'Expired';
            $warrant->expires_on = DateTime::now()->subDays(1);
            $this->Warrants->save($warrant);
        }

        $this->clearPermissionCaches();

        // With enforcement OFF, even without a warrant the permission should be granted
        $permissions = PermissionsLoader::getPermissions(self::TEST_MEMBER_EIRIK_ID);
        $hasPermission = isset($permissions[$permission->id]);

        $this->assertTrue(
            $hasPermission,
            'With warrant enforcement disabled, permission should be granted without active warrant',
        );
    }
}
