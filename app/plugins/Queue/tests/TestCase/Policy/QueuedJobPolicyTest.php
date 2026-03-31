<?php

declare(strict_types=1);

namespace Queue\Test\TestCase\Policy;

use Queue\Policy\QueuedJobPolicy;
use App\Model\Entity\Member;
use App\Policy\BasePolicy;
use App\Test\TestCase\BaseTestCase;
use Authorization\Policy\BeforePolicyInterface;

/**
 * QueuedJobPolicy test — tests all 16 public policy methods.
 *
 * Each method delegates to _hasPolicy(), so we verify:
 * 1. Super-user bypass via before()
 * 2. Non-privileged user denial for every method
 */
class QueuedJobPolicyTest extends BaseTestCase
{
    protected $Members;
    protected QueuedJobPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
        $this->Members = $this->getTableLocator()->get('Members');
        $this->policy = new QueuedJobPolicy();
    }

    protected function loadMember(int $id): Member
    {
        $member = $this->Members->get($id);
        $member->getPermissions();

        return $member;
    }

    protected function makeEntity(): \App\Model\Entity\BaseEntity
    {
        return $this->getTableLocator()->get('Queue.QueuedJobs')->newEmptyEntity();
    }

    // =========================================================================
    // Instantiation and inheritance
    // =========================================================================

    public function testExtendsBasePolicy(): void
    {
        $this->assertInstanceOf(BasePolicy::class, $this->policy);
        $this->assertInstanceOf(BeforePolicyInterface::class, $this->policy);
    }

    // =========================================================================
    // Super-user bypass
    // =========================================================================

    public function testSuperUserBypass(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $entity = $this->makeEntity();
        $this->assertTrue($this->policy->before($admin, $entity, 'addJob'));
    }

    // =========================================================================
    // Non-privileged denial for every public method
    // =========================================================================

    /**
     * @dataProvider policyMethodProvider
     */
    public function testNonPrivilegedUserDenied(string $method): void
    {
        $user = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $entity = $this->makeEntity();

        $this->assertFalse($this->policy->{$method}($user, $entity));
    }

    /**
     * @dataProvider policyMethodProvider
     */
    public function testSuperUserAllowedForMethod(string $method): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $entity = $this->makeEntity();
        // Super user is handled by before(), which returns true
        $this->assertTrue($this->policy->before($admin, $entity, $method));
    }

    public static function policyMethodProvider(): array
    {
        return [
            'canAddJob' => ['canAddJob'],
            'canResetJob' => ['canResetJob'],
            'canRemoveJob' => ['canRemoveJob'],
            'canProcesses' => ['canProcesses'],
            'canReset' => ['canReset'],
            'canFlush' => ['canFlush'],
            'canHardReset' => ['canHardReset'],
            'canStats' => ['canStats'],
            'canViewClasses' => ['canViewClasses'],
            'canImport' => ['canImport'],
            'canData' => ['canData'],
            'canExecute' => ['canExecute'],
            'canTest' => ['canTest'],
            'canMigrate' => ['canMigrate'],
            'canTerminate' => ['canTerminate'],
            'canCleanup' => ['canCleanup'],
        ];
    }
}
