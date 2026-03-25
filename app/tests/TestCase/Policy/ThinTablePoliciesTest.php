<?php
declare(strict_types=1);

namespace App\Test\TestCase\Policy;

use App\Model\Entity\Member;
use App\Policy\AppSettingsTablePolicy;
use App\Policy\BackupsTablePolicy;
use App\Policy\GatheringActivitiesTablePolicy;
use App\Policy\GatheringsTablePolicy;
use App\Policy\GatheringTypesTablePolicy;
use App\Policy\MemberRolesTablePolicy;
use App\Policy\RolesTablePolicy;
use App\Policy\ServicePrincipalsTablePolicy;
use App\Policy\WarrantPeriodsTablePolicy;
use App\Policy\WarrantsTablePolicy;
use App\Test\TestCase\BaseTestCase;
use Cake\ORM\Query\SelectQuery;

/**
 * Tests for thin/marker table policies that extend BasePolicy
 * with zero or minimal custom methods.
 */
class ThinTablePoliciesTest extends BaseTestCase
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

    // ── AppSettingsTablePolicy (empty) ──

    public function testAppSettingsTablePolicySuperUserBypasses(): void
    {
        $policy = new AppSettingsTablePolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $table = $this->getTableLocator()->get('AppSettings');
        $this->assertTrue($policy->before($admin, $table, 'index'));
    }

    public function testAppSettingsTablePolicyNonPrivilegedDenied(): void
    {
        $policy = new AppSettingsTablePolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $table = $this->getTableLocator()->get('AppSettings');
        $this->assertNull($policy->before($agatha, $table, 'index'));
        $this->assertFalse($policy->canIndex($agatha, $table));
    }

    // ── BackupsTablePolicy (empty) ──

    public function testBackupsTablePolicySuperUserBypasses(): void
    {
        $policy = new BackupsTablePolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $table = $this->getTableLocator()->get('Backups');
        $this->assertTrue($policy->before($admin, $table, 'index'));
    }

    public function testBackupsTablePolicyNonPrivilegedDenied(): void
    {
        $policy = new BackupsTablePolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $table = $this->getTableLocator()->get('Backups');
        $this->assertNull($policy->before($agatha, $table, 'index'));
        $this->assertFalse($policy->canIndex($agatha, $table));
    }

    // ── GatheringActivitiesTablePolicy (empty) ──

    public function testGatheringActivitiesTablePolicySuperUserBypasses(): void
    {
        $policy = new GatheringActivitiesTablePolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $table = $this->getTableLocator()->get('GatheringActivities');
        $this->assertTrue($policy->before($admin, $table, 'index'));
    }

    public function testGatheringActivitiesTablePolicyNonPrivilegedDenied(): void
    {
        $policy = new GatheringActivitiesTablePolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $table = $this->getTableLocator()->get('GatheringActivities');
        $this->assertNull($policy->before($agatha, $table, 'index'));
        $this->assertFalse($policy->canIndex($agatha, $table));
    }

    // ── GatheringTypesTablePolicy (empty) ──

    public function testGatheringTypesTablePolicySuperUserBypasses(): void
    {
        $policy = new GatheringTypesTablePolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $table = $this->getTableLocator()->get('GatheringTypes');
        $this->assertTrue($policy->before($admin, $table, 'index'));
    }

    public function testGatheringTypesTablePolicyNonPrivilegedDenied(): void
    {
        $policy = new GatheringTypesTablePolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $table = $this->getTableLocator()->get('GatheringTypes');
        $this->assertNull($policy->before($agatha, $table, 'index'));
        $this->assertFalse($policy->canIndex($agatha, $table));
    }

    // ── WarrantPeriodsTablePolicy (empty) ──

    public function testWarrantPeriodsTablePolicySuperUserBypasses(): void
    {
        $policy = new WarrantPeriodsTablePolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $table = $this->getTableLocator()->get('WarrantPeriods');
        $this->assertTrue($policy->before($admin, $table, 'index'));
    }

    public function testWarrantPeriodsTablePolicyNonPrivilegedDenied(): void
    {
        $policy = new WarrantPeriodsTablePolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $table = $this->getTableLocator()->get('WarrantPeriods');
        $this->assertNull($policy->before($agatha, $table, 'index'));
        $this->assertFalse($policy->canIndex($agatha, $table));
    }

    // ── GatheringsTablePolicy (overrides canIndex, canCalendar, etc.) ──

    public function testGatheringsTablePolicySuperUserBypasses(): void
    {
        $policy = new GatheringsTablePolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $table = $this->getTableLocator()->get('Gatherings');
        $this->assertTrue($policy->before($admin, $table, 'index'));
    }

    public function testGatheringsTablePolicyAnyUserCanIndex(): void
    {
        $policy = new GatheringsTablePolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $table = $this->getTableLocator()->get('Gatherings');
        // canIndex returns true for any authenticated user
        $this->assertTrue($policy->canIndex($agatha, $table));
    }

    public function testGatheringsTablePolicyAnyUserCanCalendar(): void
    {
        $policy = new GatheringsTablePolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $table = $this->getTableLocator()->get('Gatherings');
        $this->assertTrue($policy->canCalendar($agatha, $table));
    }

    public function testGatheringsTablePolicyCanCalendarGridDataDelegatesToCanCalendar(): void
    {
        $policy = new GatheringsTablePolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $table = $this->getTableLocator()->get('Gatherings');
        $this->assertTrue($policy->canCalendarGridData($agatha, $table));
    }

    public function testGatheringsTablePolicyCanGridDataDelegatesToCanIndex(): void
    {
        $policy = new GatheringsTablePolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $table = $this->getTableLocator()->get('Gatherings');
        $this->assertTrue($policy->canGridData($agatha, $table));
    }

    public function testGatheringsTablePolicyAnyUserCanMobileCalendar(): void
    {
        $policy = new GatheringsTablePolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $table = $this->getTableLocator()->get('Gatherings');
        $this->assertTrue($policy->canMobileCalendar($agatha, $table));
    }

    public function testGatheringsTablePolicyCanMobileCalendarDataDelegates(): void
    {
        $policy = new GatheringsTablePolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $table = $this->getTableLocator()->get('Gatherings');
        $this->assertTrue($policy->canMobileCalendarData($agatha, $table));
    }

    // ── WarrantsTablePolicy (has canDeclineWarrantInRoster, canDeactivate, canExport) ──

    public function testWarrantsTablePolicySuperUserBypasses(): void
    {
        $policy = new WarrantsTablePolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $table = $this->getTableLocator()->get('Warrants');
        $this->assertTrue($policy->before($admin, $table, 'index'));
    }

    public function testWarrantsTablePolicyNonPrivilegedDenied(): void
    {
        $policy = new WarrantsTablePolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $table = $this->getTableLocator()->get('Warrants');
        $this->assertNull($policy->before($agatha, $table, 'index'));
        $this->assertFalse($policy->canIndex($agatha, $table));
    }

    public function testWarrantsTablePolicySuperUserCanDeclineWarrantInRoster(): void
    {
        $policy = new WarrantsTablePolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $entity = $this->getTableLocator()->get('Warrants')->newEmptyEntity();
        $this->assertTrue($policy->canDeclineWarrantInRoster($admin, $entity));
    }

    public function testWarrantsTablePolicyNonPrivilegedCannotDeclineWarrantInRoster(): void
    {
        $policy = new WarrantsTablePolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getTableLocator()->get('Warrants')->newEmptyEntity();
        $this->assertFalse($policy->canDeclineWarrantInRoster($agatha, $entity));
    }

    public function testWarrantsTablePolicySuperUserCanDeactivate(): void
    {
        $policy = new WarrantsTablePolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $entity = $this->getTableLocator()->get('Warrants')->newEmptyEntity();
        $this->assertTrue($policy->canDeactivate($admin, $entity));
    }

    public function testWarrantsTablePolicyNonPrivilegedCannotDeactivate(): void
    {
        $policy = new WarrantsTablePolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getTableLocator()->get('Warrants')->newEmptyEntity();
        $this->assertFalse($policy->canDeactivate($agatha, $entity));
    }

    public function testWarrantsTablePolicySuperUserCanExport(): void
    {
        $policy = new WarrantsTablePolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $table = $this->getTableLocator()->get('Warrants');
        $this->assertTrue($policy->canExport($admin, $table));
    }

    public function testWarrantsTablePolicyNonPrivilegedCannotExport(): void
    {
        $policy = new WarrantsTablePolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $table = $this->getTableLocator()->get('Warrants');
        $this->assertFalse($policy->canExport($agatha, $table));
    }

    public function testWarrantsTablePolicyScopeIndexReturnsQuery(): void
    {
        $policy = new WarrantsTablePolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $table = $this->getTableLocator()->get('Warrants');
        $query = $table->find();
        $result = $policy->scopeIndex($admin, $query);
        $this->assertInstanceOf(SelectQuery::class, $result);
    }

    // ── ServicePrincipalsTablePolicy (has canIndex, canAdd) ──

    public function testServicePrincipalsTablePolicySuperUserBypasses(): void
    {
        $policy = new ServicePrincipalsTablePolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $table = $this->getTableLocator()->get('ServicePrincipals');
        $this->assertTrue($policy->before($admin, $table, 'index'));
    }

    public function testServicePrincipalsTablePolicyNonPrivilegedDenied(): void
    {
        $policy = new ServicePrincipalsTablePolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $table = $this->getTableLocator()->get('ServicePrincipals');
        $this->assertNull($policy->before($agatha, $table, 'index'));
        $this->assertFalse($policy->canIndex($agatha, $table));
    }

    public function testServicePrincipalsTablePolicySuperUserCanAdd(): void
    {
        $policy = new ServicePrincipalsTablePolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $table = $this->getTableLocator()->get('ServicePrincipals');
        $this->assertTrue($policy->canAdd($admin, $table));
    }

    public function testServicePrincipalsTablePolicyNonPrivilegedCannotAdd(): void
    {
        $policy = new ServicePrincipalsTablePolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $table = $this->getTableLocator()->get('ServicePrincipals');
        $this->assertFalse($policy->canAdd($agatha, $table));
    }

    public function testServicePrincipalsTablePolicyScopeIndexReturnsQuery(): void
    {
        $policy = new ServicePrincipalsTablePolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $table = $this->getTableLocator()->get('ServicePrincipals');
        $query = $table->find();
        $result = $policy->scopeIndex($admin, $query);
        $this->assertInstanceOf(SelectQuery::class, $result);
    }

    // ── RolesTablePolicy (has canDeletePermission, canAddPermission, canSearchMembers) ──

    public function testRolesTablePolicySuperUserBypasses(): void
    {
        $policy = new RolesTablePolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $table = $this->getTableLocator()->get('Roles');
        $this->assertTrue($policy->before($admin, $table, 'index'));
    }

    public function testRolesTablePolicyNonPrivilegedDenied(): void
    {
        $policy = new RolesTablePolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $table = $this->getTableLocator()->get('Roles');
        $this->assertNull($policy->before($agatha, $table, 'index'));
        $this->assertFalse($policy->canIndex($agatha, $table));
    }

    public function testRolesTablePolicySuperUserCanDeletePermission(): void
    {
        $policy = new RolesTablePolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $entity = $this->getTableLocator()->get('Roles')->newEmptyEntity();
        $this->assertTrue($policy->canDeletePermission($admin, $entity));
    }

    public function testRolesTablePolicyNonPrivilegedCannotDeletePermission(): void
    {
        $policy = new RolesTablePolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getTableLocator()->get('Roles')->newEmptyEntity();
        $this->assertFalse($policy->canDeletePermission($agatha, $entity));
    }

    public function testRolesTablePolicySuperUserCanAddPermission(): void
    {
        $policy = new RolesTablePolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $entity = $this->getTableLocator()->get('Roles')->newEmptyEntity();
        $this->assertTrue($policy->canAddPermission($admin, $entity));
    }

    public function testRolesTablePolicyNonPrivilegedCannotAddPermission(): void
    {
        $policy = new RolesTablePolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getTableLocator()->get('Roles')->newEmptyEntity();
        $this->assertFalse($policy->canAddPermission($agatha, $entity));
    }

    public function testRolesTablePolicySuperUserCanSearchMembers(): void
    {
        $policy = new RolesTablePolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $entity = $this->getTableLocator()->get('Roles')->newEmptyEntity();
        $this->assertTrue($policy->canSearchMembers($admin, $entity));
    }

    public function testRolesTablePolicyNonPrivilegedCannotSearchMembers(): void
    {
        $policy = new RolesTablePolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getTableLocator()->get('Roles')->newEmptyEntity();
        $this->assertFalse($policy->canSearchMembers($agatha, $entity));
    }

    public function testRolesTablePolicyScopeIndexReturnsQuery(): void
    {
        $policy = new RolesTablePolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $table = $this->getTableLocator()->get('Roles');
        $query = $table->find();
        $result = $policy->scopeIndex($admin, $query);
        $this->assertInstanceOf(SelectQuery::class, $result);
    }

    // ── MemberRolesTablePolicy (has canDeactivate) ──

    public function testMemberRolesTablePolicySuperUserBypasses(): void
    {
        $policy = new MemberRolesTablePolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $table = $this->getTableLocator()->get('MemberRoles');
        $this->assertTrue($policy->before($admin, $table, 'index'));
    }

    public function testMemberRolesTablePolicyNonPrivilegedDenied(): void
    {
        $policy = new MemberRolesTablePolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $table = $this->getTableLocator()->get('MemberRoles');
        $this->assertNull($policy->before($agatha, $table, 'index'));
        $this->assertFalse($policy->canIndex($agatha, $table));
    }

    public function testMemberRolesTablePolicySuperUserCanDeactivate(): void
    {
        $policy = new MemberRolesTablePolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $entity = $this->getTableLocator()->get('MemberRoles')->newEmptyEntity();
        $this->assertTrue($policy->canDeactivate($admin, $entity));
    }

    public function testMemberRolesTablePolicyNonPrivilegedCannotDeactivate(): void
    {
        $policy = new MemberRolesTablePolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getTableLocator()->get('MemberRoles')->newEmptyEntity();
        $this->assertFalse($policy->canDeactivate($agatha, $entity));
    }
}
