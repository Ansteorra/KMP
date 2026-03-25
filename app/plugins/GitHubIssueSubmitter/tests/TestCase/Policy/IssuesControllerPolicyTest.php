<?php

declare(strict_types=1);

namespace GitHubIssueSubmitter\Test\TestCase\Policy;

use GitHubIssueSubmitter\Policy\IssuesControllerPolicy;
use App\Model\Entity\Member;
use App\Policy\BasePolicy;
use App\Test\TestCase\BaseTestCase;
use Authorization\Policy\BeforePolicyInterface;

/**
 * IssuesControllerPolicy test for GitHubIssueSubmitter plugin.
 *
 * Tests the permissive authorization model where canSubmit always returns true.
 */
class IssuesControllerPolicyTest extends BaseTestCase
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

    public function testExtendsBasePolicy(): void
    {
        $policy = new IssuesControllerPolicy();
        $this->assertInstanceOf(BasePolicy::class, $policy);
        $this->assertInstanceOf(BeforePolicyInterface::class, $policy);
    }

    public function testSuperUserBypass(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $policy = new IssuesControllerPolicy();
        $urlProps = ['controller' => 'Issues', 'action' => 'submit', 'plugin' => 'GitHubIssueSubmitter'];
        $this->assertTrue($policy->before($admin, $urlProps, 'submit'));
    }

    public function testCanSubmitAlwaysReturnsTrue(): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $policy = new IssuesControllerPolicy();
        $resource = ['controller' => 'Issues', 'action' => 'submit', 'plugin' => 'GitHubIssueSubmitter'];
        $this->assertTrue($policy->canSubmit($user, $resource));
    }

    public function testCanSubmitTrueForAnyUser(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $policy = new IssuesControllerPolicy();
        $resource = ['controller' => 'Issues', 'action' => 'submit', 'plugin' => 'GitHubIssueSubmitter'];
        $this->assertTrue($policy->canSubmit($admin, $resource));
    }
}
