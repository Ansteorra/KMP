<?php

declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Services\ICalendarService;
use App\Model\Entity\Gathering;
use Cake\I18n\DateTime;
use App\Test\TestCase\BaseTestCase;

/**
 * ICalendarService Test Case
 */
class ICalendarServiceTest extends BaseTestCase
{
    /**
     * Test subject
     *
     * @var \App\Services\ICalendarService
     */
    protected $ICalendarService;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->ICalendarService = new ICalendarService();
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->ICalendarService);

        parent::tearDown();
    }

    /**
     * Test generateICalendar method for single day event
     *
     * @return void
     */
    public function testGenerateICalendarSingleDay(): void
    {
        // Create a mock gathering entity
        $gathering = new Gathering([
            'id' => 1,
            'public_id' => 'abc123',
            'name' => 'Test Event',
            'description' => 'This is a test event description',
            'location' => '123 Main St, City, State',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'start_date' => new DateTime('2025-12-15'),
            'end_date' => new DateTime('2025-12-15'),
            'is_multi_day' => false,
        ]);

        // Mock related entities
        $gathering->branch = (object)[
            'name' => 'Test Branch'
        ];
        $gathering->gathering_type = (object)[
            'name' => 'Test Type'
        ];

        $result = $this->ICalendarService->generateICalendar($gathering);

        // Verify basic iCalendar structure
        $this->assertStringContainsString('BEGIN:VCALENDAR', $result);
        $this->assertStringContainsString('END:VCALENDAR', $result);
        $this->assertStringContainsString('BEGIN:VEVENT', $result);
        $this->assertStringContainsString('END:VEVENT', $result);

        // Verify event details
        $this->assertStringContainsString('SUMMARY:Test Event', $result);
        $this->assertStringContainsString('LOCATION:123 Main St\, City\, State', $result);
        $this->assertStringContainsString('GEO:40.7128;-74.006', $result);
        $this->assertStringContainsString('CATEGORIES:Test Type', $result);
    }

    /**
     * Test generateICalendar method for multi-day event
     *
     * @return void
     */
    public function testGenerateICalendarMultiDay(): void
    {
        // Create a mock gathering entity for multi-day event
        $gathering = new Gathering([
            'id' => 2,
            'public_id' => 'def456',
            'name' => 'Multi-Day Event',
            'description' => 'A multi-day gathering',
            'location' => 'Event Center',
            'start_date' => new DateTime('2025-12-15'),
            'end_date' => new DateTime('2025-12-17'),
            'is_multi_day' => true,
        ]);

        $gathering->branch = (object)[
            'name' => 'Test Branch'
        ];
        $gathering->gathering_type = (object)[
            'name' => 'Festival'
        ];

        $result = $this->ICalendarService->generateICalendar($gathering);

        // Verify event format contains start and end dates
        $this->assertStringContainsString('DTSTART:', $result);
        $this->assertStringContainsString('DTEND:', $result);
        $this->assertStringContainsString('Multi-Day Event', $result);
    }

    /**
     * Test getFilename method
     *
     * @return void
     */
    public function testGetFilename(): void
    {
        $gathering = new Gathering([
            'name' => 'Test Event With Spaces & Special!',
            'start_date' => new DateTime('2025-12-15'),
        ]);

        $filename = $this->ICalendarService->getFilename($gathering);

        // Should be lowercase, dashed, and include the date
        $this->assertStringContainsString('test-event-with-spaces-special', $filename);
        $this->assertStringContainsString('2025-12-15', $filename);
    }

    /**
     * Test text escaping for iCalendar format
     *
     * @return void
     */
    public function testTextEscaping(): void
    {
        $gathering = new Gathering([
            'id' => 3,
            'public_id' => 'ghi789',
            'name' => 'Test; Event, With\\Special',
            'description' => "Line one\nLine two",
            'start_date' => new DateTime('2025-12-15'),
            'end_date' => new DateTime('2025-12-15'),
            'is_multi_day' => false,
        ]);

        $gathering->branch = (object)['name' => 'Branch'];
        $gathering->gathering_type = (object)['name' => 'Type'];

        $result = $this->ICalendarService->generateICalendar($gathering);

        // Verify special characters are escaped
        $this->assertStringContainsString('Test\; Event\, With\\\\Special', $result);
        $this->assertStringContainsString('Line one\nLine two', $result);
    }
}
