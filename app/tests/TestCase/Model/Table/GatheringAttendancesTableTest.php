<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Test\TestCase\BaseTestCase;
use Cake\I18n\DateTime;
use Officers\Model\Entity\Officer;

/**
 * Tests royal progress RSVP behavior on GatheringAttendancesTable.
 *
 * Covers the RSVP-based royal progress model (issue #61) and the office
 * snapshot semantics that keep progress meaningful after office holders
 * change (issue #62).
 */
class GatheringAttendancesTableTest extends BaseTestCase
{
    /**
     * @var \App\Model\Table\GatheringAttendancesTable
     */
    protected $GatheringAttendances;

    protected function setUp(): void
    {
        parent::setUp();
        $this->GatheringAttendances = $this->getTableLocator()->get('GatheringAttendances');
    }

    protected function tearDown(): void
    {
        unset($this->GatheringAttendances);
        parent::tearDown();
    }

    /**
     * Create a progress-eligible office and a current officer assignment.
     *
     * @return array{office: \Officers\Model\Entity\Office, officer: \Officers\Model\Entity\Officer}
     */
    private function createProgressOfficerFixture(
        int $memberId = self::TEST_MEMBER_AGATHA_ID,
        bool $progressEligible = true,
    ): array {
        $unique = uniqid('progress', true);
        $officesTable = $this->getTableLocator()->get('Officers.Offices');
        $office = $officesTable->newEntity([
            'name' => "Crown {$unique}",
            'requires_warrant' => false,
            'required_office' => false,
            'can_skip_report' => true,
            'only_one_per_branch' => false,
            'is_royal_progress' => $progressEligible,
            'term_length' => 12,
        ]);
        $officesTable->saveOrFail($office);

        $officersTable = $this->getTableLocator()->get('Officers.Officers');
        $officer = $officersTable->newEntity([
            'member_id' => $memberId,
            'office_id' => $office->id,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'status' => Officer::CURRENT_STATUS,
            'start_on' => DateTime::now()->subDays(10),
            'expires_on' => DateTime::now()->addMonths(6),
            'approver_id' => self::ADMIN_MEMBER_ID,
            'approval_date' => DateTime::now(),
            'email_address' => 'progress@example.test',
        ]);
        $officersTable->saveOrFail($officer);

        return ['office' => $office, 'officer' => $officer];
    }

    private function createGathering(): object
    {
        $gatherings = $this->getTableLocator()->get('Gatherings');
        $gathering = $gatherings->newEntity([
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'gathering_type_id' => 1,
            'name' => 'Progress Test Gathering ' . uniqid(),
            'start_date' => DateTime::now()->addDays(30)->format('Y-m-d H:i:s'),
            'end_date' => DateTime::now()->addDays(31)->format('Y-m-d H:i:s'),
            'created_by' => self::ADMIN_MEMBER_ID,
        ]);

        return $gatherings->saveOrFail($gathering);
    }

    public function testApplyRoyalProgressSnapshotsOfficeAndForcesKingdomSharing(): void
    {
        $fixture = $this->createProgressOfficerFixture();
        $gathering = $this->createGathering();

        $attendance = $this->GatheringAttendances->newEntity([
            'gathering_id' => $gathering->id,
            'member_id' => self::TEST_MEMBER_AGATHA_ID,
            'share_with_kingdom' => false,
            'created_by' => self::TEST_MEMBER_AGATHA_ID,
        ]);

        $result = $this->GatheringAttendances->applyRoyalProgress(
            $attendance,
            $fixture['officer']->id,
            self::TEST_MEMBER_AGATHA_ID,
        );

        $this->assertTrue($result);
        $this->assertTrue($attendance->is_royal_progress);
        $this->assertSame($fixture['office']->id, $attendance->progress_office_id);
        $this->assertSame($fixture['office']->name, $attendance->progress_office_name);
        $this->assertNotEmpty($attendance->progress_branch_name);
        $this->assertTrue($attendance->share_with_kingdom, 'Progress RSVPs must be publicly visible');

        $this->GatheringAttendances->saveOrFail($attendance);

        // Snapshot survives an office rename (issue #62)
        $officesTable = $this->getTableLocator()->get('Officers.Offices');
        $office = $officesTable->get($fixture['office']->id);
        $office->name = 'Renamed Office';
        $officesTable->saveOrFail($office);

        $reloaded = $this->GatheringAttendances->get($attendance->id);
        $this->assertSame($fixture['office']->name, $reloaded->progress_office_name);
        $this->assertStringContainsString($fixture['office']->name, (string)$reloaded->progress_title);
    }

