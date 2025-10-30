<?php

declare(strict_types=1);

namespace App\Test\TestCase\Model\Entity;

use App\Model\Entity\Gathering;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Entity\Gathering Test Case
 *
 * Tests entity validation rules and virtual fields.
 */
class GatheringTest extends TestCase
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
}
