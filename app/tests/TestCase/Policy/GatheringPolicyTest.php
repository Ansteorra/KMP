<?php

declare(strict_types=1);

namespace App\Test\TestCase\Policy;

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
}
