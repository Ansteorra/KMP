<?php

declare(strict_types=1);

namespace Waivers\Test\TestCase\Model\Entity;

use App\Test\TestCase\BaseTestCase;
use Waivers\Model\Entity\WaiverType;

/**
 * Waivers\Model\Entity\WaiverType Test Case
 */
class WaiverTypeTest extends BaseTestCase
{
    /**
     * Test subject
     *
     * @var \Waivers\Model\Entity\WaiverType
     */
    protected $WaiverType;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->WaiverType = new WaiverType([
            'id' => 1,
            'name' => 'Test Waiver Type',
            'description' => 'Test description',
            'retention_policy' => '{"anchor":"gathering_end_date","duration":{"years":7}}',
            'convert_to_pdf' => true,
            'is_active' => true,
        ]);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->WaiverType);
        parent::tearDown();
    }

    /**
     * Test accessible fields
     *
     * @return void
     */
    public function testAccessibleFields(): void
    {
        $data = [
            'id' => 999,
            'name' => 'New Name',
            'description' => 'New Description',
            'retention_policy' => '{"anchor":"permanent"}',
            'convert_to_pdf' => false,
            'is_active' => false,
            'created' => '2025-01-01 00:00:00',
            'modified' => '2025-01-01 00:00:00',
        ];

        $entity = new WaiverType($data, ['guard' => true]);

        // ID should not be accessible by mass assignment when guard is enabled
        $this->assertNull($entity->id);

        // These should be accessible
        $this->assertEquals('New Name', $entity->name);
        $this->assertEquals('New Description', $entity->description);
        $this->assertEquals('{"anchor":"permanent"}', $entity->retention_policy);
        $this->assertFalse($entity->convert_to_pdf);
        $this->assertFalse($entity->is_active);

        // Timestamps should not be accessible by mass assignment when guard is enabled
        $this->assertNull($entity->created);
    }

    /**
     * Test retention policy parsing virtual field
     *
     * @return void
     */
    public function testRetentionPolicyParsedVirtualField(): void
    {
        $expected = [
            'anchor' => 'gathering_end_date',
            'duration' => ['years' => 7],
        ];

        $this->assertEquals($expected, $this->WaiverType->retention_policy_parsed);
    }

    /**
     * Test retention policy parsed with invalid JSON
     *
     * @return void
     */
    public function testRetentionPolicyParsedWithInvalidJson(): void
    {
        $this->WaiverType->retention_policy = 'not-valid-json';

        $this->assertNull($this->WaiverType->retention_policy_parsed);
    }

    /**
     * Test retention policy parsed with null value
     *
     * @return void
     */
    public function testRetentionPolicyParsedWithNull(): void
    {
        $this->WaiverType->retention_policy = null;

        $this->assertNull($this->WaiverType->retention_policy_parsed);
    }

    /**
     * Test retention description virtual field
     *
     * @return void
     */
    public function testRetentionDescriptionVirtualField(): void
    {
        // Test gathering_end_date anchor
        $this->WaiverType->retention_policy = '{"anchor":"gathering_end_date","duration":{"years":7}}';
        $this->assertEquals(
            'Retain for 7 years after gathering end date',
            $this->WaiverType->retention_description
        );

        // Test upload_date anchor
        $this->WaiverType->retention_policy = '{"anchor":"upload_date","duration":{"months":6}}';
        $this->assertEquals(
            'Retain for 6 months after upload date',
            $this->WaiverType->retention_description
        );

        // Test permanent anchor
        $this->WaiverType->retention_policy = '{"anchor":"permanent"}';
        $this->assertEquals(
            'Retain permanently',
            $this->WaiverType->retention_description
        );

        // Test multiple duration units
        $this->WaiverType->retention_policy = '{"anchor":"gathering_end_date","duration":{"years":2,"months":6,"days":15}}';
        $this->assertEquals(
            'Retain for 2 years, 6 months, 15 days after gathering end date',
            $this->WaiverType->retention_description
        );

        // Test invalid JSON
        $this->WaiverType->retention_policy = 'invalid';
        $this->assertEquals(
            'Invalid retention policy',
            $this->WaiverType->retention_description
        );
    }

    /**
     * Test is_active status check
     *
     * @return void
     */
    public function testIsActiveStatus(): void
    {
        // Database returns tinyint as int, so cast to bool for strict assertions
        $this->assertTrue((bool)$this->WaiverType->is_active);

        $this->WaiverType->is_active = false;
        $this->assertFalse($this->WaiverType->is_active);

        $this->WaiverType->is_active = 1;
        $this->assertSame(1, $this->WaiverType->is_active); // Check it stores as int

        $this->WaiverType->is_active = 0;
        $this->assertSame(0, $this->WaiverType->is_active); // Stores as int 0, not false
    }

    /**
     * Test convert_to_pdf flag
     *
     * @return void
     */
    public function testConvertToPdfFlag(): void
    {
        $this->assertTrue($this->WaiverType->convert_to_pdf);

        $this->WaiverType->convert_to_pdf = false;
        $this->assertFalse($this->WaiverType->convert_to_pdf);
    }

    /**
     * Test entity can be marked as dirty
     *
     * @return void
     */
    public function testEntityDirtyTracking(): void
    {
        $entity = new WaiverType([
            'name' => 'Test',
            'retention_policy' => '{"anchor":"permanent"}',
        ]);

        // Initially clean after setting
        $entity->clean();
        $this->assertFalse($entity->isDirty('name'));

        // Should be dirty after modification
        $entity->name = 'Modified';
        $this->assertTrue($entity->isDirty('name'));
    }

    /**
     * Test required fields
     *
     * @return void
     */
    public function testRequiredFields(): void
    {
        $entity = new WaiverType();

        // Name is required - initially null
        $this->assertNull($entity->name);

        // Retention policy is required - initially null
        $this->assertNull($entity->retention_policy);

        // Booleans are null when not set (no defaults on entity, only DB schema)
        $this->assertNull($entity->convert_to_pdf);
        $this->assertNull($entity->is_active);
    }

    /**
     * Test hidden fields
     *
     * @return void
     */
    public function testHiddenFields(): void
    {
        $array = $this->WaiverType->toArray();

        // Virtual fields should be exposed if added to _virtual
        // (Implementation depends on entity configuration)
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('retention_policy', $array);
    }
}