    public function testApplyRoyalProgressRejectsOfficerOfAnotherMember(): void
    {
        $fixture = $this->createProgressOfficerFixture(self::TEST_MEMBER_AGATHA_ID);
        $gathering = $this->createGathering();

        $attendance = $this->GatheringAttendances->newEntity([
            'gathering_id' => $gathering->id,
            'member_id' => self::TEST_MEMBER_BRYCE_ID,
            'created_by' => self::TEST_MEMBER_BRYCE_ID,
        ]);

        $result = $this->GatheringAttendances->applyRoyalProgress(
            $attendance,
            $fixture['officer']->id,
            self::TEST_MEMBER_BRYCE_ID,
        );

        $this->assertFalse($result);
        $this->assertFalse((bool)$attendance->is_royal_progress);
    }

    public function testApplyRoyalProgressRejectsNonProgressOffice(): void
    {
        $fixture = $this->createProgressOfficerFixture(self::TEST_MEMBER_AGATHA_ID, false);
        $gathering = $this->createGathering();

        $attendance = $this->GatheringAttendances->newEntity([
            'gathering_id' => $gathering->id,
            'member_id' => self::TEST_MEMBER_AGATHA_ID,
            'created_by' => self::TEST_MEMBER_AGATHA_ID,
        ]);

        $result = $this->GatheringAttendances->applyRoyalProgress(
            $attendance,
            $fixture['officer']->id,
            self::TEST_MEMBER_AGATHA_ID,
        );

        $this->assertFalse($result);
    }

    public function testApplyRoyalProgressClearsWhenOfficerIdNull(): void
    {
        $fixture = $this->createProgressOfficerFixture();
        $gathering = $this->createGathering();

        $attendance = $this->GatheringAttendances->newEntity([
            'gathering_id' => $gathering->id,
            'member_id' => self::TEST_MEMBER_AGATHA_ID,
            'created_by' => self::TEST_MEMBER_AGATHA_ID,
        ]);
        $this->GatheringAttendances->applyRoyalProgress(
            $attendance,
            $fixture['officer']->id,
            self::TEST_MEMBER_AGATHA_ID,
        );
        $this->assertTrue($attendance->is_royal_progress);

        $result = $this->GatheringAttendances->applyRoyalProgress($attendance, null, self::TEST_MEMBER_AGATHA_ID);

        $this->assertTrue($result);
        $this->assertFalse((bool)$attendance->is_royal_progress);
        $this->assertNull($attendance->progress_office_id);
        $this->assertNull($attendance->progress_office_name);
        $this->assertNull($attendance->progress_branch_name);
        $this->assertNull($attendance->progress_title);
    }

    public function testCurrentProgressOfficersForMemberOnlyReturnsEligibleCurrentAssignments(): void
    {
        $eligible = $this->createProgressOfficerFixture(self::TEST_MEMBER_DEVON_ID, true);
        $this->createProgressOfficerFixture(self::TEST_MEMBER_DEVON_ID, false);

        $officers = $this->GatheringAttendances->currentProgressOfficersForMember(self::TEST_MEMBER_DEVON_ID);

        $officerIds = array_map(fn($officer) => $officer->id, $officers);
        $this->assertContains($eligible['officer']->id, $officerIds);
        foreach ($officers as $officer) {
            $this->assertTrue($officer->office->is_royal_progress);
        }
    }
}
