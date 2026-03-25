<?php

declare(strict_types=1);

namespace App\Test\TestCase\Policy;

use App\Policy\MembersTablePolicy;
use App\Test\TestCase\BaseTestCase;

class MembersTablePolicyTest extends BaseTestCase
{
    protected $Members;
    protected MembersTablePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
        $this->Members = $this->getTableLocator()->get('Members');
        $this->policy = new MembersTablePolicy();
    }

    protected function getMembersTable()
    {
        return $this->getTableLocator()->get('Members');
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
        $table = $this->getMembersTable();

        $actions = ['verifyQueue', 'verifyQueueGridData', 'export'];
        foreach ($actions as $action) {
            $result = $this->policy->before($admin, $table, $action);
            $this->assertTrue($result, "Super user before() should return true for '$action'");
        }
    }

    public function testNonPrivilegedUserCannotVerifyQueue(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $table = $this->getMembersTable();

        $beforeResult = $this->policy->before($agatha, $table, 'verifyQueue');
        $this->assertNull($beforeResult);
        $this->assertFalse($this->policy->canVerifyQueue($agatha, $table));
    }

    public function testCanVerifyQueueGridDataDelegatesToCanVerifyQueue(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $table = $this->getMembersTable();

        $gridDataResult = $this->policy->canVerifyQueueGridData($agatha, $table);
        $verifyQueueResult = $this->policy->canVerifyQueue($agatha, $table);
        $this->assertSame($verifyQueueResult, $gridDataResult, 'canVerifyQueueGridData should delegate to canVerifyQueue');
    }

    public function testNonPrivilegedUserCannotExport(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $table = $this->getMembersTable();

        $beforeResult = $this->policy->before($agatha, $table, 'export');
        $this->assertNull($beforeResult);
        $this->assertFalse($this->policy->canExport($agatha, $table));
    }

    public function testScopeVerifyQueueReturnsUnmodifiedQuery(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $table = $this->getMembersTable();
        $query = $table->find();

        $result = $this->policy->scopeVerifyQueue($admin, $query);
        $this->assertSame($query, $result, 'scopeVerifyQueue should return the query unmodified');
    }

    public function testScopeIndexDvDelegatesToScopeIndex(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $table = $this->getMembersTable();

        $query1 = $table->find();
        $query2 = $table->find();

        $indexDvResult = $this->policy->scopeIndexDv($admin, $query1);
        $indexResult = $this->policy->scopeIndex($admin, $query2);

        // Both should apply the same transformation - for a super user,
        // _getBranchIdsForPolicy returns empty so both return query as-is
        $this->assertEquals($indexDvResult->sql(), $indexResult->sql(), 'scopeIndexDv should produce same SQL as scopeIndex');
    }

    public function testScopeGridDataDelegatesToScopeIndex(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $table = $this->getMembersTable();

        $query1 = $table->find();
        $query2 = $table->find();

        $gridDataResult = $this->policy->scopeGridData($admin, $query1);
        $indexResult = $this->policy->scopeIndex($admin, $query2);

        $this->assertEquals($gridDataResult->sql(), $indexResult->sql(), 'scopeGridData should produce same SQL as scopeIndex');
    }
}
