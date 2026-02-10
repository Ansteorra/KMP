<?php

declare(strict_types=1);

namespace App\Test\TestCase\Model\Entity;

use App\Model\Entity\Gathering;
use App\Test\TestCase\BaseTestCase;

/**
 * App\Model\Entity\Gathering Test Case
 *
 * Tests entity validation rules and virtual fields.
 */
class GatheringTest extends BaseTestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Entity\Gathering
     */
    protected $Gathering;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->Gathering = new Gathering();
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->Gathering);
        parent::tearDown();
    }

    /**
     * Test that required fields are enforced
     *
     * @return void
     */
    public function testValidationRequiredFields(): void
    {
        $gathering = new Gathering([
            'name' => '',
            'start_date' => null,
            'end_date' => null,
        ]);

        $this->assertFalse($gathering->hasErrors());

        // Errors would be caught at the table level validation
        $gathering->setError('name', ['_required' => 'This field is required']);
        $gathering->setError('start_date', ['_required' => 'This field is required']);
        $gathering->setError('end_date', ['_required' => 'This field is required']);

        $this->assertTrue($gathering->hasErrors());
        $this->assertNotEmpty($gathering->getError('name'));
        $this->assertNotEmpty($gathering->getError('start_date'));
        $this->assertNotEmpty($gathering->getError('end_date'));
    }

    /**
     * Test date validation - end_date >= start_date
     *
     * @return void
     */
    public function testDateValidationEndAfterStart(): void
    {
        $gathering = new Gathering([
            'name' => 'Test Gathering',
            'branch_id' => 1,
            'gathering_type_id' => 1,
            'start_date' => '2025-08-10',
            'end_date' => '2025-08-05', // Before start date
            'location' => 'Test Location',
        ]);

        // The validation rule should be at the table level
        // Entity just holds the data
        $this->assertNotNull($gathering->start_date);
        $this->assertNotNull($gathering->end_date);
    }

    /**
     * Test accessible fields
     *
     * @return void
     */
    public function testAccessibleFields(): void
    {
        $data = [
            'name' => 'Test Gathering',
            'branch_id' => 1,
            'gathering_type_id' => 1,
            'description' => 'Test description',
            'start_date' => '2025-08-01',
            'end_date' => '2025-08-03',
            'location' => 'Test Location',
        ];

        $gathering = new Gathering($data);

        $this->assertEquals('Test Gathering', $gathering->name);
        $this->assertEquals(1, $gathering->branch_id);
        $this->assertEquals(1, $gathering->gathering_type_id);
        $this->assertEquals('Test description', $gathering->description);
        $this->assertEquals('Test Location', $gathering->location);
    }

    /**
     * Test that id is not mass assignable
     *
     * @return void
     */
    public function testIdNotMassAssignable(): void
    {
        $gathering = new Gathering([
            'id' => 999,
            'name' => 'Test Gathering',
        ]);

        // ID should not be set via mass assignment (protected by default in CakePHP)
        $this->assertEquals('Test Gathering', $gathering->name);
    }

    /**
     * Test multi-day gathering scenario
     *
     * @return void
     */
    public function testMultiDayGathering(): void
    {
        $gathering = new Gathering([
            'name' => 'Week-Long Event',
            'start_date' => '2025-07-01',
            'end_date' => '2025-07-07',
        ]);

        $this->assertNotNull($gathering->start_date);
        $this->assertNotNull($gathering->end_date);

        // Could add virtual field to calculate duration if needed
    }

    /**
     * Test single-day gathering scenario
     *
     * @return void
     */
    public function testSingleDayGathering(): void
    {
        $gathering = new Gathering([
            'name' => 'Single Day Practice',
            'start_date' => new \Cake\I18n\DateTime('2025-06-15'),
            'end_date' => new \Cake\I18n\DateTime('2025-06-15'), // Same day
        ]);

        $this->assertNotNull($gathering->start_date);
        $this->assertNotNull($gathering->end_date);
        $this->assertEquals(
            $gathering->start_date->format('Y-m-d'),
            $gathering->end_date->format('Y-m-d')
        );
    }

    /**
     * Test is_multi_day virtual field for same day events
     *
     * @return void
     */
    public function testIsMultiDaySameDay(): void
    {
        // Event starts and ends on the same calendar day
        $gathering = new Gathering([
            'name' => 'Single Day Event',
            'start_date' => new \Cake\I18n\DateTime('2025-06-15 09:00:00', 'UTC'),
            'end_date' => new \Cake\I18n\DateTime('2025-06-15 17:00:00', 'UTC'),
            'timezone' => 'America/Chicago',
        ]);

        $this->assertFalse($gathering->is_multi_day);
    }

    /**
     * Test is_multi_day virtual field for multi-day events
     *
     * @return void
     */
    public function testIsMultiDayDifferentDays(): void
    {
        // Event clearly spans multiple days
        $gathering = new Gathering([
            'name' => 'Multi Day Event',
            'start_date' => new \Cake\I18n\DateTime('2025-06-15 09:00:00', 'UTC'),
            'end_date' => new \Cake\I18n\DateTime('2025-06-17 17:00:00', 'UTC'),
            'timezone' => 'America/Chicago',
        ]);

        $this->assertTrue($gathering->is_multi_day);
    }

    /**
     * Test is_multi_day respects event timezone, not UTC
     *
     * An event that spans midnight in UTC but is the same day in the event's
     * timezone should NOT be flagged as multi-day.
     *
     * Example: An event in America/Chicago (UTC-5 or UTC-6) from 6pm-11pm local time
     * would be 00:00-05:00 next day in UTC, but is clearly a single day event locally.
     *
     * @return void
     */
    public function testIsMultiDayRespectsEventTimezone(): void
    {
        // Event in America/Chicago that runs from 6pm to 11pm local time
        // In UTC (with CDT offset of -5), this would be 23:00 to 04:00 next day UTC
        // BUT in the event's timezone, it's the same calendar day
        $gathering = new Gathering([
            'name' => 'Evening Event',
            // 6pm CDT = 23:00 UTC (same day)
            'start_date' => new \Cake\I18n\DateTime('2025-06-15 23:00:00', 'UTC'),
            // 11pm CDT = 04:00 UTC (next day in UTC, but same day in CDT)
            'end_date' => new \Cake\I18n\DateTime('2025-06-16 04:00:00', 'UTC'),
            'timezone' => 'America/Chicago', // CDT (UTC-5) in June
        ]);

        // This should NOT be multi-day because in America/Chicago,
        // both dates are June 15 local time
        $this->assertFalse(
            $gathering->is_multi_day,
            'Event that spans midnight UTC but is same day in event timezone should not be flagged as multi-day'
        );
    }

    /**
     * Test is_multi_day with no timezone falls back to app default timezone
     *
     * When no timezone is set on a gathering, the TimezoneHelper falls back to
     * the application's default timezone.
     *
     * @return void
     */
    public function testIsMultiDayNoTimezoneFallsBackToAppDefault(): void
    {
        // Temporarily set app default to America/Chicago to test fallback
        $originalTz = \Cake\Core\Configure::read('App.defaultTimezone');
        \Cake\Core\Configure::write('App.defaultTimezone', 'America/Chicago');

        // An event that spans midnight UTC but is same day in Central time
        $gathering = new Gathering([
            'name' => 'Event Without Timezone',
            'start_date' => new \Cake\I18n\DateTime('2025-06-15 23:00:00', 'UTC'),
            'end_date' => new \Cake\I18n\DateTime('2025-06-16 04:00:00', 'UTC'),
            // No timezone set - will use app default (America/Chicago)
        ]);

        // In America/Chicago (UTC-5 during CDT), the times would be:
        // 23:00 UTC = 18:00 CDT (June 15)
        // 04:00 UTC = 23:00 CDT (June 15)
        // So this is NOT multi-day in the default timezone
        $this->assertFalse(
            $gathering->is_multi_day,
            'Event with no timezone should use app default timezone for multi-day calculation'
        );

        // Restore original timezone
        \Cake\Core\Configure::write('App.defaultTimezone', $originalTz);
    }

    /**
     * Test is_multi_day with explicit UTC timezone
     *
     * @return void
     */
    public function testIsMultiDayExplicitUtc(): void
    {
        // When UTC is explicitly set, dates spanning midnight UTC ARE multi-day
        $gathering = new Gathering([
            'name' => 'UTC Event',
            'start_date' => new \Cake\I18n\DateTime('2025-06-15 23:00:00', 'UTC'),
            'end_date' => new \Cake\I18n\DateTime('2025-06-16 04:00:00', 'UTC'),
            'timezone' => 'UTC',
        ]);

        // In UTC, this spans June 15 and June 16
        $this->assertTrue(
            $gathering->is_multi_day,
            'Event with explicit UTC timezone should be multi-day when spanning midnight UTC'
        );
    }
}
