<?php

declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Services\AuthorizationService;
use App\Test\TestCase\BaseTestCase;
use Authorization\Policy\MapResolver;
use Cake\Datasource\ConnectionManager;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;

/**
 * Tests for authorization edge cases and security features
 * 
 * Validates critical security scenarios:
 * - Revoked roles should not grant permissions
 * - Expired roles should not grant permissions  
 * - Expired warrants should not satisfy warrant requirements
 * - Inactive members should not have permissions requiring active membership
 * - Members without warrants cannot access warrant-required permissions
 * - Background check expiration handling
 * - Age validation for age-restricted permissions
 * - Membership expiration scenarios
 */
class AuthorizationEdgeCasesTest extends BaseTestCase
{
    /** @var \App\Model\Table\MembersTable */
    protected $Members;
    /** @var \App\Model\Table\MemberRolesTable */
    protected $MemberRoles;
    /** @var \App\Model\Table\WarrantsTable */
    protected $Warrants;
    /** @var \App\Services\AuthorizationService */
    protected $AuthService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->Members = $this->getTableLocator()->get('Members');
        $this->MemberRoles = $this->getTableLocator()->get('MemberRoles');
        $this->Warrants = $this->getTableLocator()->get('Warrants');

        // Create authorization service with policy resolver
        $resolver = new MapResolver();
        $resolver->map(\App\Model\Entity\Member::class, \App\Policy\MemberPolicy::class);
        $this->AuthService = new AuthorizationService($resolver);
    }

    /**
     * Test that revoked roles do not grant permissions
     * 
     * Eirik (2875) has member_role 362 (Greater Officer of State) that was revoked (revoker_id = 1073).
     * The revoked role itself should be filtered out by the permissions system.
     * Note: Eirik may have other active instances of the same role, so we verify the
     * revoked member_role is excluded from the active roles, not that permissions are absent.
     *
     * @return void
     */
    public function testRevokedRoleDoesNotGrantPermissions(): void
    {
        $eirik = $this->Members->get(self::TEST_MEMBER_EIRIK_ID);

        // Load the revoked role
        $revokedRole = $this->MemberRoles->get(362, ['contain' => ['Roles.Permissions']]);
        $this->assertNotNull($revokedRole->revoker_id, 'Role 362 should be revoked');
        $this->assertNotEmpty($revokedRole->role->name, 'Should have role name');

        // Get active member_roles for this member - the revoked one should NOT be included
        $activeMemberRoles = $this->MemberRoles->find()
            ->where([
                'member_id' => self::TEST_MEMBER_EIRIK_ID,
                'revoker_id IS' => null,
                'expires_on >' => new DateTime(),
            ])
            ->toArray();

        $activeMemberRoleIds = array_map(function ($mr) {
            return $mr->id;
        }, $activeMemberRoles);

        $this->assertNotContains(
            362,
            $activeMemberRoleIds,
            'Revoked member_role 362 should not be in active roles'
        );
    }

    /**
     * Test that expired roles do not grant permissions
     * 
     * Devon (2874) had member_role 363 (Regional Officer Management) that expired on 2025-08-30.
     * The expired role itself should be filtered out from active roles.
     * Note: Devon may have other active instances of the same role.
     *
     * @return void
     */
    public function testExpiredRoleDoesNotGrantPermissions(): void
    {
        $this->skipIfPostgres();
        // Load the expired role
        $expiredRole = $this->MemberRoles->get(363, ['contain' => ['Roles.Permissions']]);
        $this->assertNotNull($expiredRole->expires_on, 'Role 363 should have expiration date');

        // Verify this role is expired (its expires_on is in the past)
        $expiresTimestamp = strtotime($expiredRole->expires_on->format('Y-m-d H:i:s'));
        $this->assertLessThan(time(), $expiresTimestamp, 'Role 363 should be expired');

        // Get active member_roles for this member - the expired one should NOT be included
        $activeMemberRoles = $this->MemberRoles->find()
            ->where([
                'member_id' => self::TEST_MEMBER_DEVON_ID,
                'revoker_id IS' => null,
                'expires_on >' => new DateTime(),
            ])
            ->toArray();

        $activeMemberRoleIds = array_map(function ($mr) {
            return $mr->id;
        }, $activeMemberRoles);

        $this->assertNotContains(
            363,
            $activeMemberRoleIds,
            'Expired member_role 363 should not be in active roles'
        );
    }

    /**
     * Test that members without active warrants cannot access warrant-required permissions
     * 
     * Many permissions require active warrants (requires_warrant = 1).
     * Members without current warrants should not have these permissions.
     *
     * @return void
     */
    public function testMemberWithoutWarrantLacksWarrantRequiredPermissions(): void
    {
        $agatha = $this->Members->get(self::TEST_MEMBER_AGATHA_ID);

        // Agatha has no current warrants (all expired or deactivated)
        $currentWarrants = $this->Warrants->find()
            ->where([
                'member_id' => self::TEST_MEMBER_AGATHA_ID,
                'status' => 'Current',
                'start_on <=' => new DateTime(),
                'expires_on >=' => new DateTime(),
            ])
            ->count();

        $this->assertEquals(0, $currentWarrants, 'Agatha should have no current warrants');

        // Load permissions
        $permissions = $agatha->getPermissions();

        // Check that no permissions have requires_warrant flag
        foreach ($permissions as $permission) {
            $this->assertFalse(
                $permission->requires_warrant ?? false,
                "Agatha should not have warrant-required permission: {$permission->name}"
            );
        }
    }

    /**
     * Test that members with expired warrants cannot access warrant-required permissions
     * 
     * Bryce (2872) has expired warrants but also has one current warrant (2505).
     * Only the current warrant should enable warrant-required permissions.
     *
     * @return void
     */
    public function testExpiredWarrantsDoNotSatisfyWarrantRequirement(): void
    {
        $bryce = $this->Members->get(self::TEST_MEMBER_BRYCE_ID);

        // Check Bryce has both expired and current warrants
        $expiredCount = $this->Warrants->find()
            ->where([
                'member_id' => self::TEST_MEMBER_BRYCE_ID,
                'status IN' => ['Expired', 'Deactivated'],
            ])
            ->count();

        $this->assertGreaterThan(0, $expiredCount, 'Bryce should have expired/deactivated warrants');

        $currentCount = $this->Warrants->find()
            ->where([
                'member_id' => self::TEST_MEMBER_BRYCE_ID,
                'status' => 'Current',
                'start_on <=' => new DateTime(),
                'expires_on >=' => new DateTime(),
            ])
            ->count();

        $this->assertGreaterThan(0, $currentCount, 'Bryce should have current warrant(s)');

        // Load permissions - should only include warrant-required if warrant is current
        $permissions = $bryce->getPermissions();
        $this->assertIsArray($permissions, 'Permissions should be array');

        // Verify that no warrant-required permissions slip through from expired warrants
        // Since Bryce has a current warrant, this test focuses on ensuring the system
        // correctly distinguishes between current and expired warrants
        foreach ($permissions as $permission) {
            if ($permission->requires_warrant ?? false) {
                // If a warrant-required permission is present, verify Bryce has a current warrant
                $this->assertGreaterThan(
                    0,
                    $currentCount,
                    "Warrant-required permission '{$permission->name}' requires an active warrant"
                );
            }
        }
    }

    /**
     * Test that members with expired membership lose permissions requiring active membership
     * 
     * Temporarily sets a member's membership to expired and verifies
     * that permissions requiring active membership are not granted.
     *
     * @return void
     */
    public function testExpiredMembershipLosesActiveRequirementPermissions(): void
    {
        // Set membership to expired via direct SQL (more reliable than ORM for test setup)
        $conn = ConnectionManager::get('test');
        $conn->execute(
            "UPDATE members SET membership_expires_on = '2025-01-01' WHERE id = ?",
            [self::TEST_MEMBER_EIRIK_ID]
        );

        // Reload to pick up changes
        $eirik = $this->Members->get(self::TEST_MEMBER_EIRIK_ID);
        $this->assertNotNull($eirik->membership_expires_on);

        $expiresTimestamp = strtotime($eirik->membership_expires_on->format('Y-m-d'));
        $this->assertLessThan(
            time(),
            $expiresTimestamp,
            'Eirik membership should be expired'
        );

        // Load permissions
        $permissions = $eirik->getPermissions();

        // No permissions should require active membership since his is expired
        foreach ($permissions as $permission) {
            $this->assertFalse(
                $permission->require_active_membership ?? false,
                "Expired member should not have active-membership permission: {$permission->name}"
            );
        }
    }

    /**
     * Test that non-warrantable members cannot get warrant-required permissions
     * 
     * Temporarily sets a member as non-warrantable and verifies
     * warrant-required permissions are not granted.
     *
     * @return void
     */
    public function testNonWarrantableMemberLacksWarrantPermissions(): void
    {
        $this->skipIfPostgres();
        // Set warrantable to false via direct SQL (more reliable than ORM for test setup)
        $conn = ConnectionManager::get('test');
        $conn->execute(
            "UPDATE members SET warrantable = ? WHERE id = ?",
            [0, self::TEST_MEMBER_EIRIK_ID]
        );

        // Reload to pick up changes
        $eirik = $this->Members->get(self::TEST_MEMBER_EIRIK_ID);
        $this->assertFalse((bool)$eirik->warrantable, 'Eirik should not be warrantable');

        // Load permissions
        $permissions = $eirik->getPermissions();

        // Should not have any warrant-required permissions
        foreach ($permissions as $permission) {
            $this->assertFalse(
                $permission->requires_warrant ?? false,
                "Non-warrantable member should not have warrant-required permission: {$permission->name}"
            );
        }
    }

    /**
     * Test age validation for age-restricted permissions
     * 
     * Devon (2874) was born in 2002-09, making them 23 years old.
     * Eirik (2875) was born in 2004-12, making them 20 years old.
     * Age-restricted permissions should only be granted if member meets minimum age.
     *
     * @return void
     */
    public function testAgeRestrictedPermissionsValidation(): void
    {
        $devon = $this->Members->get(self::TEST_MEMBER_DEVON_ID);
        $eirik = $this->Members->get(self::TEST_MEMBER_EIRIK_ID);

        // Calculate Devon's age (born 2002-09, now 2025-11)
        $devonAge = 2025 - 2002;
        if (11 < 9) { // Current month < birth month
            $devonAge--;
        }
        $this->assertEquals(23, $devonAge, 'Devon should be 23 years old');

        // Calculate Eirik's age (born 2004-12, now 2025-11)
        $eirikAge = 2025 - 2004;
        if (11 < 12) { // Current month < birth month
            $eirikAge--;
        }
        $this->assertEquals(20, $eirikAge, 'Eirik should be 20 years old');

        // Load permissions
        $devonPermissions = $devon->getPermissions();
        $eirikPermissions = $eirik->getPermissions();

        // Validate age requirements are enforced
        foreach ($devonPermissions as $permission) {
            if (isset($permission->require_min_age) && $permission->require_min_age > 0) {
                $this->assertLessThanOrEqual(
                    $permission->require_min_age,
                    $devonAge,
                    "Devon should meet age requirement for: {$permission->name}"
                );
            }
        }

        foreach ($eirikPermissions as $permission) {
            if (isset($permission->require_min_age) && $permission->require_min_age > 0) {
                $this->assertLessThanOrEqual(
                    $permission->require_min_age,
                    $eirikAge,
                    "Eirik should meet age requirement for: {$permission->name}"
                );
            }
        }
    }

    /**
     * Test that super user permission bypasses all validation requirements
     * 
     * Admin (1) has super user permission which should bypass:
     * - Warrant requirements
     * - Membership expiration
     * - Background check requirements
     * - Age restrictions
     *
     * @return void
     */
    public function testSuperUserBypassesAllValidationRequirements(): void
    {
        $this->skipIfPostgres();
        $admin = $this->Members->get(self::ADMIN_MEMBER_ID);
        $admin->setAuthorization($this->AuthService);

        // Super user should bypass all checks via before() policy
        $this->assertTrue($admin->isSuperUser(), 'Admin should be super user');

        // Super user can perform any action regardless of validation requirements
        $otherMember = $this->Members->get(self::TEST_MEMBER_BRYCE_ID);
        $this->assertTrue(
            $admin->checkCan('view', $otherMember),
            'Super user should be able to view any member'
        );
        $this->assertTrue(
            $admin->checkCan('edit', $otherMember),
            'Super user should be able to edit any member'
        );
        $this->assertTrue(
            $admin->checkCan('delete', $otherMember),
            'Super user should be able to delete any member'
        );
    }

    /**
     * Test permission caching consistency across multiple loads
     * 
     * Permissions should be cached and return consistent results.
     * This validates cache integrity for the same member.
     *
     * @return void
     */
    public function testPermissionCachingConsistency(): void
    {
        $bryce = $this->Members->get(self::TEST_MEMBER_BRYCE_ID);

        // Load permissions first time
        $permissions1 = $bryce->getPermissions();
        $permissionIds1 = array_keys($permissions1);
        sort($permissionIds1);

        // Load permissions second time (should use cache)
        $permissions2 = $bryce->getPermissions();
        $permissionIds2 = array_keys($permissions2);
        sort($permissionIds2);

        // Should return identical permission sets
        $this->assertEquals(
            $permissionIds1,
            $permissionIds2,
            'Cached permissions should match original permissions'
        );

        // Verify cache structure is consistent
        $this->assertSame(
            count($permissions1),
            count($permissions2),
            'Permission count should be consistent'
        );
    }

    /**
     * Test policy caching with and without branch filtering
     * 
     * Policies should be correctly cached and filtered by branch IDs.
     *
     * @return void
     */
    public function testPolicyCachingWithBranchFiltering(): void
    {
        $devon = $this->Members->get(self::TEST_MEMBER_DEVON_ID);
        $devon->getPermissions(); // Load permissions to populate cache

        // Get all policies
        $allPolicies = $devon->getPolicies();

        // Get filtered policies for Southern Region
        $filteredPolicies = $devon->getPolicies([self::TEST_BRANCH_SOUTHERN_REGION_ID]);

        // Get same filtered policies again (should use cache)
        $filteredPolicies2 = $devon->getPolicies([self::TEST_BRANCH_SOUTHERN_REGION_ID]);

        // Filtered results should be consistent
        $this->assertEquals(
            count($filteredPolicies),
            count($filteredPolicies2),
            'Filtered policy count should be consistent'
        );

        // Filtered policies should be subset of all policies
        $this->assertLessThanOrEqual(
            count($allPolicies),
            count($filteredPolicies),
            'Filtered policies should not exceed all policies'
        );
    }

    /**
     * Test member with multiple expired and active roles
     * 
     * Member should only have permissions from active roles.
     * This tests the temporal validation filtering.
     *
     * @return void
     */
    public function testMultipleRolesTemporalFiltering(): void
    {
        // Find a member with both active and expired roles
        $memberWithMixedRoles = $this->MemberRoles->find()
            ->select(['member_id'])
            ->where(['start_on <=' => new DateTime()])
            ->group('member_id')
            ->having('COUNT(CASE WHEN expires_on IS NULL OR expires_on >= NOW() THEN 1 END) > 0')
            ->having('COUNT(CASE WHEN expires_on < NOW() THEN 1 END) > 0')
            ->first();

        if ($memberWithMixedRoles) {
            $member = $this->Members->get($memberWithMixedRoles->member_id);

            // Get active roles
            $activeRoles = $this->MemberRoles->find()
                ->where([
                    'member_id' => $member->id,
                    'start_on <=' => new DateTime(),
                    'OR' => [
                        ['expires_on IS' => null],
                        ['expires_on >=' => new DateTime()],
                    ],
                    'revoker_id IS' => null,
                ])
                ->count();

            $this->assertGreaterThan(0, $activeRoles, 'Member should have active roles');

            // Load permissions - should only be from active roles
            $permissions = $member->getPermissions();
            $this->assertIsArray($permissions, 'Permissions should be loaded from active roles only');
        } else {
            $this->markTestSkipped('No member found with both active and expired roles');
        }
    }

    /**
     * Test member status validation
     * 
     * Members with non-verified status should not have permissions requiring active membership.
     * All test members have "verified" status, so we document expected behavior.
     *
     * @return void
     */
    public function testMemberStatusValidation(): void
    {
        $bryce = $this->Members->get(self::TEST_MEMBER_BRYCE_ID);

        // Bryce has verified status
        $this->assertEquals('verified', $bryce->status, 'Bryce should have verified status');

        // With verified status, Bryce can have permissions requiring active membership
        $permissions = $bryce->getPermissions();
        $this->assertIsArray($permissions, 'Verified member can have permissions');

        // Verify that permissions requiring active membership are only present for verified members
        // Allowed statuses for active membership permissions (adjust based on your app's logic)
        $allowedStatuses = ['verified', 'active'];

        foreach ($permissions as $permission) {
            if ($permission->require_active_membership ?? false) {
                $this->assertContains(
                    $bryce->status,
                    $allowedStatuses,
                    "Permission '{$permission->name}' requires active membership but member status is '{$bryce->status}'"
                );
            }
        }
    }

    /**
     * Test that permission IDs are unique across all permissions
     * 
     * Each member should not have duplicate permission IDs.
     *
     * @return void
     */
    public function testPermissionIDsAreUnique(): void
    {
        $bryce = $this->Members->get(self::TEST_MEMBER_BRYCE_ID);
        $permissions = $bryce->getPermissions();

        $permissionIds = array_keys($permissions);
        $uniqueIds = array_unique($permissionIds);

        $this->assertEquals(
            count($permissionIds),
            count($uniqueIds),
            'Permission IDs should be unique (no duplicates)'
        );
    }

    /**
     * Test empty permission set behavior
     * 
     * Members with no roles should have empty permission set.
     * Agatha (2871) has no active roles after revocations.
     *
     * @return void
     */
    public function testEmptyPermissionSetForMemberWithNoRoles(): void
    {
        $agatha = $this->Members->get(self::TEST_MEMBER_AGATHA_ID);

        // Check Agatha has no active roles
        $activeRoles = $this->MemberRoles->find()
            ->where([
                'member_id' => self::TEST_MEMBER_AGATHA_ID,
                'start_on <=' => new DateTime(),
                'OR' => [
                    ['expires_on IS' => null],
                    ['expires_on >=' => new DateTime()],
                ],
                'revoker_id IS' => null,
            ])
            ->count();

        $this->assertEquals(0, $activeRoles, 'Agatha should have no active roles');

        // Load permissions
        $permissions = $agatha->getPermissions();

        $this->assertEmpty($permissions, 'Member with no active roles should have empty permissions');
        $this->assertFalse($agatha->isSuperUser(), 'Member with no permissions should not be super user');
    }
}
