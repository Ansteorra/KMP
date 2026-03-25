<?php

declare(strict_types=1);

namespace Awards\Test\TestCase\Policy;

use Awards\Policy\AwardPolicy;
use Awards\Policy\AwardsTablePolicy;
use Awards\Policy\DomainPolicy;
use Awards\Policy\DomainsTablePolicy;
use Awards\Policy\EventPolicy;
use Awards\Policy\EventsTablePolicy;
use Awards\Policy\LevelPolicy;
use Awards\Policy\LevelsTablePolicy;
use Awards\Policy\RecommendationPolicy;
use Awards\Policy\RecommendationsStatesLogPolicy;
use Awards\Policy\RecommendationsStatesLogTablePolicy;
use Awards\Policy\RecommendationsTablePolicy;
use App\Model\Entity\Member;
use App\Policy\BasePolicy;
use App\Test\TestCase\BaseTestCase;
use Authorization\Policy\BeforePolicyInterface;

/**
 * Combined policy tests for Awards plugin.
 *
 * Tests policy instantiation, inheritance, super-user bypass,
 * non-privileged user denial, ownership-based access, and dynamic methods.
 */
class AwardsPoliciesTest extends BaseTestCase
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
    // AwardPolicy (empty — extends BasePolicy)
    // =========================================================================

    public function testAwardPolicyExtendsBasePolicy(): void
    {
        $policy = new AwardPolicy();
        $this->assertInstanceOf(BasePolicy::class, $policy);
        $this->assertInstanceOf(BeforePolicyInterface::class, $policy);
    }

    public function testAwardPolicySuperUserBypass(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $policy = new AwardPolicy();
        $entity = $this->getTableLocator()->get('Awards.Awards')->newEmptyEntity();
        $this->assertTrue($policy->before($admin, $entity, 'view'));
    }

    public function testAwardPolicyNonPrivilegedReturnsNull(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new AwardPolicy();
        $entity = $this->getTableLocator()->get('Awards.Awards')->newEmptyEntity();
        $this->assertNull($policy->before($user, $entity, 'view'));
    }

    // =========================================================================
    // AwardsTablePolicy
    // =========================================================================

    public function testAwardsTablePolicyExtendsBasePolicy(): void
    {
        $policy = new AwardsTablePolicy();
        $this->assertInstanceOf(BasePolicy::class, $policy);
        $this->assertInstanceOf(BeforePolicyInterface::class, $policy);
    }

    public function testAwardsTablePolicySuperUserBypass(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $policy = new AwardsTablePolicy();
        $table = $this->getTableLocator()->get('Awards.Awards');
        $this->assertTrue($policy->before($admin, $table, 'index'));
    }

    // =========================================================================
    // DomainPolicy (empty — extends BasePolicy)
    // =========================================================================

    public function testDomainPolicyExtendsBasePolicy(): void
    {
        $policy = new DomainPolicy();
        $this->assertInstanceOf(BasePolicy::class, $policy);
        $this->assertInstanceOf(BeforePolicyInterface::class, $policy);
    }

    public function testDomainPolicySuperUserBypass(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $policy = new DomainPolicy();
        $entity = $this->getTableLocator()->get('Awards.Domains')->newEmptyEntity();
        $this->assertTrue($policy->before($admin, $entity, 'edit'));
    }

    // =========================================================================
    // DomainsTablePolicy
    // =========================================================================

    public function testDomainsTablePolicyExtendsBasePolicy(): void
    {
        $policy = new DomainsTablePolicy();
        $this->assertInstanceOf(BasePolicy::class, $policy);
        $this->assertInstanceOf(BeforePolicyInterface::class, $policy);
    }

    public function testDomainsTablePolicySuperUserBypass(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $policy = new DomainsTablePolicy();
        $table = $this->getTableLocator()->get('Awards.Domains');
        $this->assertTrue($policy->before($admin, $table, 'index'));
    }

    // =========================================================================
    // EventPolicy
    // =========================================================================

    public function testEventPolicyExtendsBasePolicy(): void
    {
        $policy = new EventPolicy();
        $this->assertInstanceOf(BasePolicy::class, $policy);
        $this->assertInstanceOf(BeforePolicyInterface::class, $policy);
    }

    public function testEventPolicySuperUserBypass(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $policy = new EventPolicy();
        $entity = $this->getTableLocator()->get('Awards.Events')->newEmptyEntity();
        $this->assertTrue($policy->before($admin, $entity, 'allEvents'));
    }

    public function testCanAllEventsDeniedForNonPrivilegedUser(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new EventPolicy();
        $entity = $this->getTableLocator()->get('Awards.Events')->newEmptyEntity();
        $this->assertFalse($policy->canAllEvents($user, $entity));
    }

    // =========================================================================
    // EventsTablePolicy (empty — extends BasePolicy)
    // =========================================================================

    public function testEventsTablePolicyExtendsBasePolicy(): void
    {
        $policy = new EventsTablePolicy();
        $this->assertInstanceOf(BasePolicy::class, $policy);
        $this->assertInstanceOf(BeforePolicyInterface::class, $policy);
    }

    public function testEventsTablePolicySuperUserBypass(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $policy = new EventsTablePolicy();
        $table = $this->getTableLocator()->get('Awards.Events');
        $this->assertTrue($policy->before($admin, $table, 'index'));
    }

    // =========================================================================
    // LevelPolicy (empty — extends BasePolicy)
    // =========================================================================

    public function testLevelPolicyExtendsBasePolicy(): void
    {
        $policy = new LevelPolicy();
        $this->assertInstanceOf(BasePolicy::class, $policy);
        $this->assertInstanceOf(BeforePolicyInterface::class, $policy);
    }

    public function testLevelPolicySuperUserBypass(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $policy = new LevelPolicy();
        $entity = $this->getTableLocator()->get('Awards.Levels')->newEmptyEntity();
        $this->assertTrue($policy->before($admin, $entity, 'view'));
    }

    // =========================================================================
    // LevelsTablePolicy
    // =========================================================================

    public function testLevelsTablePolicyExtendsBasePolicy(): void
    {
        $policy = new LevelsTablePolicy();
        $this->assertInstanceOf(BasePolicy::class, $policy);
        $this->assertInstanceOf(BeforePolicyInterface::class, $policy);
    }

    public function testLevelsTablePolicySuperUserBypass(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $policy = new LevelsTablePolicy();
        $table = $this->getTableLocator()->get('Awards.Levels');
        $this->assertTrue($policy->before($admin, $table, 'index'));
    }

    // =========================================================================
    // RecommendationsStatesLogPolicy (empty — extends BasePolicy)
    // =========================================================================

    public function testRecommendationsStatesLogPolicyExtendsBasePolicy(): void
    {
        $policy = new RecommendationsStatesLogPolicy();
        $this->assertInstanceOf(BasePolicy::class, $policy);
        $this->assertInstanceOf(BeforePolicyInterface::class, $policy);
    }

    public function testRecommendationsStatesLogPolicySuperUserBypass(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $policy = new RecommendationsStatesLogPolicy();
        $entity = $this->getTableLocator()->get('Awards.RecommendationsStatesLog')->newEmptyEntity();
        $this->assertTrue($policy->before($admin, $entity, 'view'));
    }

    // =========================================================================
    // RecommendationsStatesLogTablePolicy (empty — extends BasePolicy)
    // =========================================================================

    public function testRecommendationsStatesLogTablePolicyExtendsBasePolicy(): void
    {
        $policy = new RecommendationsStatesLogTablePolicy();
        $this->assertInstanceOf(BasePolicy::class, $policy);
        $this->assertInstanceOf(BeforePolicyInterface::class, $policy);
    }

    public function testRecommendationsStatesLogTablePolicySuperUserBypass(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $policy = new RecommendationsStatesLogTablePolicy();
        $table = $this->getTableLocator()->get('Awards.RecommendationsStatesLog');
        $this->assertTrue($policy->before($admin, $table, 'index'));
    }

    // =========================================================================
    // RecommendationsTablePolicy
    // =========================================================================

    public function testRecommendationsTablePolicyExtendsBasePolicy(): void
    {
        $policy = new RecommendationsTablePolicy();
        $this->assertInstanceOf(BasePolicy::class, $policy);
        $this->assertInstanceOf(BeforePolicyInterface::class, $policy);
    }

    public function testRecommendationsTableCanAddAlwaysTrue(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new RecommendationsTablePolicy();
        $table = $this->getTableLocator()->get('Awards.Recommendations');

        $this->assertTrue($policy->canAdd($user, $table));
    }

    public function testRecommendationsTableCanExportDelegatesToCanIndex(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new RecommendationsTablePolicy();
        $table = $this->getTableLocator()->get('Awards.Recommendations');

        // Non-privileged user lacks canIndex, so canExport should also be false
        $this->assertFalse($policy->canExport($user, $table));
    }

    public function testRecommendationsTableCanExportSuperUser(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $policy = new RecommendationsTablePolicy();
        $table = $this->getTableLocator()->get('Awards.Recommendations');
        // Super user bypass via before()
        $this->assertTrue($policy->before($admin, $table, 'export'));
    }

    // =========================================================================
    // RecommendationPolicy
    // =========================================================================

    public function testRecommendationPolicyExtendsBasePolicy(): void
    {
        $policy = new RecommendationPolicy();
        $this->assertInstanceOf(BasePolicy::class, $policy);
        $this->assertInstanceOf(BeforePolicyInterface::class, $policy);
    }

    public function testRecommendationPolicySuperUserBypass(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $policy = new RecommendationPolicy();
        $entity = $this->getTableLocator()->get('Awards.Recommendations')->newEmptyEntity();
        $this->assertTrue($policy->before($admin, $entity, 'view'));
    }

    public function testCanViewSubmittedByMemberAsSelf(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new RecommendationPolicy();
        $entity = $this->getTableLocator()->get('Awards.Recommendations')->newEmptyEntity();
        $entity->requester_id = self::TEST_MEMBER_AGATHA_ID;

        $this->assertTrue($policy->canViewSubmittedByMember($user, $entity));
    }

    public function testCanViewSubmittedByMemberDeniedForOther(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new RecommendationPolicy();
        $entity = $this->getTableLocator()->get('Awards.Recommendations')->newEmptyEntity();
        $entity->requester_id = 99999;

        $this->assertFalse($policy->canViewSubmittedByMember($user, $entity));
    }

    public function testCanViewSubmittedForMemberDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new RecommendationPolicy();
        $entity = $this->getTableLocator()->get('Awards.Recommendations')->newEmptyEntity();
        $this->assertFalse($policy->canViewSubmittedForMember($user, $entity));
    }

    public function testCanViewEventRecommendationsDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new RecommendationPolicy();
        $entity = $this->getTableLocator()->get('Awards.Recommendations')->newEmptyEntity();
        $this->assertFalse($policy->canViewEventRecommendations($user, $entity));
    }

    public function testCanViewGatheringRecommendationsDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new RecommendationPolicy();
        $entity = $this->getTableLocator()->get('Awards.Recommendations')->newEmptyEntity();
        $this->assertFalse($policy->canViewGatheringRecommendations($user, $entity));
    }

    public function testCanExportDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new RecommendationPolicy();
        $entity = $this->getTableLocator()->get('Awards.Recommendations')->newEmptyEntity();
        $this->assertFalse($policy->canExport($user, $entity));
    }

    public function testCanUseBoardDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new RecommendationPolicy();
        $entity = $this->getTableLocator()->get('Awards.Recommendations')->newEmptyEntity();
        $this->assertFalse($policy->canUseBoard($user, $entity));
    }

    public function testCanViewHiddenDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new RecommendationPolicy();
        $entity = $this->getTableLocator()->get('Awards.Recommendations')->newEmptyEntity();
        $this->assertFalse($policy->canViewHidden($user, $entity));
    }

    public function testCanViewPrivateNotesDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new RecommendationPolicy();
        $entity = $this->getTableLocator()->get('Awards.Recommendations')->newEmptyEntity();
        $this->assertFalse($policy->canViewPrivateNotes($user, $entity));
    }

    public function testCanAddNoteDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new RecommendationPolicy();
        $entity = $this->getTableLocator()->get('Awards.Recommendations')->newEmptyEntity();
        $this->assertFalse($policy->canAddNote($user, $entity));
    }

    public function testCanUpdateStatesDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new RecommendationPolicy();
        $entity = $this->getTableLocator()->get('Awards.Recommendations')->newEmptyEntity();
        $this->assertFalse($policy->canUpdateStates($user, $entity));
    }

    public function testCanAddAlwaysTrue(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new RecommendationPolicy();
        $entity = $this->getTableLocator()->get('Awards.Recommendations')->newEmptyEntity();
        $this->assertTrue($policy->canAdd($user, $entity));
    }

    public function testDynamicCallThrowsForUnknownMethod(): void
    {
        $policy = new RecommendationPolicy();
        $this->expectException(\BadMethodCallException::class);
        $policy->nonExistentMethod();
    }

    public function testGetDynamicMethodsReturnsArray(): void
    {
        $methods = RecommendationPolicy::getDynamicMethods();
        $this->assertIsArray($methods);
        foreach ($methods as $method) {
            $this->assertStringStartsWith('canApproveLevel', $method);
        }
    }
}
