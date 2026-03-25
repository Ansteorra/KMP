<?php

declare(strict_types=1);

namespace App\Test\TestCase\Policy;

use App\Policy\MemberPolicy;
use App\Test\TestCase\BaseTestCase;

class MemberPolicyTest extends BaseTestCase
{
    protected $Members;
    protected MemberPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
        $this->Members = $this->getTableLocator()->get('Members');
        $this->policy = new MemberPolicy();
    }

    protected function loadMember(int $id)
    {
        $member = $this->Members->get($id);
        $member->getPermissions();

        return $member;
    }

    // -------------------------------------------------------
    // Super user bypass via before()
    // -------------------------------------------------------

    public function testSuperUserCanDoEverything(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $target = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);

        $actions = [
            'viewPii',
            'profile',
            'submitScaMemberInfo',
            'sendMobileCardEmail',
            'viewAdditionalInformation',
            'viewCardJson',
            'importExpirationDates',
            'verifyMembership',
            'verifyQueue',
            'editAdditionalInfo',
            'delete',
        ];
        foreach ($actions as $action) {
            $result = $this->policy->before($admin, $target, $action);
            $this->assertTrue($result, "Super user before() should return true for '$action'");
        }
    }

    // -------------------------------------------------------
    // canProfile — always true
    // -------------------------------------------------------

    public function testCanProfileAlwaysReturnsTrue(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $otherMember = $this->loadMember(self::TEST_MEMBER_BRYCE_ID);

        $this->assertTrue($this->policy->canProfile($agatha, $otherMember));
        $this->assertTrue($this->policy->canProfile($agatha, $agatha));
    }

    // -------------------------------------------------------
    // canViewPii
    // -------------------------------------------------------

    public function testCanViewPiiForOwnProfile(): void
    {
        $bryce = $this->loadMember(self::TEST_MEMBER_BRYCE_ID);

        $this->assertTrue($this->policy->canViewPii($bryce, $bryce));
    }

    public function testCannotViewPiiForOtherMember(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $bryce = $this->loadMember(self::TEST_MEMBER_BRYCE_ID);

        $beforeResult = $this->policy->before($agatha, $bryce, 'viewPii');
        $this->assertNull($beforeResult);
        $this->assertFalse($this->policy->canViewPii($agatha, $bryce));
    }

    // -------------------------------------------------------
    // canSubmitScaMemberInfo
    // -------------------------------------------------------

    public function testCanSubmitScaMemberInfoForOwnProfile(): void
    {
        $bryce = $this->loadMember(self::TEST_MEMBER_BRYCE_ID);

        $this->assertTrue($this->policy->canSubmitScaMemberInfo($bryce, $bryce));
    }

    public function testCannotSubmitScaMemberInfoForOtherMember(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $bryce = $this->loadMember(self::TEST_MEMBER_BRYCE_ID);

        $beforeResult = $this->policy->before($agatha, $bryce, 'submitScaMemberInfo');
        $this->assertNull($beforeResult);
        $this->assertFalse($this->policy->canSubmitScaMemberInfo($agatha, $bryce));
    }

    // -------------------------------------------------------
    // canSendMobileCardEmail
    // -------------------------------------------------------

    public function testCanSendMobileCardEmailForOwnProfile(): void
    {
        $bryce = $this->loadMember(self::TEST_MEMBER_BRYCE_ID);

        $this->assertTrue($this->policy->canSendMobileCardEmail($bryce, $bryce));
    }

    // -------------------------------------------------------
    // canViewAdditionalInformation
    // -------------------------------------------------------

    public function testCanViewAdditionalInformationForOwnProfile(): void
    {
        $bryce = $this->loadMember(self::TEST_MEMBER_BRYCE_ID);

        $this->assertTrue($this->policy->canViewAdditionalInformation($bryce, $bryce));
    }

    // -------------------------------------------------------
    // canViewCardJson
    // -------------------------------------------------------

    public function testCanViewCardJsonForOwnProfile(): void
    {
        $bryce = $this->loadMember(self::TEST_MEMBER_BRYCE_ID);

        $this->assertTrue($this->policy->canViewCardJson($bryce, $bryce));
    }

    // -------------------------------------------------------
    // canImportExpirationDates — _hasPolicy only
    // -------------------------------------------------------

    public function testCanImportExpirationDatesRequiresPolicy(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $target = $this->loadMember(self::TEST_MEMBER_BRYCE_ID);

        $beforeResult = $this->policy->before($agatha, $target, 'importExpirationDates');
        $this->assertNull($beforeResult);
        $this->assertFalse($this->policy->canImportExpirationDates($agatha, $target));
    }

    // -------------------------------------------------------
    // canVerifyMembership — _hasPolicy only
    // -------------------------------------------------------

    public function testCanVerifyMembershipRequiresPolicy(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $target = $this->loadMember(self::TEST_MEMBER_BRYCE_ID);

        $beforeResult = $this->policy->before($agatha, $target, 'verifyMembership');
        $this->assertNull($beforeResult);
        $this->assertFalse($this->policy->canVerifyMembership($agatha, $target));
    }

    // -------------------------------------------------------
    // canVerifyQueue — _hasPolicy only
    // -------------------------------------------------------

    public function testCanVerifyQueueRequiresPolicy(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $target = $this->loadMember(self::TEST_MEMBER_BRYCE_ID);

        $beforeResult = $this->policy->before($agatha, $target, 'verifyQueue');
        $this->assertNull($beforeResult);
        $this->assertFalse($this->policy->canVerifyQueue($agatha, $target));
    }

    // -------------------------------------------------------
    // canEditAdditionalInfo
    // -------------------------------------------------------

    public function testCanEditAdditionalInfoForOwnProfile(): void
    {
        $bryce = $this->loadMember(self::TEST_MEMBER_BRYCE_ID);

        $this->assertTrue($this->policy->canEditAdditionalInfo($bryce, $bryce));
    }

    public function testCannotEditAdditionalInfoForOtherMember(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $bryce = $this->loadMember(self::TEST_MEMBER_BRYCE_ID);

        $beforeResult = $this->policy->before($agatha, $bryce, 'editAdditionalInfo');
        $this->assertNull($beforeResult);
        $this->assertFalse($this->policy->canEditAdditionalInfo($agatha, $bryce));
    }

    // -------------------------------------------------------
    // canDelete — always false for non-super-user
    // -------------------------------------------------------

    public function testDeleteAlwaysReturnsFalseForNonSuperUser(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $target = $this->loadMember(self::TEST_MEMBER_BRYCE_ID);

        $beforeResult = $this->policy->before($agatha, $target, 'delete');
        $this->assertNull($beforeResult);
        $this->assertFalse($this->policy->canDelete($agatha, $target));

        // Even self-management does not allow delete
        $this->assertFalse($this->policy->canDelete($agatha, $agatha));
    }
}
