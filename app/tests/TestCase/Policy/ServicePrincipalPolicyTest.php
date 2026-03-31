<?php
declare(strict_types=1);

namespace App\Test\TestCase\Policy;

use App\Policy\ServicePrincipalPolicy;
use App\Test\TestCase\BaseTestCase;

class ServicePrincipalPolicyTest extends BaseTestCase
{
    protected $Members;
    protected ServicePrincipalPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
        $this->Members = $this->getTableLocator()->get('Members');
        $this->policy = new ServicePrincipalPolicy();
    }

    protected function getServicePrincipal()
    {
        $table = $this->getTableLocator()->get('ServicePrincipals');
        $sp = $table->find()->first();
        if (!$sp) {
            $this->markTestSkipped('No service principal data available');
        }

        return $sp;
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
        $sp = $this->getServicePrincipal();

        $actions = ['view', 'add', 'edit', 'delete', 'regenerateCredentials', 'manageTokens', 'manageRoles'];
        foreach ($actions as $action) {
            $result = $this->policy->before($admin, $sp, $action);
            $this->assertTrue($result, "Super user before() should return true for '$action'");
        }
    }

    public function testNonPrivilegedUserCannotView(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $sp = $this->getServicePrincipal();

        $beforeResult = $this->policy->before($agatha, $sp, 'view');
        $this->assertNull($beforeResult);
        $this->assertFalse($this->policy->canView($agatha, $sp));
    }

    public function testNonPrivilegedUserCannotAdd(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $sp = $this->getServicePrincipal();

        $beforeResult = $this->policy->before($agatha, $sp, 'add');
        $this->assertNull($beforeResult);
        $this->assertFalse($this->policy->canAdd($agatha, $sp));
    }

    public function testNonPrivilegedUserCannotEdit(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $sp = $this->getServicePrincipal();

        $beforeResult = $this->policy->before($agatha, $sp, 'edit');
        $this->assertNull($beforeResult);
        $this->assertFalse($this->policy->canEdit($agatha, $sp));
    }

    public function testNonPrivilegedUserCannotDelete(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $sp = $this->getServicePrincipal();

        $beforeResult = $this->policy->before($agatha, $sp, 'delete');
        $this->assertNull($beforeResult);
        $this->assertFalse($this->policy->canDelete($agatha, $sp));
    }

    public function testCanRegenerateCredentialsDelegatesToCanEdit(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $sp = $this->getServicePrincipal();

        $regenResult = $this->policy->canRegenerateCredentials($agatha, $sp);
        $editResult = $this->policy->canEdit($agatha, $sp);
        $this->assertSame($editResult, $regenResult, 'canRegenerateCredentials should delegate to canEdit');
    }

    public function testCanManageTokensDelegatesToCanEdit(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $sp = $this->getServicePrincipal();

        $tokensResult = $this->policy->canManageTokens($agatha, $sp);
        $editResult = $this->policy->canEdit($agatha, $sp);
        $this->assertSame($editResult, $tokensResult, 'canManageTokens should delegate to canEdit');
    }

    public function testCanManageRolesDelegatesToCanEdit(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $sp = $this->getServicePrincipal();

        $rolesResult = $this->policy->canManageRoles($agatha, $sp);
        $editResult = $this->policy->canEdit($agatha, $sp);
        $this->assertSame($editResult, $rolesResult, 'canManageRoles should delegate to canEdit');
    }
}
