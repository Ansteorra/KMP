<?php

declare(strict_types=1);

namespace Waivers\Test\TestCase\Policy;

use Waivers\Policy\GatheringActivityWaiverPolicy;
use Waivers\Policy\GatheringActivityWaiversTablePolicy;
use Waivers\Policy\GatheringWaiverPolicy;
use Waivers\Policy\GatheringWaiversControllerPolicy;
use Waivers\Policy\GatheringWaiversTablePolicy;
use Waivers\Policy\WaiverPolicy;
use Waivers\Policy\WaiverTypePolicy;
use Waivers\Policy\WaiverTypesTablePolicy;
use App\Model\Entity\Member;
use App\Policy\BasePolicy;
use App\Test\TestCase\BaseTestCase;
use Authorization\Policy\BeforePolicyInterface;

/**
 * Combined policy tests for Waivers plugin.
 *
 * Tests policy instantiation, inheritance, super-user bypass,
 * non-privileged user denial, and steward-based access patterns.
 */
class WaiversPoliciesTest extends BaseTestCase
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
    // WaiverPolicy (empty — extends BasePolicy)
    // =========================================================================

    public function testWaiverPolicyExtendsBasePolicy(): void
    {
        $policy = new WaiverPolicy();
        $this->assertInstanceOf(BasePolicy::class, $policy);
        $this->assertInstanceOf(BeforePolicyInterface::class, $policy);
    }

    public function testWaiverPolicySuperUserBypass(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $policy = new WaiverPolicy();
        $entity = $this->getTableLocator()->get('Waivers.WaiverTypes')->newEmptyEntity();
        $this->assertTrue($policy->before($admin, $entity, 'view'));
    }

    // =========================================================================
    // WaiverTypePolicy
    // =========================================================================

    public function testWaiverTypePolicyExtendsBasePolicy(): void
    {
        $policy = new WaiverTypePolicy();
        $this->assertInstanceOf(BasePolicy::class, $policy);
        $this->assertInstanceOf(BeforePolicyInterface::class, $policy);
    }

    public function testCanToggleActiveDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new WaiverTypePolicy();
        $entity = $this->getTableLocator()->get('Waivers.WaiverTypes')->newEmptyEntity();
        $this->assertFalse($policy->canToggleActive($user, $entity));
    }

    public function testCanDownloadTemplateDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new WaiverTypePolicy();
        $entity = $this->getTableLocator()->get('Waivers.WaiverTypes')->newEmptyEntity();
        $this->assertFalse($policy->canDownloadTemplate($user, $entity));
    }

    public function testWaiverTypeSuperUserBypass(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $policy = new WaiverTypePolicy();
        $entity = $this->getTableLocator()->get('Waivers.WaiverTypes')->newEmptyEntity();
        $this->assertTrue($policy->before($admin, $entity, 'toggleActive'));
    }

    // =========================================================================
    // WaiverTypesTablePolicy (empty — extends BasePolicy)
    // =========================================================================

    public function testWaiverTypesTablePolicyExtendsBasePolicy(): void
    {
        $policy = new WaiverTypesTablePolicy();
        $this->assertInstanceOf(BasePolicy::class, $policy);
        $this->assertInstanceOf(BeforePolicyInterface::class, $policy);
    }

    public function testWaiverTypesTablePolicySuperUserBypass(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $policy = new WaiverTypesTablePolicy();
        $table = $this->getTableLocator()->get('Waivers.WaiverTypes');
        $this->assertTrue($policy->before($admin, $table, 'index'));
    }

    // =========================================================================
    // GatheringActivityWaiverPolicy
    // =========================================================================

    public function testGatheringActivityWaiverPolicyExtendsBasePolicy(): void
    {
        $policy = new GatheringActivityWaiverPolicy();
        $this->assertInstanceOf(BasePolicy::class, $policy);
        $this->assertInstanceOf(BeforePolicyInterface::class, $policy);
    }

    public function testAvailableWaiverTypesDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new GatheringActivityWaiverPolicy();
        $entity = $this->getTableLocator()->get('Waivers.GatheringActivityWaivers')->newEmptyEntity();
        $this->assertFalse($policy->availableWaiverTypes($user, $entity));
    }

    public function testGatheringActivityWaiverSuperUserBypass(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $policy = new GatheringActivityWaiverPolicy();
        $entity = $this->getTableLocator()->get('Waivers.GatheringActivityWaivers')->newEmptyEntity();
        $this->assertTrue($policy->before($admin, $entity, 'availableWaiverTypes'));
    }

    // =========================================================================
    // GatheringActivityWaiversTablePolicy (empty — extends BasePolicy)
    // =========================================================================

    public function testGatheringActivityWaiversTablePolicyExtendsBasePolicy(): void
    {
        $policy = new GatheringActivityWaiversTablePolicy();
        $this->assertInstanceOf(BasePolicy::class, $policy);
        $this->assertInstanceOf(BeforePolicyInterface::class, $policy);
    }

    public function testGatheringActivityWaiversTableSuperUserBypass(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $policy = new GatheringActivityWaiversTablePolicy();
        $table = $this->getTableLocator()->get('Waivers.GatheringActivityWaivers');
        $this->assertTrue($policy->before($admin, $table, 'index'));
    }

    // =========================================================================
    // GatheringWaiverPolicy (complex — 9 public methods + steward helper)
    // =========================================================================

    public function testGatheringWaiverPolicyExtendsBasePolicy(): void
    {
        $policy = new GatheringWaiverPolicy();
        $this->assertInstanceOf(BasePolicy::class, $policy);
        $this->assertInstanceOf(BeforePolicyInterface::class, $policy);
    }

    public function testGatheringWaiverPolicySuperUserBypass(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $policy = new GatheringWaiverPolicy();
        $entity = $this->getTableLocator()->get('Waivers.GatheringWaivers')->newEmptyEntity();
        $this->assertTrue($policy->before($admin, $entity, 'download'));
    }

    public function testCanDownloadDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new GatheringWaiverPolicy();
        $entity = $this->getTableLocator()->get('Waivers.GatheringWaivers')->newEmptyEntity();
        $this->assertFalse($policy->canDownload($user, $entity));
    }

    public function testCanInlinePdfDelegatesToDownload(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new GatheringWaiverPolicy();
        $entity = $this->getTableLocator()->get('Waivers.GatheringWaivers')->newEmptyEntity();
        // canInlinePdf delegates to canDownload — both should return the same result
        $this->assertSame(
            $policy->canDownload($user, $entity),
            $policy->canInlinePdf($user, $entity)
        );
    }

    public function testCanPreviewDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new GatheringWaiverPolicy();
        $entity = $this->getTableLocator()->get('Waivers.GatheringWaivers')->newEmptyEntity();
        $this->assertFalse($policy->canPreview($user, $entity));
    }

    public function testCanChangeWaiverTypeDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new GatheringWaiverPolicy();
        $entity = $this->getTableLocator()->get('Waivers.GatheringWaivers')->newEmptyEntity();
        $this->assertFalse($policy->canChangeWaiverType($user, $entity));
    }

    public function testCanViewGatheringWaiversDeniedForNonSteward(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new GatheringWaiverPolicy();
        $entity = $this->getTableLocator()->get('Waivers.GatheringWaivers')->newEmptyEntity();
        // Without gathering_id and not a steward, should be denied
        $this->assertFalse($policy->canViewGatheringWaivers($user, $entity));
    }

    public function testCanNeedingWaiversDeniedForNonSteward(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new GatheringWaiverPolicy();
        $entity = $this->getTableLocator()->get('Waivers.GatheringWaivers')->newEmptyEntity();
        $this->assertFalse($policy->canNeedingWaivers($user, $entity));
    }

    public function testCanUploadWaiversDeniedForNonSteward(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new GatheringWaiverPolicy();
        $entity = $this->getTableLocator()->get('Waivers.GatheringWaivers')->newEmptyEntity();
        $this->assertFalse($policy->canUploadWaivers($user, $entity));
    }

    public function testCanCloseWaiversDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new GatheringWaiverPolicy();
        $entity = $this->getTableLocator()->get('Waivers.GatheringWaivers')->newEmptyEntity();
        $this->assertFalse($policy->canCloseWaivers($user, $entity));
    }

    public function testCanDeclineDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new GatheringWaiverPolicy();
        $entity = $this->getTableLocator()->get('Waivers.GatheringWaivers')->newEmptyEntity();
        $this->assertFalse($policy->canDecline($user, $entity));
    }

    // =========================================================================
    // GatheringWaiversControllerPolicy (complex — 8 public methods + steward)
    // =========================================================================

    public function testGatheringWaiversControllerPolicyExtendsBasePolicy(): void
    {
        $policy = new GatheringWaiversControllerPolicy();
        $this->assertInstanceOf(BasePolicy::class, $policy);
        $this->assertInstanceOf(BeforePolicyInterface::class, $policy);
    }

    public function testGatheringWaiversControllerSuperUserBypass(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $policy = new GatheringWaiversControllerPolicy();
        $urlProps = ['controller' => 'GatheringWaivers', 'action' => 'dashboard', 'plugin' => 'Waivers'];
        $this->assertTrue($policy->before($admin, $urlProps, 'dashboard'));
    }

    public function testCanNeedingWaiversControllerDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new GatheringWaiversControllerPolicy();
        $urlProps = ['controller' => 'GatheringWaivers', 'action' => 'needingWaivers', 'plugin' => 'Waivers'];
        $this->assertFalse($policy->canNeedingWaivers($user, $urlProps));
    }

    public function testCanUploadControllerDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new GatheringWaiversControllerPolicy();
        $urlProps = ['controller' => 'GatheringWaivers', 'action' => 'upload', 'plugin' => 'Waivers'];
        $this->assertFalse($policy->canUpload($user, $urlProps));
    }

    public function testCanChangeWaiverTypeControllerDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new GatheringWaiversControllerPolicy();
        $urlProps = ['controller' => 'GatheringWaivers', 'action' => 'changeWaiverType', 'plugin' => 'Waivers'];
        $this->assertFalse($policy->canChangeWaiverType($user, $urlProps));
    }

    public function testCanChangeActivitiesControllerDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new GatheringWaiversControllerPolicy();
        $urlProps = ['controller' => 'GatheringWaivers', 'action' => 'changeActivities', 'plugin' => 'Waivers'];
        $this->assertFalse($policy->canChangeActivities($user, $urlProps));
    }

    public function testCanDashboardDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new GatheringWaiversControllerPolicy();
        $urlProps = ['controller' => 'GatheringWaivers', 'action' => 'dashboard', 'plugin' => 'Waivers'];
        $this->assertFalse($policy->canDashboard($user, $urlProps));
    }

    public function testCanCalendarDataDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new GatheringWaiversControllerPolicy();
        $urlProps = ['controller' => 'GatheringWaivers', 'action' => 'calendarData', 'plugin' => 'Waivers'];
        $this->assertFalse($policy->canCalendarData($user, $urlProps));
    }

    public function testCanMobileSelectGatheringDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new GatheringWaiversControllerPolicy();
        $urlProps = ['controller' => 'GatheringWaivers', 'action' => 'mobileSelectGathering', 'plugin' => 'Waivers'];
        $this->assertFalse($policy->canMobileSelectGathering($user, $urlProps));
    }

    public function testCanMobileUploadDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new GatheringWaiversControllerPolicy();
        $urlProps = ['controller' => 'GatheringWaivers', 'action' => 'mobileUpload', 'plugin' => 'Waivers'];
        $this->assertFalse($policy->canMobileUpload($user, $urlProps));
    }

    // =========================================================================
    // GatheringWaiversTablePolicy
    // =========================================================================

    public function testGatheringWaiversTablePolicyExtendsBasePolicy(): void
    {
        $policy = new GatheringWaiversTablePolicy();
        $this->assertInstanceOf(BasePolicy::class, $policy);
        $this->assertInstanceOf(BeforePolicyInterface::class, $policy);
    }

    public function testGatheringWaiversTableSuperUserBypass(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $policy = new GatheringWaiversTablePolicy();
        $table = $this->getTableLocator()->get('Waivers.GatheringWaivers');
        $this->assertTrue($policy->before($admin, $table, 'index'));
    }

    public function testGatheringWaiversTableCanNeedingWaiversDenied(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new GatheringWaiversTablePolicy();
        $entity = $this->getTableLocator()->get('Waivers.GatheringWaivers')->newEmptyEntity();
        $this->assertFalse($policy->canNeedingWaivers($user, $entity));
    }
}
