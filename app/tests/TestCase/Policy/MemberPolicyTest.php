<?php
declare(strict_types=1);

namespace App\Test\TestCase\Policy;

use App\Policy\MemberPolicy;
use App\Test\TestCase\BaseTestCase;
use Cake\Cache\Cache;
use Cake\I18n\DateTime;

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
        Cache::clearGroup('security');
    }

    protected function tearDown(): void
    {
        Cache::clearGroup('security');
        parent::tearDown();
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
    // canImportExpirationDates
    // -------------------------------------------------------

    public function testCanImportExpirationDatesRequiresPolicy(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $target = $this->loadMember(self::TEST_MEMBER_BRYCE_ID);

        $beforeResult = $this->policy->before($agatha, $target, 'importExpirationDates');
        $this->assertNull($beforeResult);
        $this->assertFalse($this->policy->canImportExpirationDates($agatha, $target));
    }

    public function testKingdomSeneschalCanImportWithNarrowPermission(): void
    {
        $this->grantNarrowImportPermissionToGreaterOfficers();
        $eirik = $this->loadMember(self::TEST_MEMBER_EIRIK_ID);

        $this->assertTrue(
            $this->policy->canImportExpirationDates($eirik, $this->Members->newEmptyEntity()),
        );
    }

    public function testNonSeneschalGreaterOfficerCannotImportWithNarrowPermission(): void
    {
        $this->grantNarrowImportPermissionToGreaterOfficers();
        $now = DateTime::now();
        $officer = $this->getTableLocator()->get('Officers.Officers')->find()
            ->innerJoinWith('Offices')
            ->select(['member_id'])
            ->where([
                'Offices.name' => 'Kingdom MoAS',
                'Officers.status' => 'Current',
                'Officers.start_on <=' => $now,
                'Officers.expires_on >=' => $now,
                'Officers.revoker_id IS' => null,
            ])
            ->firstOrFail();
        $greaterOfficer = $this->loadMember((int)$officer->member_id);

        $this->assertFalse(
            $this->policy->canImportExpirationDates($greaterOfficer, $this->Members->newEmptyEntity()),
        );
    }

    public function testSiteSecretaryCannotImportWithLegacyManageMembersGrant(): void
    {
        $roles = $this->getTableLocator()->get('Roles');
        $siteSecretary = $roles->find()->where(['name' => 'Site Secretary'])->firstOrFail();
        $memberRoles = $this->getTableLocator()->get('MemberRoles');
        $memberRoles->saveOrFail($memberRoles->newEntity([
            'member_id' => self::TEST_MEMBER_AGATHA_ID,
            'role_id' => $siteSecretary->id,
            'branch_id' => null,
            'start_on' => '2020-01-01',
            'expires_on' => '2100-01-01',
            'approver_id' => self::ADMIN_MEMBER_ID,
        ]));
        Cache::clearGroup('security');
        $siteSecretaryMember = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);

        $this->assertFalse(
            $this->policy->canImportExpirationDates(
                $siteSecretaryMember,
                $this->Members->newEmptyEntity(),
            ),
        );
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

    private function grantNarrowImportPermissionToGreaterOfficers(): void
    {
        $permissions = $this->getTableLocator()->get('Permissions');
        $permission = $permissions->find()
            ->where(['name' => 'Can Import Member Data'])
            ->first();
        if ($permission === null) {
            $permission = $permissions->newEntity([
                'name' => 'Can Import Member Data',
                'require_active_membership' => true,
                'require_active_background_check' => false,
                'require_min_age' => 0,
                'is_system' => true,
                'is_super_user' => false,
                'requires_warrant' => true,
                'scoping_rule' => 'Global',
            ]);
            $permissions->saveOrFail($permission);
        }

        $permissionPolicies = $this->getTableLocator()->get('PermissionPolicies');
        $policyMapping = $permissionPolicies->find()
            ->where([
                'permission_id' => $permission->id,
                'policy_class' => MemberPolicy::class,
                'policy_method' => 'canImportExpirationDates',
            ])
            ->first();
        if ($policyMapping === null) {
            $permissionPolicies->saveOrFail($permissionPolicies->newEntity([
                'permission_id' => $permission->id,
                'policy_class' => MemberPolicy::class,
                'policy_method' => 'canImportExpirationDates',
            ]));
        }

        $role = $this->getTableLocator()->get('Roles')->find()
            ->where(['name' => 'Greater Officer of State'])
            ->firstOrFail();
        $rolesPermissions = $this->getTableLocator()->get('RolesPermissions');
        $roleGrant = $rolesPermissions->find()
            ->where([
                'role_id' => $role->id,
                'permission_id' => $permission->id,
            ])
            ->first();
        if ($roleGrant === null) {
            $rolesPermissions->saveOrFail($rolesPermissions->newEntity([
                'role_id' => $role->id,
                'permission_id' => $permission->id,
                'created_by' => self::ADMIN_MEMBER_ID,
            ]));
        }

        Cache::clearGroup('security');
    }
}
