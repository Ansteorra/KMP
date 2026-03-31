<?php
declare(strict_types=1);

namespace App\Test\TestCase\Policy;

use App\Policy\GatheringAttendancePolicy;
use App\Test\TestCase\BaseTestCase;

class GatheringAttendancePolicyTest extends BaseTestCase
{
    protected $Members;
    protected GatheringAttendancePolicy $policy;
    protected int $gatheringId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
        $this->Members = $this->getTableLocator()->get('Members');
        $this->policy = new GatheringAttendancePolicy();

        // Grab a valid gathering ID from seed data
        $Gatherings = $this->getTableLocator()->get('Gatherings');
        $gathering = $Gatherings->find()->first();
        if (!$gathering) {
            $this->markTestSkipped('No gatherings in seed data');
        }
        $this->gatheringId = (int)$gathering->id;
    }

    protected function loadMember(int $id)
    {
        $member = $this->Members->get($id);
        $member->getPermissions();

        return $member;
    }

    protected function makeAttendance(int $memberId)
    {
        $GatheringAttendances = $this->getTableLocator()->get('GatheringAttendances');

        return $GatheringAttendances->newEntity([
            'member_id' => $memberId,
            'gathering_id' => $this->gatheringId,
        ]);
    }

    // -------------------------------------------------------
    // Super user bypass via before()
    // -------------------------------------------------------

    public function testSuperUserCanDoEverything(): void
    {
        $admin = $this->loadMember(self::ADMIN_MEMBER_ID);
        $attendance = $this->makeAttendance(self::TEST_MEMBER_AGATHA_ID);

        $actions = ['add', 'edit', 'delete', 'view'];
        foreach ($actions as $action) {
            $result = $this->policy->before($admin, $attendance, $action);
            $this->assertTrue($result, "Super user before() should return true for '$action'");
        }
    }

    // -------------------------------------------------------
    // canAdd — own attendance
    // -------------------------------------------------------

    public function testMemberCanAddOwnAttendance(): void
    {
        $bryce = $this->loadMember(self::TEST_MEMBER_BRYCE_ID);
        $attendance = $this->makeAttendance(self::TEST_MEMBER_BRYCE_ID);

        $this->assertTrue($this->policy->canAdd($bryce, $attendance));
    }

    public function testMemberCannotAddAttendanceForOther(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $attendance = $this->makeAttendance(self::TEST_MEMBER_BRYCE_ID);

        $beforeResult = $this->policy->before($agatha, $attendance, 'add');
        $this->assertNull($beforeResult);
        $this->assertFalse($this->policy->canAdd($agatha, $attendance));
    }

    // -------------------------------------------------------
    // canEdit — own attendance
    // -------------------------------------------------------

    public function testMemberCanEditOwnAttendance(): void
    {
        $bryce = $this->loadMember(self::TEST_MEMBER_BRYCE_ID);
        $attendance = $this->makeAttendance(self::TEST_MEMBER_BRYCE_ID);

        $this->assertTrue($this->policy->canEdit($bryce, $attendance));
    }

    public function testMemberCannotEditOtherAttendance(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $attendance = $this->makeAttendance(self::TEST_MEMBER_BRYCE_ID);

        $beforeResult = $this->policy->before($agatha, $attendance, 'edit');
        $this->assertNull($beforeResult);
        $this->assertFalse($this->policy->canEdit($agatha, $attendance));
    }

    // -------------------------------------------------------
    // canDelete — own attendance
    // -------------------------------------------------------

    public function testMemberCanDeleteOwnAttendance(): void
    {
        $bryce = $this->loadMember(self::TEST_MEMBER_BRYCE_ID);
        $attendance = $this->makeAttendance(self::TEST_MEMBER_BRYCE_ID);

        $this->assertTrue($this->policy->canDelete($bryce, $attendance));
    }

    public function testMemberCannotDeleteOtherAttendance(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $attendance = $this->makeAttendance(self::TEST_MEMBER_BRYCE_ID);

        $beforeResult = $this->policy->before($agatha, $attendance, 'delete');
        $this->assertNull($beforeResult);
        $this->assertFalse($this->policy->canDelete($agatha, $attendance));
    }

    // -------------------------------------------------------
    // canView — own attendance
    // -------------------------------------------------------

    public function testMemberCanViewOwnAttendance(): void
    {
        $bryce = $this->loadMember(self::TEST_MEMBER_BRYCE_ID);
        $attendance = $this->makeAttendance(self::TEST_MEMBER_BRYCE_ID);

        $this->assertTrue($this->policy->canView($bryce, $attendance));
    }

    public function testMemberCannotViewOtherAttendance(): void
    {
        $agatha = $this->loadMember(self::TEST_MEMBER_AGATHA_ID);
        $attendance = $this->makeAttendance(self::TEST_MEMBER_BRYCE_ID);

        $beforeResult = $this->policy->before($agatha, $attendance, 'view');
        $this->assertNull($beforeResult);
        $this->assertFalse($this->policy->canView($agatha, $attendance));
    }
}
