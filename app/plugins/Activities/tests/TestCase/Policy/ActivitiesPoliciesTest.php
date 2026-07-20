<?php

declare(strict_types=1);

namespace Activities\Test\TestCase\Policy;

use Activities\Policy\ActivitiesTablePolicy;
use Activities\Policy\ActivityGroupPolicy;
use Activities\Policy\ActivityGroupsTablePolicy;
use Activities\Policy\ActivityPolicy;
use Activities\Policy\AuthorizationPolicy;
use Activities\Policy\ReportsControllerPolicy;
use App\Model\Entity\Member;
use App\Policy\BasePolicy;
use App\Test\TestCase\BaseTestCase;
use Authorization\Policy\BeforePolicyInterface;

/**
 * Combined policy tests for Activities plugin.
 *
 * Tests policy instantiation, inheritance, super-user bypass,
 * non-privileged user denial, and ownership-based access patterns.
 */
class ActivitiesPoliciesTest extends BaseTestCase
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
    // ActivitiesTablePolicy
    // =========================================================================

    public function testActivitiesTablePolicyExtendsBasePolicy(): void
    {
        $policy = new ActivitiesTablePolicy();
        $this->assertInstanceOf(BasePolicy::class, $policy);
        $this->assertInstanceOf(BeforePolicyInterface::class, $policy);
    }

    public function testActivitiesTablePolicySuperUserBypass(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $policy = new ActivitiesTablePolicy();
        $table = $this->getTableLocator()->get('Activities.Activities');
        $result = $policy->before($admin, $table, 'index');
        $this->assertTrue($result);
    }

    public function testActivitiesTablePolicyNonPrivilegedReturnsNull(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new ActivitiesTablePolicy();
        $table = $this->getTableLocator()->get('Activities.Activities');
        $result = $policy->before($user, $table, 'index');
        $this->assertNull($result);
    }

    // =========================================================================
    // ActivityGroupPolicy
    // =========================================================================

    public function testActivityGroupPolicyExtendsBasePolicy(): void
    {
        $policy = new ActivityGroupPolicy();
        $this->assertInstanceOf(BasePolicy::class, $policy);
        $this->assertInstanceOf(BeforePolicyInterface::class, $policy);
    }

    public function testActivityGroupPolicySuperUserBypass(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $policy = new ActivityGroupPolicy();
        $entity = $this->getTableLocator()->get('Activities.ActivityGroups')->newEmptyEntity();
        $result = $policy->before($admin, $entity, 'view');
        $this->assertTrue($result);
    }

    // =========================================================================
    // ActivityGroupsTablePolicy
    // =========================================================================

    public function testActivityGroupsTablePolicyExtendsBasePolicy(): void
    {
        $policy = new ActivityGroupsTablePolicy();
        $this->assertInstanceOf(BasePolicy::class, $policy);
        $this->assertInstanceOf(BeforePolicyInterface::class, $policy);
    }

    public function testActivityGroupsTablePolicySuperUserBypass(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $policy = new ActivityGroupsTablePolicy();
        $table = $this->getTableLocator()->get('Activities.ActivityGroups');
        $result = $policy->before($admin, $table, 'index');
        $this->assertTrue($result);
    }

    // =========================================================================
    // ActivityPolicy
    // =========================================================================

    public function testActivityPolicyExtendsBasePolicy(): void
    {
        $policy = new ActivityPolicy();
        $this->assertInstanceOf(BasePolicy::class, $policy);
        $this->assertInstanceOf(BeforePolicyInterface::class, $policy);
    }

    public function testActivityPolicySuperUserBypass(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $policy = new ActivityPolicy();
        $entity = $this->getTableLocator()->get('Activities.Activities')->newEmptyEntity();
        $result = $policy->before($admin, $entity, 'edit');
        $this->assertTrue($result);
    }

    // =========================================================================
    // AuthorizationPolicy
    // =========================================================================

    public function testAuthorizationPolicyExtendsBasePolicy(): void
    {
        $policy = new AuthorizationPolicy();
        $this->assertInstanceOf(BasePolicy::class, $policy);
        $this->assertInstanceOf(BeforePolicyInterface::class, $policy);
    }

    public function testAuthorizationPolicySuperUserBypass(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $policy = new AuthorizationPolicy();
        $entity = $this->getTableLocator()->get('Activities.Authorizations')->newEmptyEntity();
        $result = $policy->before($admin, $entity, 'revoke');
        $this->assertTrue($result);
    }

    public function testCanRevokeDeniedForNonPrivilegedUser(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new AuthorizationPolicy();
        $entity = $this->getTableLocator()->get('Activities.Authorizations')->newEmptyEntity();
        $entity->member_id = 99999;

        $this->assertFalse($policy->canRevoke($user, $entity));
    }

    public function testCanAddAsSelf(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new AuthorizationPolicy();
        $entity = $this->getTableLocator()->get('Activities.Authorizations')->newEmptyEntity();
        $entity->member_id = self::TEST_MEMBER_AGATHA_ID;

        $this->assertTrue($policy->canAdd($user, $entity));
    }

    public function testCanAddDeniedForOtherMemberWithoutPermission(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new AuthorizationPolicy();
        $entity = $this->getTableLocator()->get('Activities.Authorizations')->newEmptyEntity();
        $entity->member_id = 99999;

        $this->assertFalse($policy->canAdd($user, $entity));
    }

    public function testCanRenewAsSelf(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new AuthorizationPolicy();
        $entity = $this->getTableLocator()->get('Activities.Authorizations')->newEmptyEntity();
        $entity->member_id = self::TEST_MEMBER_AGATHA_ID;

        $this->assertTrue($policy->canRenew($user, $entity));
    }

    public function testCanRenewDeniedForOtherMember(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new AuthorizationPolicy();
        $entity = $this->getTableLocator()->get('Activities.Authorizations')->newEmptyEntity();
        $entity->member_id = 99999;

        $this->assertFalse($policy->canRenew($user, $entity));
    }

    public function testCanMemberAuthorizationsAsSelf(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new AuthorizationPolicy();
        $entity = $this->getTableLocator()->get('Activities.Authorizations')->newEmptyEntity();
        $entity->member_id = self::TEST_MEMBER_AGATHA_ID;

        $this->assertTrue($policy->canMemberAuthorizations($user, $entity));
    }

    public function testCanMemberAuthorizationsDeniedForOtherMember(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new AuthorizationPolicy();
        $entity = $this->getTableLocator()->get('Activities.Authorizations')->newEmptyEntity();
        $entity->member_id = 99999;

        $this->assertFalse($policy->canMemberAuthorizations($user, $entity));
    }

    public function testCanRetractAsSelf(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new AuthorizationPolicy();
        $entity = $this->getTableLocator()->get('Activities.Authorizations')->newEmptyEntity();
        $entity->member_id = self::TEST_MEMBER_AGATHA_ID;

        $this->assertTrue($policy->canRetract($user, $entity));
    }

    public function testCanRetractDeniedForOtherMember(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new AuthorizationPolicy();
        $entity = $this->getTableLocator()->get('Activities.Authorizations')->newEmptyEntity();
        $entity->member_id = 99999;

        $this->assertFalse($policy->canRetract($user, $entity));
    }

    public function testCanRetractDeniedForZeroMemberId(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new AuthorizationPolicy();
        $entity = $this->getTableLocator()->get('Activities.Authorizations')->newEmptyEntity();
        $entity->member_id = 0;

        $this->assertFalse($policy->canRetract($user, $entity));
    }

    public function testActivityAuthorizationsAsSelf(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new AuthorizationPolicy();
        $entity = $this->getTableLocator()->get('Activities.Authorizations')->newEmptyEntity();
        $entity->member_id = self::TEST_MEMBER_AGATHA_ID;

        $this->assertTrue($policy->activityAuthorizations($user, $entity));
    }

    public function testActivityAuthorizationsDeniedForOtherMember(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new AuthorizationPolicy();
        $entity = $this->getTableLocator()->get('Activities.Authorizations')->newEmptyEntity();
        $entity->member_id = 99999;

        $this->assertFalse($policy->activityAuthorizations($user, $entity));
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

    public function testCanActivityWarrantsRosterDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new ReportsControllerPolicy();
        $urlProps = ['controller' => 'Reports', 'action' => 'activityWarrantsRoster', 'plugin' => 'Activities'];

        $this->assertFalse($policy->canActivityWarrantsRoster($user, $urlProps));
    }

    public function testCanAuthorizationsDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new ReportsControllerPolicy();
        $urlProps = ['controller' => 'Reports', 'action' => 'authorizations', 'plugin' => 'Activities'];

        $this->assertFalse($policy->canAuthorizations($user, $urlProps));
    }

    public function testReportsControllerSuperUserBypass(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $policy = new ReportsControllerPolicy();
        $urlProps = ['controller' => 'Reports', 'action' => 'activityWarrantsRoster', 'plugin' => 'Activities'];
        $result = $policy->before($admin, $urlProps, 'activityWarrantsRoster');
        $this->assertTrue($result);
    }
}
