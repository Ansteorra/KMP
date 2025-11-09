<?php

declare(strict_types=1);

namespace App\Test\TestCase\KMP;

use App\KMP\PermissionsLoader;
use App\Model\Entity\Permission;
use App\Test\TestCase\BaseTestCase;
use Cake\ORM\TableRegistry;

class PermissionsLoaderTest extends BaseTestCase
{
    /** @var \Cake\ORM\Table\MembersTable */
    protected $Members;
    /** @var \Cake\ORM\Table\PermissionsTable */
    protected $Permissions;
    /** @var \Cake\ORM\Table\RolesTable */
    protected $Roles;
    /** @var \Cake\ORM\Table\MemberRolesTable */
    protected $MemberRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->Members = $this->getTableLocator()->get('Members');
        $this->Permissions = $this->getTableLocator()->get('Permissions');
        $this->Roles = $this->getTableLocator()->get('Roles');
        $this->MemberRoles = $this->getTableLocator()->get('MemberRoles');
    }

    public function testSuperUserPermissionGlobalScope(): void
    {
        $permissions = PermissionsLoader::getPermissions(self::ADMIN_MEMBER_ID); // Seed admin
        $super = null;
        foreach ($permissions as $perm) {
            if ($perm->is_super_user) {
                $super = $perm;
                break;
            }
        }
        $this->assertNotNull($super, 'Admin should have a super user permission');
        $this->assertSame(Permission::SCOPE_GLOBAL, $super->scoping_rule);
        $this->assertNull($super->branch_ids, 'Global scope should have null branch_ids');
    }

    public function testCacheReturnsSameInstanceStructure(): void
    {
        $first = PermissionsLoader::getPermissions(self::ADMIN_MEMBER_ID);
        $second = PermissionsLoader::getPermissions(self::ADMIN_MEMBER_ID); // Should be cached
        // Compare permission id lists and one sample object fields
        $this->assertSame(array_keys($first), array_keys($second));
        $sampleId = array_key_first($first);
        $this->assertEquals($first[$sampleId]->scoping_rule, $second[$sampleId]->scoping_rule);
    }

    public function testBranchScopedPermissionLoadsForBryce(): void
    {
        // Bryce has Regional Officer Management role at Barony of Stargate (branch 39)
        // This role includes "Branch and Children" scoped permissions
        $permissions = PermissionsLoader::getPermissions(self::TEST_MEMBER_BRYCE_ID);
        
        $hasHierarchicalPerm = false;
        foreach ($permissions as $perm) {
            if ($perm->scoping_rule === Permission::SCOPE_BRANCH_AND_CHILDREN) {
                $hasHierarchicalPerm = true;
                $this->assertIsArray($perm->branch_ids, 'Branch and Children should have array of branch_ids');
                $this->assertContains(self::TEST_BRANCH_STARGATE_ID, $perm->branch_ids, 'Should include Stargate branch');
                break;
            }
        }
        $this->assertTrue($hasHierarchicalPerm, 'Bryce should have at least one Branch and Children scoped permission');
    }

    public function testDevonHasMultipleBranchScopes(): void
    {
        // Devon has Regional Officer Management in Central (12) and Southern (13) regions
        $permissions = PermissionsLoader::getPermissions(self::TEST_MEMBER_DEVON_ID);
        
        $foundHierarchical = false;
        foreach ($permissions as $perm) {
            if ($perm->scoping_rule === Permission::SCOPE_BRANCH_AND_CHILDREN) {
                $foundHierarchical = true;
                // Should have branches from both regions merged
                $this->assertIsArray($perm->branch_ids);
                $this->assertGreaterThan(1, count($perm->branch_ids), 'Devon should have multiple branches from multiple regions');
                break;
            }
        }
        $this->assertTrue($foundHierarchical, 'Devon should have hierarchical permissions from multiple regions');
    }

    public function testDifferentMembersHaveDifferentPermissionSets(): void
    {
        $adminPerms = PermissionsLoader::getPermissions(self::ADMIN_MEMBER_ID);
        $testMemberPerms = PermissionsLoader::getPermissions(self::TEST_MEMBER_AGATHA_ID);
        
        // Admin should have super user permission
        $adminHasSuperUser = false;
        foreach ($adminPerms as $perm) {
            if ($perm->is_super_user) {
                $adminHasSuperUser = true;
                break;
            }
        }
        $this->assertTrue($adminHasSuperUser, 'Admin should have super user permission');
        
        // Test member should NOT have super user permission
        $testHasSuperUser = false;
        foreach ($testMemberPerms as $perm) {
            if ($perm->is_super_user) {
                $testHasSuperUser = true;
                break;
            }
        }
        $this->assertFalse($testHasSuperUser, 'Regular member should not have super user permission');
    }

    public function testPoliciesExtractionMergesScopes(): void
    {
        $policies = PermissionsLoader::getPolicies(self::ADMIN_MEMBER_ID);
        // Pick first policy class and method
        $firstClass = array_key_first($policies);
        if (!$firstClass) {
            $this->markTestSkipped('No policies defined for admin in seed');
        }
        $firstMethod = array_key_first($policies[$firstClass]);
        $policyObj = $policies[$firstClass][$firstMethod];
        $this->assertObjectHasProperty('scoping_rule', $policyObj);
        $this->assertObjectHasProperty('branch_ids', $policyObj);
    }

    public function testMembersWithPermissionQueryGlobal(): void
    {
        $globalPerm = $this->Permissions->find()->where(['is_super_user' => true])->first();
        if (!$globalPerm) {
            $this->markTestSkipped('No super user permission found');
        }
        $query = PermissionsLoader::getMembersWithPermissionsQuery($globalPerm->id, self::KINGDOM_BRANCH_ID);
        $this->assertGreaterThan(0, $query->count(), 'Global permission should return members regardless of branch');
    }

    public function testMembersWithBranchScopedPermissionQuery(): void
    {
        // Find "Manage Officers And Deputies Under Me" - a Branch and Children permission
        $branchPerm = $this->Permissions->find()
            ->where([
                'scoping_rule' => Permission::SCOPE_BRANCH_AND_CHILDREN,
                'name' => 'Manage Officers And Deputies Under Me'
            ])
            ->first();
        
        if (!$branchPerm) {
            $this->markTestSkipped('Branch-scoped permission not found');
        }

        // Query for members with this permission in Southern Region
        // Devon has Regional Officer Management role at Southern Region (13)
        $query = PermissionsLoader::getMembersWithPermissionsQuery(
            $branchPerm->id,
            self::TEST_BRANCH_SOUTHERN_REGION_ID
        );
        
        $count = $query->count();
        $this->assertGreaterThan(0, $count, 'Should find members with branch-scoped permission in Southern Region');
        
        // Verify the query structure returns member entities
        $members = $query->limit(5)->all();
        $this->assertNotEmpty($members, 'Query should return member entities');
    }
}
