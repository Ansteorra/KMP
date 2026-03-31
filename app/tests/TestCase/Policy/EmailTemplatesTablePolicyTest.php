<?php
declare(strict_types=1);

namespace App\Test\TestCase\Policy;

use App\Policy\EmailTemplatesTablePolicy;
use App\Test\TestCase\BaseTestCase;

class EmailTemplatesTablePolicyTest extends BaseTestCase
{
    protected $Members;
    protected EmailTemplatesTablePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
        $this->Members = $this->getTableLocator()->get('Members');
        $this->policy = new EmailTemplatesTablePolicy();
    }

    protected function getTable()
    {
        return $this->getTableLocator()->get('EmailTemplates');
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
        $table = $this->getTable();

        $actions = ['index', 'add', 'edit', 'delete', 'discover', 'sync'];
        foreach ($actions as $action) {
            $result = $this->policy->before($admin, $table, $action);
            $this->assertTrue($result, "Super user before() should return true for '$action'");
        }
    }

    public function testNonPrivilegedUserCannotIndex(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $table = $this->getTable();

        $beforeResult = $this->policy->before($agatha, $table, 'index');
        $this->assertNull($beforeResult);
        $this->assertFalse($this->policy->canIndex($agatha, $table));
    }

    public function testNonPrivilegedUserCannotAdd(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $table = $this->getTable();

        $beforeResult = $this->policy->before($agatha, $table, 'add');
        $this->assertNull($beforeResult);
        $this->assertFalse($this->policy->canAdd($agatha, $table));
    }

    public function testNonPrivilegedUserCannotEdit(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getTable()->newEmptyEntity();

        $beforeResult = $this->policy->before($agatha, $entity, 'edit');
        $this->assertNull($beforeResult);
        $this->assertFalse($this->policy->canEdit($agatha, $entity));
    }

    public function testNonPrivilegedUserCannotDelete(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->getTable()->newEmptyEntity();

        $beforeResult = $this->policy->before($agatha, $entity, 'delete');
        $this->assertNull($beforeResult);
        $this->assertFalse($this->policy->canDelete($agatha, $entity));
    }

    public function testCanDiscoverDelegatesToCanIndex(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $table = $this->getTable();

        $discoverResult = $this->policy->canDiscover($agatha, $table);
        $indexResult = $this->policy->canIndex($agatha, $table);
        $this->assertSame($indexResult, $discoverResult, 'canDiscover should delegate to canIndex');
    }

    public function testCanSyncDelegatesToCanAdd(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $table = $this->getTable();

        $syncResult = $this->policy->canSync($agatha, $table);
        $addResult = $this->policy->canAdd($agatha, $table);
        $this->assertSame($addResult, $syncResult, 'canSync should delegate to canAdd');
    }
}
