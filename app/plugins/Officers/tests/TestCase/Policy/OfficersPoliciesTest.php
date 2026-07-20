<?php

declare(strict_types=1);

namespace Officers\Test\TestCase\Policy;

use Officers\Policy\DepartmentPolicy;
use Officers\Policy\DepartmentsTablePolicy;
use Officers\Policy\OfficePolicy;
use Officers\Policy\OfficerPolicy;
use Officers\Policy\OfficersTablePolicy;
use Officers\Policy\OfficesTablePolicy;
use Officers\Policy\ReportsControllerPolicy;
use Officers\Policy\RostersControllerPolicy;
use App\Model\Entity\Member;
use App\Model\Entity\Permission;
use App\Policy\BasePolicy;
use App\Test\TestCase\BaseTestCase;
use Authorization\Policy\BeforePolicyInterface;
use Cake\Cache\Cache;
use Cake\I18n\DateTime;

/**
 * Combined policy tests for Officers plugin.
 *
 * Tests policy instantiation, inheritance, super-user bypass,
 * non-privileged user denial, and ownership-based access patterns.
 */
class OfficersPoliciesTest extends BaseTestCase
{
    protected $Members;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
        $this->Members = $this->getTableLocator()->get('Members');
    }

    protected function loadMember(int $id): Member
    {
        $member = $this->Members->get($id);
        $member->getPermissions();

        return $member;
    }

    // =========================================================================
    // DepartmentPolicy
    // =========================================================================

    public function testDepartmentPolicyExtendsBasePolicy(): void
    {
        $policy = new DepartmentPolicy();
        $this->assertInstanceOf(BasePolicy::class, $policy);
        $this->assertInstanceOf(BeforePolicyInterface::class, $policy);
    }

    public function testDepartmentPolicySuperUserBypass(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $policy = new DepartmentPolicy();
        $entity = $this->getTableLocator()->get('Officers.Departments')->newEmptyEntity();
        $this->assertTrue($policy->before($admin, $entity, 'view'));
    }

    public function testCanSeeAllDepartmentsDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new DepartmentPolicy();
        $entity = $this->getTableLocator()->get('Officers.Departments')->newEmptyEntity();
        $this->assertFalse($policy->canSeeAllDepartments($user, $entity));
    }

    // =========================================================================
    // DepartmentsTablePolicy
    // =========================================================================

    public function testDepartmentsTablePolicyExtendsBasePolicy(): void
    {
        $policy = new DepartmentsTablePolicy();
        $this->assertInstanceOf(BasePolicy::class, $policy);
        $this->assertInstanceOf(BeforePolicyInterface::class, $policy);
    }

    public function testDepartmentsTablePolicySuperUserBypass(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $policy = new DepartmentsTablePolicy();
        $table = $this->getTableLocator()->get('Officers.Departments');
        $this->assertTrue($policy->before($admin, $table, 'index'));
    }

    // =========================================================================
    // OfficePolicy
    // =========================================================================

    public function testOfficePolicyExtendsBasePolicy(): void
    {
        $policy = new OfficePolicy();
        $this->assertInstanceOf(BasePolicy::class, $policy);
        $this->assertInstanceOf(BeforePolicyInterface::class, $policy);
    }

    public function testOfficePolicySuperUserBypass(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $policy = new OfficePolicy();
        $entity = $this->getTableLocator()->get('Officers.Offices')->newEmptyEntity();
        $this->assertTrue($policy->before($admin, $entity, 'syncOfficers'));
    }

    public function testCanSyncOfficersDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new OfficePolicy();
        $entity = $this->getTableLocator()->get('Officers.Offices')->newEmptyEntity();
        $this->assertFalse($policy->canSyncOfficers($user, $entity));
    }

    // =========================================================================
    // OfficerPolicy (complex — many methods)
    // =========================================================================

    public function testOfficerPolicyExtendsBasePolicy(): void
    {
        $policy = new OfficerPolicy();
        $this->assertInstanceOf(BasePolicy::class, $policy);
        $this->assertInstanceOf(BeforePolicyInterface::class, $policy);
    }

    public function testOfficerPolicySuperUserBypass(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $policy = new OfficerPolicy();
        $entity = $this->getTableLocator()->get('Officers.Officers')->newEmptyEntity();
        $this->assertTrue($policy->before($admin, $entity, 'edit'));
    }

    public function testCanBranchOfficersAllowsAnyAuthenticated(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new OfficerPolicy();
        $entity = $this->getTableLocator()->get('Officers.Officers')->newEmptyEntity();
        $this->assertTrue($policy->canBranchOfficers($user, $entity));
    }

    public function testCanMemberOfficersAsSelf(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new OfficerPolicy();
        $entity = $this->getTableLocator()->get('Officers.Officers')->newEmptyEntity();
        $entity->member_id = self::TEST_MEMBER_AGATHA_ID;
        $this->assertTrue($policy->canMemberOfficers($user, $entity));
    }

    public function testCanMemberOfficersDeniedForOtherMember(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new OfficerPolicy();
        $entity = $this->getTableLocator()->get('Officers.Officers')->newEmptyEntity();
        $entity->member_id = 99999;
        $this->assertFalse($policy->canMemberOfficers($user, $entity));
    }

    public function testCanWorkWithAllOfficersDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new OfficerPolicy();
        $entity = $this->getTableLocator()->get('Officers.Officers')->newEmptyEntity();
        $this->assertFalse($policy->canWorkWithAllOfficers($user, $entity));
    }

    public function testCanWorkWithOfficerReportingTreeDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new OfficerPolicy();
        $entity = $this->getTableLocator()->get('Officers.Officers')->newEmptyEntity();
        $this->assertFalse($policy->canWorkWithOfficerReportingTree($user, $entity));
    }

    public function testCanWorkWithOfficerDeputiesDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new OfficerPolicy();
        $entity = $this->getTableLocator()->get('Officers.Officers')->newEmptyEntity();
        $this->assertFalse($policy->canWorkWithOfficerDeputies($user, $entity));
    }

    public function testCanWorkWithOfficerDirectReportsDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new OfficerPolicy();
        $entity = $this->getTableLocator()->get('Officers.Officers')->newEmptyEntity();
        $this->assertFalse($policy->canWorkWithOfficerDirectReports($user, $entity));
    }

    public function testCanWorkWithOfficerDeputiesHonorsMultipleDirectGrantSources(): void
    {
        $this->skipIfPostgres();
        Cache::clearGroup('member_permissions');

        $now = DateTime::now();
        $roles = $this->getTableLocator()->get('Roles');
        $permissions = $this->getTableLocator()->get('Permissions');
        $rolesPermissions = $this->getTableLocator()->get('RolesPermissions');
        $permissionPolicies = $this->getTableLocator()->get('PermissionPolicies');
        $memberRoles = $this->getTableLocator()->get('MemberRoles');
        $officers = $this->getTableLocator()->get('Officers.Officers');

        $role = $roles->newEntity([
            'name' => 'Multiple Grant Sources Test Role ' . uniqid(),
            'is_system' => false,
            'created' => $now,
            'created_by' => self::ADMIN_MEMBER_ID,
        ]);
        $roles->saveOrFail($role);

        $permission = $permissions->newEntity([
            'name' => 'Multiple Grant Sources Test Permission ' . uniqid(),
            'require_active_membership' => false,
            'require_active_background_check' => false,
            'require_min_age' => 0,
            'is_system' => false,
            'is_super_user' => false,
            'requires_warrant' => false,
            'scoping_rule' => Permission::SCOPE_BRANCH_ONLY,
            'created' => $now,
            'created_by' => self::ADMIN_MEMBER_ID,
        ]);
        $permissions->saveOrFail($permission);

        $rolesPermissions->saveOrFail($rolesPermissions->newEntity([
            'role_id' => $role->id,
            'permission_id' => $permission->id,
            'created' => $now,
            'created_by' => self::ADMIN_MEMBER_ID,
        ]));

        $permissionPolicies->saveOrFail($permissionPolicies->newEntity([
            'permission_id' => $permission->id,
            'policy_class' => OfficerPolicy::class,
            'policy_method' => 'canWorkWithOfficerDeputies',
        ]));

        $firstOfficerId = 910001;
        $secondOfficerId = 910002;
        foreach (
            [
                [$firstOfficerId, self::TEST_BRANCH_LOCAL_ID],
                [$secondOfficerId, self::TEST_BRANCH_STARGATE_ID],
            ] as [$officerId, $branchId]
        ) {
            $memberRole = $memberRoles->newEmptyEntity();
            $memberRole->member_id = self::TEST_MEMBER_AGATHA_ID;
            $memberRole->role_id = $role->id;
            $memberRole->start_on = DateTime::now()->subDays(1);
            $memberRole->expires_on = DateTime::now()->addYears(1);
            $memberRole->approver_id = self::ADMIN_MEMBER_ID;
            $memberRole->branch_id = $branchId;
            $memberRole->entity_type = 'Officers.Officers';
            $memberRole->entity_id = $officerId;
            $memberRole->created = $now;
            $memberRole->created_by = self::ADMIN_MEMBER_ID;
            $memberRoles->saveOrFail($memberRole);
        }

        Cache::clearGroup('member_permissions');

        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new OfficerPolicy();

        $firstOfficer = $officers->newEmptyEntity();
        $firstOfficer->id = $firstOfficerId;
        $firstOfficer->branch_id = self::TEST_BRANCH_LOCAL_ID;

        $secondOfficer = $officers->newEmptyEntity();
        $secondOfficer->id = $secondOfficerId;
        $secondOfficer->branch_id = self::TEST_BRANCH_STARGATE_ID;

        $this->assertTrue(
            $policy->canWorkWithOfficerDeputies($user, $firstOfficer, self::TEST_BRANCH_LOCAL_ID, true),
        );
        $this->assertTrue(
            $policy->canWorkWithOfficerDeputies($user, $secondOfficer, self::TEST_BRANCH_STARGATE_ID, true),
        );
        $this->assertFalse(
            $policy->canWorkWithOfficerDeputies($user, $firstOfficer, self::TEST_BRANCH_STARGATE_ID, true),
        );

        Cache::clearGroup('member_permissions');
    }

    public function testCanReleaseDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new OfficerPolicy();
        $entity = $this->getTableLocator()->get('Officers.Officers')->newEmptyEntity();
        $entity->branch_id = self::TEST_BRANCH_LOCAL_ID;
        $entity->office_id = 1;
        $this->assertFalse($policy->canRelease($user, $entity));
    }

    public function testCanRequestWarrantAsSelf(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new OfficerPolicy();
        $entity = $this->getTableLocator()->get('Officers.Officers')->newEmptyEntity();
        $entity->member_id = self::TEST_MEMBER_AGATHA_ID;
        $entity->branch_id = self::TEST_BRANCH_LOCAL_ID;
        $entity->office_id = 1;
        $this->assertTrue($policy->canRequestWarrant($user, $entity));
    }

    public function testCanRequestWarrantDeniedForOther(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new OfficerPolicy();
        $entity = $this->getTableLocator()->get('Officers.Officers')->newEmptyEntity();
        $entity->member_id = 99999;
        $entity->branch_id = self::TEST_BRANCH_LOCAL_ID;
        $entity->office_id = 1;
        $this->assertFalse($policy->canRequestWarrant($user, $entity));
    }

    public function testCanOfficersByWarrantStatusDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new OfficerPolicy();
        $entity = $this->getTableLocator()->get('Officers.Officers')->newEmptyEntity();
        $this->assertFalse($policy->canOfficersByWarrantStatus($user, $entity));
    }

    public function testCanEditDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new OfficerPolicy();
        $entity = $this->getTableLocator()->get('Officers.Officers')->newEmptyEntity();
        $entity->branch_id = self::TEST_BRANCH_LOCAL_ID;
        $entity->office_id = 1;
        $this->assertFalse($policy->canEdit($user, $entity));
    }

    public function testCanOfficersDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new OfficerPolicy();
        $entity = $this->getTableLocator()->get('Officers.Officers')->newEmptyEntity();
        $this->assertFalse($policy->canOfficers($user, $entity));
    }

    public function testCanAssignDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new OfficerPolicy();
        $entity = $this->getTableLocator()->get('Officers.Officers')->newEmptyEntity();
        $entity->branch_id = self::TEST_BRANCH_LOCAL_ID;
        $this->assertFalse($policy->canAssign($user, $entity));
    }

    // =========================================================================
    // OfficersTablePolicy (SKIP_BASE)
    // =========================================================================

    public function testOfficersTablePolicyExtendsBasePolicy(): void
    {
        $policy = new OfficersTablePolicy();
        $this->assertInstanceOf(BasePolicy::class, $policy);
    }

    public function testOfficersTablePolicySkipBase(): void
    {
        $this->assertSame('true', OfficersTablePolicy::SKIP_BASE);
    }

    // =========================================================================
    // OfficesTablePolicy
    // =========================================================================

    public function testOfficesTablePolicyExtendsBasePolicy(): void
    {
        $policy = new OfficesTablePolicy();
        $this->assertInstanceOf(BasePolicy::class, $policy);
        $this->assertInstanceOf(BeforePolicyInterface::class, $policy);
    }

    public function testOfficesTablePolicySuperUserBypass(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $policy = new OfficesTablePolicy();
        $table = $this->getTableLocator()->get('Officers.Offices');
        $this->assertTrue($policy->before($admin, $table, 'index'));
    }

    // =========================================================================
    // ReportsControllerPolicy
    // =========================================================================

    public function testReportsControllerPolicyExtendsBasePolicy(): void
    {
        $policy = new ReportsControllerPolicy();
        $this->assertInstanceOf(BasePolicy::class, $policy);
        $this->assertInstanceOf(BeforePolicyInterface::class, $policy);
    }

    public function testCanDepartmentOfficersRosterDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new ReportsControllerPolicy();
        $urlProps = ['controller' => 'Reports', 'action' => 'departmentOfficersRoster', 'plugin' => 'Officers'];
        $this->assertFalse($policy->canDepartmentOfficersRoster($user, $urlProps));
    }

    public function testReportsControllerSuperUserBypass(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $policy = new ReportsControllerPolicy();
        $urlProps = ['controller' => 'Reports', 'action' => 'departmentOfficersRoster', 'plugin' => 'Officers'];
        $this->assertTrue($policy->before($admin, $urlProps, 'departmentOfficersRoster'));
    }

    // =========================================================================
    // RostersControllerPolicy
    // =========================================================================

    public function testRostersControllerPolicyExtendsBasePolicy(): void
    {
        $policy = new RostersControllerPolicy();
        $this->assertInstanceOf(BasePolicy::class, $policy);
        $this->assertInstanceOf(BeforePolicyInterface::class, $policy);
    }

    public function testCanCreateRosterDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new RostersControllerPolicy();
        $urlProps = ['controller' => 'Rosters', 'action' => 'createRoster', 'plugin' => 'Officers'];
        $this->assertFalse($policy->canCreateRoster($user, $urlProps));
    }

    public function testCanAddRosterDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new RostersControllerPolicy();
        $entity = $this->getTableLocator()->get('Officers.Officers')->newEmptyEntity();
        $this->assertFalse($policy->canAdd($user, $entity));
    }

    public function testRostersControllerSuperUserBypass(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $policy = new RostersControllerPolicy();
        $urlProps = ['controller' => 'Rosters', 'action' => 'createRoster', 'plugin' => 'Officers'];
        $this->assertTrue($policy->before($admin, $urlProps, 'createRoster'));
    }
}
