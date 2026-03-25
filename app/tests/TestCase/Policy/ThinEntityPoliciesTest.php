<?php

declare(strict_types=1);

namespace App\Test\TestCase\Policy;

use App\Policy\AppSettingPolicy;
use App\Policy\BackupPolicy;
use App\Policy\DocumentPolicy;
use App\Policy\GatheringActivityPolicy;
use App\Policy\GatheringTypePolicy;
use App\Policy\MemberRolePolicy;
use App\Policy\TableAdminControllerPolicy;
use App\Policy\WarrantPeriodPolicy;
use App\Test\TestCase\BaseTestCase;

/**
 * Tests for thin/marker entity policies that extend BasePolicy
 * with zero or minimal custom methods.
 */
class ThinEntityPoliciesTest extends BaseTestCase
{
    protected $Members;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
        $this->Members = $this->getTableLocator()->get('Members');
    }

    protected function loadMember(int $id): \App\Model\Entity\Member
    {
        $member = $this->Members->get($id);
        $member->getPermissions();

        return $member;
    }

    // ── AppSettingPolicy (empty) ──

    public function testAppSettingPolicySuperUserBypasses(): void
    {
        $policy = new AppSettingPolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $entity = $this->getTableLocator()->get('AppSettings')->newEmptyEntity();
        $this->assertTrue($policy->before($admin, $entity, 'view'));
    }

    public function testAppSettingPolicyNonPrivilegedDenied(): void
    {
        $policy = new AppSettingPolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getTableLocator()->get('AppSettings')->newEmptyEntity();
        $this->assertNull($policy->before($agatha, $entity, 'view'));
        $this->assertFalse($policy->canView($agatha, $entity));
    }

    // ── BackupPolicy (empty) ──

    public function testBackupPolicySuperUserBypasses(): void
    {
        $policy = new BackupPolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $entity = $this->getTableLocator()->get('Backups')->newEmptyEntity();
        $this->assertTrue($policy->before($admin, $entity, 'view'));
    }

    public function testBackupPolicyNonPrivilegedDenied(): void
    {
        $policy = new BackupPolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getTableLocator()->get('Backups')->newEmptyEntity();
        $this->assertNull($policy->before($agatha, $entity, 'view'));
        $this->assertFalse($policy->canView($agatha, $entity));
    }

    // ── DocumentPolicy (empty) ──

    public function testDocumentPolicySuperUserBypasses(): void
    {
        $policy = new DocumentPolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $entity = $this->getTableLocator()->get('Documents')->newEmptyEntity();
        $this->assertTrue($policy->before($admin, $entity, 'view'));
    }

    public function testDocumentPolicyNonPrivilegedDenied(): void
    {
        $policy = new DocumentPolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getTableLocator()->get('Documents')->newEmptyEntity();
        $this->assertNull($policy->before($agatha, $entity, 'view'));
        $this->assertFalse($policy->canView($agatha, $entity));
    }

    // ── GatheringTypePolicy (empty) ──

    public function testGatheringTypePolicySuperUserBypasses(): void
    {
        $policy = new GatheringTypePolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $entity = $this->getTableLocator()->get('GatheringTypes')->newEmptyEntity();
        $this->assertTrue($policy->before($admin, $entity, 'view'));
    }

    public function testGatheringTypePolicyNonPrivilegedDenied(): void
    {
        $policy = new GatheringTypePolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getTableLocator()->get('GatheringTypes')->newEmptyEntity();
        $this->assertNull($policy->before($agatha, $entity, 'view'));
        $this->assertFalse($policy->canView($agatha, $entity));
    }

    // ── GatheringActivityPolicy (empty) ──

    public function testGatheringActivityPolicySuperUserBypasses(): void
    {
        $policy = new GatheringActivityPolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $entity = $this->getTableLocator()->get('GatheringActivities')->newEmptyEntity();
        $this->assertTrue($policy->before($admin, $entity, 'view'));
    }

    public function testGatheringActivityPolicyNonPrivilegedDenied(): void
    {
        $policy = new GatheringActivityPolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getTableLocator()->get('GatheringActivities')->newEmptyEntity();
        $this->assertNull($policy->before($agatha, $entity, 'view'));
        $this->assertFalse($policy->canView($agatha, $entity));
    }

    // ── WarrantPeriodPolicy (empty) ──

    public function testWarrantPeriodPolicySuperUserBypasses(): void
    {
        $policy = new WarrantPeriodPolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $entity = $this->getTableLocator()->get('WarrantPeriods')->newEmptyEntity();
        $this->assertTrue($policy->before($admin, $entity, 'view'));
    }

    public function testWarrantPeriodPolicyNonPrivilegedDenied(): void
    {
        $policy = new WarrantPeriodPolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getTableLocator()->get('WarrantPeriods')->newEmptyEntity();
        $this->assertNull($policy->before($agatha, $entity, 'view'));
        $this->assertFalse($policy->canView($agatha, $entity));
    }

    // ── TableAdminControllerPolicy (empty) ──

    public function testTableAdminControllerPolicySuperUserBypasses(): void
    {
        $policy = new TableAdminControllerPolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $entity = $this->getTableLocator()->get('AppSettings')->newEmptyEntity();
        $this->assertTrue($policy->before($admin, $entity, 'view'));
    }

    public function testTableAdminControllerPolicyNonPrivilegedDenied(): void
    {
        $policy = new TableAdminControllerPolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getTableLocator()->get('AppSettings')->newEmptyEntity();
        $this->assertNull($policy->before($agatha, $entity, 'view'));
        $this->assertFalse($policy->canView($agatha, $entity));
    }

    // ── MemberRolePolicy (has canDeactivate) ──

    public function testMemberRolePolicySuperUserBypasses(): void
    {
        $policy = new MemberRolePolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $entity = $this->getTableLocator()->get('MemberRoles')->newEmptyEntity();
        $this->assertTrue($policy->before($admin, $entity, 'view'));
    }

    public function testMemberRolePolicyNonPrivilegedDenied(): void
    {
        $policy = new MemberRolePolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getTableLocator()->get('MemberRoles')->newEmptyEntity();
        $this->assertNull($policy->before($agatha, $entity, 'view'));
        $this->assertFalse($policy->canView($agatha, $entity));
    }

    public function testMemberRolePolicySuperUserCanDeactivate(): void
    {
        $policy = new MemberRolePolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $entity = $this->getTableLocator()->get('MemberRoles')->newEmptyEntity();
        $this->assertTrue($policy->canDeactivate($admin, $entity));
    }

    public function testMemberRolePolicyNonPrivilegedCannotDeactivate(): void
    {
        $policy = new MemberRolePolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getTableLocator()->get('MemberRoles')->newEmptyEntity();
        $this->assertFalse($policy->canDeactivate($agatha, $entity));
    }
}
