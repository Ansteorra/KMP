<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Test\TestCase\BaseTestCase;
use Cake\I18n\DateTime;

class GatheringAttendancesTableTest extends BaseTestCase
{
    public function testRetiredRoyalProgressColumnsAreRemovedFromAttendanceAndOffices(): void
    {
        $attendances = $this->getTableLocator()->get('GatheringAttendances');
        foreach (['is_royal_progress', 'progress_office_id', 'progress_office_name', 'progress_branch_name'] as $field) {
            $this->assertFalse($attendances->getSchema()->hasColumn($field));
        }
        $this->assertFalse($this->getTableLocator()->get('Officers.Offices')->getSchema()->hasColumn('is_royal_progress'));
    }

    public function testOrdinaryRsvpRetainsSharingPreferencesAndIgnoresRetiredRoyalFields(): void
    {
        $gatherings = $this->getTableLocator()->get('Gatherings');
        $gathering = $gatherings->saveOrFail($gatherings->newEntity([
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'gathering_type_id' => $this->getTableLocator()->get('GatheringTypes')->find()->firstOrFail()->id,
            'name' => 'Ordinary RSVP fixture',
            'start_date' => DateTime::now()->addDays(30),
            'end_date' => DateTime::now()->addDays(31),
            'created_by' => self::ADMIN_MEMBER_ID,
        ]));
        $attendances = $this->getTableLocator()->get('GatheringAttendances');
        $attendance = $attendances->saveOrFail($attendances->newEntity([
            'gathering_id' => $gathering->id,
            'member_id' => self::TEST_MEMBER_AGATHA_ID,
            'created_by' => self::TEST_MEMBER_AGATHA_ID,
            'share_with_kingdom' => false,
            'share_with_hosting_group' => true,
            'public_note' => 'Planning to attend Saturday.',
            'is_royal_progress' => true,
            'progress_office_name' => 'Crown',
            'progress_branch_name' => 'Ansteorra',
        ]));
        $saved = $attendances->get($attendance->id);
        $this->assertFalse($saved->share_with_kingdom);
        $this->assertTrue($saved->share_with_hosting_group);
        $this->assertSame('Planning to attend Saturday.', $saved->public_note);
        $this->assertArrayNotHasKey('is_royal_progress', $saved->toArray());
        $this->assertArrayNotHasKey('progress_title', $saved->toArray());
    }
}
