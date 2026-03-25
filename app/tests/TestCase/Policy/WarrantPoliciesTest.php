<?php
declare(strict_types=1);

namespace App\Test\TestCase\Policy;

use App\Model\Entity\Member;
use App\Policy\WarrantPolicy;
use App\Policy\WarrantRosterPolicy;
use App\Policy\WarrantRostersTablePolicy;
use App\Test\TestCase\BaseTestCase;
use Cake\ORM\Query\SelectQuery;

/**
 * Tests for warrant-related policies:
 * WarrantPolicy, WarrantRosterPolicy, WarrantRostersTablePolicy
 */
class WarrantPoliciesTest extends BaseTestCase
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

    // ── WarrantPolicy (canAllWarrants, canDeactivate, canDeclineWarrantInRoster) ──

    public function testWarrantPolicySuperUserBypasses(): void
    {
        $policy = new WarrantPolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $entity = $this->getTableLocator()->get('Warrants')->newEmptyEntity();
        $this->assertTrue($policy->before($admin, $entity, 'view'));
    }

    public function testWarrantPolicyNonPrivilegedDenied(): void
    {
        $policy = new WarrantPolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getTableLocator()->get('Warrants')->newEmptyEntity();
        $this->assertNull($policy->before($agatha, $entity, 'view'));
        $this->assertFalse($policy->canView($agatha, $entity));
    }

    public function testWarrantPolicySuperUserCanAllWarrants(): void
    {
        $policy = new WarrantPolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $entity = $this->getTableLocator()->get('Warrants')->newEmptyEntity();
        $this->assertTrue($policy->canAllWarrants($admin, $entity));
    }

    public function testWarrantPolicyNonPrivilegedCannotAllWarrants(): void
    {
        $policy = new WarrantPolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getTableLocator()->get('Warrants')->newEmptyEntity();
        $this->assertFalse($policy->canAllWarrants($agatha, $entity));
    }

    public function testWarrantPolicySuperUserCanDeactivate(): void
    {
        $policy = new WarrantPolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $entity = $this->getTableLocator()->get('Warrants')->newEmptyEntity();
        $this->assertTrue($policy->canDeactivate($admin, $entity));
    }

    public function testWarrantPolicyNonPrivilegedCannotDeactivate(): void
    {
        $policy = new WarrantPolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getTableLocator()->get('Warrants')->newEmptyEntity();
        $this->assertFalse($policy->canDeactivate($agatha, $entity));
    }

    public function testWarrantPolicySuperUserCanDeclineWarrantInRoster(): void
    {
        $policy = new WarrantPolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $entity = $this->getTableLocator()->get('Warrants')->newEmptyEntity();
        $this->assertTrue($policy->canDeclineWarrantInRoster($admin, $entity));
    }

    public function testWarrantPolicyNonPrivilegedCannotDeclineWarrantInRoster(): void
    {
        $policy = new WarrantPolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getTableLocator()->get('Warrants')->newEmptyEntity();
        $this->assertFalse($policy->canDeclineWarrantInRoster($agatha, $entity));
    }

    // ── WarrantRosterPolicy (canAllRosters, canApprove, canDecline) ──

    public function testWarrantRosterPolicySuperUserBypasses(): void
    {
        $policy = new WarrantRosterPolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $entity = $this->getTableLocator()->get('WarrantRosters')->newEmptyEntity();
        $this->assertTrue($policy->before($admin, $entity, 'view'));
    }

    public function testWarrantRosterPolicyNonPrivilegedDenied(): void
    {
        $policy = new WarrantRosterPolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getTableLocator()->get('WarrantRosters')->newEmptyEntity();
        $this->assertNull($policy->before($agatha, $entity, 'view'));
        $this->assertFalse($policy->canView($agatha, $entity));
    }

    public function testWarrantRosterPolicySuperUserCanAllRosters(): void
    {
        $policy = new WarrantRosterPolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $entity = $this->getTableLocator()->get('WarrantRosters')->newEmptyEntity();
        $this->assertTrue($policy->canAllRosters($admin, $entity));
    }

    public function testWarrantRosterPolicyNonPrivilegedCannotAllRosters(): void
    {
        $policy = new WarrantRosterPolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getTableLocator()->get('WarrantRosters')->newEmptyEntity();
        $this->assertFalse($policy->canAllRosters($agatha, $entity));
    }

    public function testWarrantRosterPolicySuperUserCanApprove(): void
    {
        $policy = new WarrantRosterPolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $entity = $this->getTableLocator()->get('WarrantRosters')->newEmptyEntity();
        $this->assertTrue($policy->canApprove($admin, $entity));
    }

    public function testWarrantRosterPolicyNonPrivilegedCannotApprove(): void
    {
        $policy = new WarrantRosterPolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getTableLocator()->get('WarrantRosters')->newEmptyEntity();
        $this->assertFalse($policy->canApprove($agatha, $entity));
    }

    public function testWarrantRosterPolicySuperUserCanDecline(): void
    {
        $policy = new WarrantRosterPolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $entity = $this->getTableLocator()->get('WarrantRosters')->newEmptyEntity();
        $this->assertTrue($policy->canDecline($admin, $entity));
    }

    public function testWarrantRosterPolicyNonPrivilegedCannotDecline(): void
    {
        $policy = new WarrantRosterPolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getTableLocator()->get('WarrantRosters')->newEmptyEntity();
        $this->assertFalse($policy->canDecline($agatha, $entity));
    }

    // ── WarrantRostersTablePolicy (canAllRosters, scopeAllRosters, scopeGridData) ──

    public function testWarrantRostersTablePolicySuperUserBypasses(): void
    {
        $policy = new WarrantRostersTablePolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $table = $this->getTableLocator()->get('WarrantRosters');
        $this->assertTrue($policy->before($admin, $table, 'index'));
    }

    public function testWarrantRostersTablePolicyNonPrivilegedDenied(): void
    {
        $policy = new WarrantRostersTablePolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $table = $this->getTableLocator()->get('WarrantRosters');
        $this->assertNull($policy->before($agatha, $table, 'index'));
        $this->assertFalse($policy->canIndex($agatha, $table));
    }

    public function testWarrantRostersTablePolicySuperUserCanAllRosters(): void
    {
        $policy = new WarrantRostersTablePolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $entity = $this->getTableLocator()->get('WarrantRosters')->newEmptyEntity();
        $this->assertTrue($policy->canAllRosters($admin, $entity));
    }

    public function testWarrantRostersTablePolicyNonPrivilegedCannotAllRosters(): void
    {
        $policy = new WarrantRostersTablePolicy();
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getTableLocator()->get('WarrantRosters')->newEmptyEntity();
        $this->assertFalse($policy->canAllRosters($agatha, $entity));
    }

    public function testWarrantRostersTablePolicyScopeAllRostersReturnsQuery(): void
    {
        $policy = new WarrantRostersTablePolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $table = $this->getTableLocator()->get('WarrantRosters');
        $query = $table->find();
        $result = $policy->scopeAllRosters($admin, $query);
        $this->assertInstanceOf(SelectQuery::class, $result);
    }

    public function testWarrantRostersTablePolicyScopeGridDataReturnsQuery(): void
    {
        $policy = new WarrantRostersTablePolicy();
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $table = $this->getTableLocator()->get('WarrantRosters');
        $query = $table->find();
        $result = $policy->scopeGridData($admin, $query);
        $this->assertInstanceOf(SelectQuery::class, $result);
    }
}
