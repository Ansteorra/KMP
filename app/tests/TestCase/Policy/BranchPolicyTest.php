<?php

declare(strict_types=1);

namespace App\Test\TestCase\Policy;

use App\Policy\BranchPolicy;
use App\Test\TestCase\BaseTestCase;

class BranchPolicyTest extends BaseTestCase
{
    protected $Members;
    protected BranchPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
        $this->Members = $this->getTableLocator()->get('Members');
        $this->policy = new BranchPolicy();
    }

    protected function getBranch()
    {
        return $this->getTableLocator()->get('Branches')->get(self::KINGDOM_BRANCH_ID);
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
        $branch = $this->getBranch();

        $actions = ['index', 'view', 'viewOfficers', 'viewBranches', 'viewGatherings', 'add', 'edit', 'delete'];
        foreach ($actions as $action) {
            $result = $this->policy->before($admin, $branch, $action);
            $this->assertTrue($result, "Super user before() should return true for '$action'");
        }
    }

    public function testAnyAuthenticatedUserCanIndex(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $branch = $this->getBranch();

        $this->assertTrue($this->policy->canIndex($agatha, $branch));
    }

    public function testAnyAuthenticatedUserCanView(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $branch = $this->getBranch();

        $this->assertTrue($this->policy->canView($agatha, $branch));
    }

    public function testAnyAuthenticatedUserCanViewOfficers(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $branch = $this->getBranch();

        $this->assertTrue($this->policy->canViewOfficers($agatha, $branch));
    }

    public function testAnyAuthenticatedUserCanViewBranches(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $branch = $this->getBranch();

        $this->assertTrue($this->policy->canViewBranches($agatha, $branch));
    }

    public function testAnyAuthenticatedUserCanViewGatherings(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $branch = $this->getBranch();

        $this->assertTrue($this->policy->canViewGatherings($agatha, $branch));
    }

    public function testNonPrivilegedUserCannotAdd(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $branch = $this->getBranch();

        $beforeResult = $this->policy->before($agatha, $branch, 'add');
        $this->assertNull($beforeResult);
        $this->assertFalse($this->policy->canAdd($agatha, $branch));
    }

    public function testNonPrivilegedUserCannotEdit(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $branch = $this->getBranch();

        $beforeResult = $this->policy->before($agatha, $branch, 'edit');
        $this->assertNull($beforeResult);
        $this->assertFalse($this->policy->canEdit($agatha, $branch));
    }

    public function testNonPrivilegedUserCannotDelete(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $branch = $this->getBranch();

        $beforeResult = $this->policy->before($agatha, $branch, 'delete');
        $this->assertNull($beforeResult);
        $this->assertFalse($this->policy->canDelete($agatha, $branch));
    }
}
