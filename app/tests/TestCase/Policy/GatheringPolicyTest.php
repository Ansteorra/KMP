<?php
declare(strict_types=1);

namespace App\Test\TestCase\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\Permission;
use App\Policy\GatheringPolicy;
use App\Test\TestCase\BaseTestCase;

class GatheringPolicyTest extends BaseTestCase
{
    protected $Members;
    protected GatheringPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
        $this->Members = $this->getTableLocator()->get('Members');
        $this->policy = new GatheringPolicy();
    }

    protected function loadMember(int $id)
    {
        $member = $this->Members->get($id);
        $member->getPermissions();

        return $member;
    }

    protected function getGathering()
    {
        $Gatherings = $this->getTableLocator()->get('Gatherings');
        $gathering = $Gatherings->find()->first();
        if (!$gathering) {
            $this->markTestSkipped('No gatherings in seed data');
        }

        return $gathering;
    }

    // -------------------------------------------------------
    // Super user bypass via before()
    // -------------------------------------------------------

    public function testSuperUserCanDoEverything(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $gathering = $this->getGathering();

        $actions = ['index', 'edit', 'cancel', 'uncancel', 'viewAttendance', 'quickView', 'calendar', 'view'];
        foreach ($actions as $action) {
            $result = $this->policy->before($admin, $gathering, $action);
            $this->assertTrue($result, "Super user before() should return true for '$action'");
        }
    }

    // -------------------------------------------------------
    // canIndex — always true
    // -------------------------------------------------------

    public function testCanIndexAlwaysReturnsTrue(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $gathering = $this->getGathering();

        $this->assertTrue($this->policy->canIndex($agatha, $gathering));
    }

    // -------------------------------------------------------
    // canQuickView — always true
    // -------------------------------------------------------

    public function testCanQuickViewAlwaysReturnsTrue(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $gathering = $this->getGathering();

        $this->assertTrue($this->policy->canQuickView($agatha, $gathering));
    }

    // -------------------------------------------------------
    // canCalendar — always true
    // -------------------------------------------------------

    public function testCanCalendarAlwaysReturnsTrue(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $gathering = $this->getGathering();

        $this->assertTrue($this->policy->canCalendar($agatha, $gathering));
    }

    // -------------------------------------------------------
    // canEdit — needs policy or steward
    // -------------------------------------------------------

    public function testNonPrivilegedUserCannotEdit(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $gathering = $this->getGathering();

        $beforeResult = $this->policy->before($agatha, $gathering, 'edit');
        $this->assertNull($beforeResult);
        $this->assertFalse($this->policy->canEdit($agatha, $gathering));
    }

    // -------------------------------------------------------
    // canCancel — delegates to canEdit
    // -------------------------------------------------------

    public function testNonPrivilegedUserCannotCancel(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $gathering = $this->getGathering();

        $beforeResult = $this->policy->before($agatha, $gathering, 'cancel');
        $this->assertNull($beforeResult);
        $this->assertFalse($this->policy->canCancel($agatha, $gathering));
    }

    // -------------------------------------------------------
    // canViewAttendance — needs policy or steward
    // -------------------------------------------------------

    public function testNonPrivilegedUserCannotViewAttendance(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $gathering = $this->getGathering();

        $beforeResult = $this->policy->before($agatha, $gathering, 'viewAttendance');
        $this->assertNull($beforeResult);
        $this->assertFalse($this->policy->canViewAttendance($agatha, $gathering));
    }

    // -------------------------------------------------------
    // canView — needs policy or steward
    // -------------------------------------------------------

    public function testNonPrivilegedUserCannotView(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $gathering = $this->getGathering();

        $beforeResult = $this->policy->before($agatha, $gathering, 'view');
        $this->assertNull($beforeResult);
        $this->assertFalse($this->policy->canView($agatha, $gathering));
    }

    // -------------------------------------------------------
    // canCancel and canUncancel delegate to canEdit
    // -------------------------------------------------------

    public function testCancelDelegatesToEdit(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $gathering = $this->getGathering();

        $cancelResult = $this->policy->canCancel($agatha, $gathering);
        $editResult = $this->policy->canEdit($agatha, $gathering);
        $this->assertSame($editResult, $cancelResult, 'canCancel should delegate to canEdit');
    }

    public function testUncancelDelegatesToEdit(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $gathering = $this->getGathering();

        $uncancelResult = $this->policy->canUncancel($agatha, $gathering);
        $editResult = $this->policy->canEdit($agatha, $gathering);
        $this->assertSame($editResult, $uncancelResult, 'canUncancel should delegate to canEdit');
    }

    public function testDedicatedPolicyCanCreateScheduledActivity(): void
    {
        $gathering = $this->getGathering();
        $user = $this->createSchedulePolicyUser(1234, 'canCreateScheduledActivity', (int)$gathering->branch_id);

        $this->assertTrue($this->policy->canCreateScheduledActivity($user, $gathering));
    }

    public function testDedicatedCreatePolicyCanViewGathering(): void
    {
        $gathering = $this->getGathering();
        $user = $this->createSchedulePolicyUser(1234, 'canCreateScheduledActivity', (int)$gathering->branch_id);

        $this->assertTrue($this->policy->canView($user, $gathering));
    }

    public function testDedicatedEditSchedulePolicyCanViewGathering(): void
    {
        $gathering = $this->getGathering();
        $user = $this->createSchedulePolicyUser(1234, 'canEditScheduledActivity', (int)$gathering->branch_id);

        $this->assertTrue($this->policy->canView($user, $gathering));
    }

    public function testDedicatedPolicyCanEditOwnScheduledActivity(): void
    {
        $gathering = $this->getGathering();
        $user = $this->createSchedulePolicyUser(1234, 'canEditScheduledActivity', (int)$gathering->branch_id);
        $scheduledActivity = $this->getTableLocator()->get('GatheringScheduledActivities')->newEntity([
            'gathering_id' => $gathering->id,
            'created_by' => 1234,
        ]);

        $this->assertTrue($this->policy->canEditScheduledActivity($user, $gathering, $scheduledActivity));
    }

    public function testDedicatedPolicyCannotEditOthersScheduledActivity(): void
    {
        $gathering = $this->getGathering();
        $user = $this->createSchedulePolicyUser(1234, 'canEditScheduledActivity', (int)$gathering->branch_id);
        $scheduledActivity = $this->getTableLocator()->get('GatheringScheduledActivities')->newEntity([
            'gathering_id' => $gathering->id,
            'created_by' => 5678,
        ]);

        $this->assertFalse($this->policy->canEditScheduledActivity($user, $gathering, $scheduledActivity));
    }

    public function testDedicatedPolicyCannotEditScheduledActivityFromAnotherGathering(): void
    {
        $gathering = $this->getGathering();
        $user = $this->createSchedulePolicyUser(1234, 'canEditScheduledActivity', (int)$gathering->branch_id);
        $scheduledActivity = $this->getTableLocator()->get('GatheringScheduledActivities')->newEntity([
            'gathering_id' => (int)$gathering->id + 1,
            'created_by' => 1234,
        ]);

        $this->assertFalse($this->policy->canEditScheduledActivity($user, $gathering, $scheduledActivity));
    }

    private function createSchedulePolicyUser(int $memberId, string $method, int $branchId): KmpIdentityInterface
    {
        $user = $this->createMock(KmpIdentityInterface::class);
        $user->method('isSuperUser')->willReturn(false);
        $user->method('getIdentifier')->willReturn($memberId);
        $user->method('getPolicies')->willReturn([
            GatheringPolicy::class => [
                $method => (object)[
                    'scoping_rule' => Permission::SCOPE_BRANCH_ONLY,
                    'branch_ids' => [$branchId],
                    'entity_id' => null,
                    'entity_type' => 'Direct Grant',
                ],
            ],
        ]);

        return $user;
    }
}
