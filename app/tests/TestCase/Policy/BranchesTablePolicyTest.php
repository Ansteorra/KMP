<?php

declare(strict_types=1);

namespace App\Test\TestCase\Policy;

use App\Policy\BranchesTablePolicy;
use App\Test\TestCase\BaseTestCase;

class BranchesTablePolicyTest extends BaseTestCase
{
    protected $Members;
    protected BranchesTablePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
        $this->Members = $this->getTableLocator()->get('Members');
        $this->policy = new BranchesTablePolicy();
    }

    protected function getBranchesTable()
    {
        return $this->getTableLocator()->get('Branches');
    }

    protected function loadMember(int $id)
    {
        $member = $this->Members->get($id);
        $member->getPermissions();

        return $member;
    }

    public function testSuperUserBypassesAllChecks(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $table = $this->getBranchesTable();

        $actions = ['index', 'gridData', 'view', 'add', 'edit'];
        foreach ($actions as $action) {
            $result = $this->policy->before($admin, $table, $action);
            $this->assertTrue($result, "Super user before() should return true for '$action'");
        }
    }

    public function testAnyAuthenticatedUserCanIndex(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $table = $this->getBranchesTable();

        $this->assertTrue($this->policy->canIndex($agatha, $table));
    }

    public function testAnyAuthenticatedUserCanGridData(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $table = $this->getBranchesTable();

        $this->assertTrue($this->policy->canGridData($agatha, $table));
    }

    public function testAnyAuthenticatedUserCanView(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $table = $this->getBranchesTable();

        $this->assertTrue($this->policy->canView($agatha, $table));
    }

    public function testScopeIndexReturnsUnmodifiedQuery(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $table = $this->getBranchesTable();
        $query = $table->find();

        $result = $this->policy->scopeIndex($agatha, $query);
        $this->assertSame($query, $result, 'scopeIndex should return the query unmodified');
    }

    public function testNonPrivilegedUserCannotAdd(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $table = $this->getBranchesTable();

        $beforeResult = $this->policy->before($agatha, $table, 'add');
        $this->assertNull($beforeResult);
        $this->assertFalse($this->policy->canAdd($agatha, $table));
    }

    public function testNonPrivilegedUserCannotEdit(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $branch = $this->getBranchesTable()->get(self::KINGDOM_BRANCH_ID);

        $beforeResult = $this->policy->before($agatha, $branch, 'edit');
        $this->assertNull($beforeResult);
        $this->assertFalse($this->policy->canEdit($agatha, $branch));
    }
}
