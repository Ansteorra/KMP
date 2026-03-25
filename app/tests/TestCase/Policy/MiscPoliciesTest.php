<?php
declare(strict_types=1);

namespace App\Test\TestCase\Policy;

use App\Model\Entity\Member;
use App\Policy\ControllerActionHookPolicy;
use App\Policy\NotePolicy;
use App\Policy\NotesTablePolicy;
use App\Policy\PermissionPolicy;
use App\Policy\ReportsControllerPolicy;
use App\Policy\RolePolicy;
use App\Test\TestCase\BaseTestCase;

/**
 * Tests for miscellaneous policies:
 * RolePolicy, PermissionPolicy, ReportsControllerPolicy,
 * NotePolicy, NotesTablePolicy, ControllerActionHookPolicy
 */
class MiscPoliciesTest extends BaseTestCase
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

    // ── RolePolicy (canDeletePermission, canAddPermission) ──

    public function testRolePolicySuperUserBypasses(): void
    {
        $policy = new RolePolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $entity = $this->getTableLocator()->get('Roles')->newEmptyEntity();
        $this->assertTrue($policy->before($admin, $entity, 'view'));
    }

    public function testRolePolicyNonPrivilegedDenied(): void
    {
        $policy = new RolePolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getTableLocator()->get('Roles')->newEmptyEntity();
        $this->assertNull($policy->before($agatha, $entity, 'view'));
        $this->assertFalse($policy->canView($agatha, $entity));
    }

    public function testRolePolicySuperUserCanDeletePermission(): void
    {
        $policy = new RolePolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $entity = $this->getTableLocator()->get('Roles')->newEmptyEntity();
        $this->assertTrue($policy->canDeletePermission($admin, $entity));
    }

    public function testRolePolicyNonPrivilegedCannotDeletePermission(): void
    {
        $policy = new RolePolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getTableLocator()->get('Roles')->newEmptyEntity();
        $this->assertFalse($policy->canDeletePermission($agatha, $entity));
    }

    public function testRolePolicySuperUserCanAddPermission(): void
    {
        $policy = new RolePolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $entity = $this->getTableLocator()->get('Roles')->newEmptyEntity();
        $this->assertTrue($policy->canAddPermission($admin, $entity));
    }

    public function testRolePolicyNonPrivilegedCannotAddPermission(): void
    {
        $policy = new RolePolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getTableLocator()->get('Roles')->newEmptyEntity();
        $this->assertFalse($policy->canAddPermission($agatha, $entity));
    }

    // ── PermissionPolicy (canUpdatePolicy, canMatrix) ──

    public function testPermissionPolicySuperUserBypasses(): void
    {
        $policy = new PermissionPolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $entity = $this->getTableLocator()->get('Permissions')->newEmptyEntity();
        $this->assertTrue($policy->before($admin, $entity, 'view'));
    }

    public function testPermissionPolicyNonPrivilegedDenied(): void
    {
        $policy = new PermissionPolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getTableLocator()->get('Permissions')->newEmptyEntity();
        $this->assertNull($policy->before($agatha, $entity, 'view'));
        $this->assertFalse($policy->canView($agatha, $entity));
    }

    public function testPermissionPolicySuperUserCanUpdatePolicy(): void
    {
        $policy = new PermissionPolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $entity = $this->getTableLocator()->get('Permissions')->newEmptyEntity();
        $this->assertTrue($policy->canUpdatePolicy($admin, $entity));
    }

    public function testPermissionPolicyNonPrivilegedCannotUpdatePolicy(): void
    {
        $policy = new PermissionPolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getTableLocator()->get('Permissions')->newEmptyEntity();
        $this->assertFalse($policy->canUpdatePolicy($agatha, $entity));
    }

    public function testPermissionPolicySuperUserCanMatrix(): void
    {
        $policy = new PermissionPolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $entity = $this->getTableLocator()->get('Permissions')->newEmptyEntity();
        $this->assertTrue($policy->canMatrix($admin, $entity));
    }

    public function testPermissionPolicyNonPrivilegedCannotMatrix(): void
    {
        $policy = new PermissionPolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getTableLocator()->get('Permissions')->newEmptyEntity();
        $this->assertFalse($policy->canMatrix($agatha, $entity));
    }

    // ── ReportsControllerPolicy (canRolesList, canPermissionsWarrantsRoster) ──

    public function testReportsControllerPolicySuperUserBypasses(): void
    {
        $policy = new ReportsControllerPolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $urlProps = ['controller' => 'Reports', 'action' => 'rolesList'];
        $this->assertTrue($policy->before($admin, $urlProps, 'rolesList'));
    }

    public function testReportsControllerPolicySuperUserCanRolesList(): void
    {
        $policy = new ReportsControllerPolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $urlProps = ['controller' => 'Reports', 'action' => 'rolesList'];
        $this->assertTrue($policy->canRolesList($admin, $urlProps));
    }

    public function testReportsControllerPolicyNonPrivilegedCannotRolesList(): void
    {
        $policy = new ReportsControllerPolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $urlProps = ['controller' => 'Reports', 'action' => 'rolesList'];
        $this->assertFalse($policy->canRolesList($agatha, $urlProps));
    }

    public function testReportsControllerPolicySuperUserCanPermissionsWarrantsRoster(): void
    {
        $policy = new ReportsControllerPolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $urlProps = ['controller' => 'Reports', 'action' => 'permissionsWarrantsRoster'];
        $this->assertTrue($policy->canPermissionsWarrantsRoster($admin, $urlProps));
    }

    public function testReportsControllerPolicyNonPrivilegedCannotPermissionsWarrantsRoster(): void
    {
        $policy = new ReportsControllerPolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $urlProps = ['controller' => 'Reports', 'action' => 'permissionsWarrantsRoster'];
        $this->assertFalse($policy->canPermissionsWarrantsRoster($agatha, $urlProps));
    }

    // ── NotePolicy (canAdd delegates to $user->checkCan) ──

    public function testNotePolicySuperUserBypasses(): void
    {
        $policy = new NotePolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $entity = $this->getTableLocator()->get('Notes')->newEmptyEntity();
        $this->assertTrue($policy->before($admin, $entity, 'view'));
    }

    public function testNotePolicyNonPrivilegedDenied(): void
    {
        $policy = new NotePolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getTableLocator()->get('Notes')->newEmptyEntity();
        $this->assertNull($policy->before($agatha, $entity, 'view'));
        $this->assertFalse($policy->canView($agatha, $entity));
    }

    // ── NotesTablePolicy (does NOT extend BasePolicy; all methods return false) ──

    public function testNotesTablePolicyAlwaysDenies(): void
    {
        $policy = new NotesTablePolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $entity = $this->getTableLocator()->get('Notes')->newEmptyEntity();
        $this->assertFalse($policy->canAdd($admin, $entity));
        $this->assertFalse($policy->canEdit($admin, $entity));
        $this->assertFalse($policy->canDelete($admin, $entity));
        $this->assertFalse($policy->canView($admin, $entity));
    }

    public function testNotesTablePolicyNonPrivilegedAlsoDenied(): void
    {
        $policy = new NotesTablePolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getTableLocator()->get('Notes')->newEmptyEntity();
        $this->assertFalse($policy->canAdd($agatha, $entity));
        $this->assertFalse($policy->canEdit($agatha, $entity));
        $this->assertFalse($policy->canDelete($agatha, $entity));
        $this->assertFalse($policy->canView($agatha, $entity));
    }

    // ── ControllerActionHookPolicy (magic __call returns true for everything) ──

    public function testControllerActionHookPolicyAllowsEverything(): void
    {
        $policy = new ControllerActionHookPolicy();
        $this->assertTrue($policy->canAnything('arg1', 'arg2'));
        $this->assertTrue($policy->canSomethingElse('arg1'));
    }

    public function testControllerActionHookPolicyAllowsArbitraryMethods(): void
    {
        $policy = new ControllerActionHookPolicy();
        $this->assertTrue($policy->canView('user', 'entity'));
        $this->assertTrue($policy->canEdit('user', 'entity'));
        $this->assertTrue($policy->canDelete('user', 'entity'));
        $this->assertTrue($policy->canIndex('user'));
    }
}
