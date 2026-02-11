<?php

declare(strict_types=1);

namespace Waivers\Test\TestCase\Model\Table;

use App\Test\TestCase\BaseTestCase;
use Waivers\Model\Table\WaiverTypesTable;

/**
 * Waivers\Model\Table\WaiverTypesTable Test Case
 */
class WaiverTypesTableTest extends BaseTestCase
{
    /**
     * Test subject
     *
     * @var \Waivers\Model\Table\WaiverTypesTable
     */
    protected $WaiverTypes;    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('Waivers.WaiverTypes')
            ? []
            : ['className' => WaiverTypesTable::class];
        $this->WaiverTypes = $this->getTableLocator()->get(
            'Waivers.WaiverTypes',
            $config,
        );
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->WaiverTypes);
        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \Waivers\Model\Table\WaiverTypesTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        // Test valid entity
        $data = [
            'name' => 'Test Waiver Type',
            'description' => 'Test description',
            'retention_policy' => '{"anchor":"gathering_end_date","duration":{"years":7}}',
            'convert_to_pdf' => true,
            'is_active' => true,
        ];
        $waiverType = $this->WaiverTypes->newEntity($data);
        $this->assertEmpty($waiverType->getErrors(), 'Valid entity should have no errors');

        // Test missing required field (name)
        $data = [
            'description' => 'Test description',
            'retention_policy' => '{"anchor":"gathering_end_date","duration":{"years":7}}',
        ];
        $waiverType = $this->WaiverTypes->newEntity($data);
        $this->assertNotEmpty($waiverType->getErrors());
        $this->assertTrue(isset($waiverType->getErrors()['name']), 'Name is required');

        // Test invalid retention policy JSON
        $data = [
            'name' => 'Test Waiver',
            'retention_policy' => 'not-valid-json',
        ];
        $waiverType = $this->WaiverTypes->newEntity($data);
        $this->assertNotEmpty($waiverType->getErrors());
        $this->assertTrue(isset($waiverType->getErrors()['retention_policy']), 'Retention policy must be valid JSON');

        // Test max length validation for name
        $data = [
            'name' => str_repeat('a', 300),
            'retention_policy' => '{"anchor":"gathering_end_date","duration":{"years":7}}',
        ];
        $waiverType = $this->WaiverTypes->newEntity($data);
        $this->assertNotEmpty($waiverType->getErrors());
        $this->assertTrue(isset($waiverType->getErrors()['name']), 'Name should have max length validation');
    }

    /**
     * Test find active scope
     *
     * @return void
     * @uses \Waivers\Model\Table\WaiverTypesTable::findActive()
     */
    public function testFindActive(): void
    {
        $query = $this->WaiverTypes->find('active');
        $results = $query->all();

        $this->assertGreaterThan(0, $results->count(), 'Should find active waiver types');

        // Verify all results are active
        foreach ($results as $waiverType) {
            $this->assertTrue($waiverType->is_active, 'All results should be active');
        }

        // Should not include inactive waiver (id=6)
        $ids = $results->extract('id')->toArray();
        $this->assertNotContains(6, $ids, 'Should not include inactive waiver type');
    }

    /**
     * Test creating a waiver type
     *
     * @return void
     */
    public function testCreate(): void
    {
        $data = [
            'name' => 'New Test Waiver',
            'description' => 'A new test waiver type',
            'retention_policy' => '{"anchor":"upload_date","duration":{"years":5}}',
            'convert_to_pdf' => false,
            'is_active' => true,
        ];

        $waiverType = $this->WaiverTypes->newEntity($data);
        $result = $this->WaiverTypes->save($waiverType);

        $this->assertNotFalse($result, 'Waiver type should be saved');
        $this->assertNotEmpty($result->id, 'Saved entity should have an ID');

        // Verify it was saved to database
        $saved = $this->WaiverTypes->get($result->id);
        $this->assertEquals($data['name'], $saved->name);
        $this->assertEquals($data['retention_policy'], $saved->retention_policy);
    }

    /**
     * Test updating a waiver type
     *
     * @return void
     */
    public function testUpdate(): void
    {
        $waiverType = $this->WaiverTypes->get(1);
        $originalName = $waiverType->name;

        $waiverType->name = 'Updated Waiver Name';
        $waiverType->description = 'Updated description';

        $result = $this->WaiverTypes->save($waiverType);

        $this->assertNotFalse($result, 'Update should succeed');

        // Re-fetch to verify
        $updated = $this->WaiverTypes->get(1);
        $this->assertEquals('Updated Waiver Name', $updated->name);
        $this->assertEquals('Updated description', $updated->description);
        $this->assertNotEquals($originalName, $updated->name);
    }

    /**
     * Test deleting a waiver type
     *
     * @return void
     */
    public function testDelete(): void
    {
        // Create a new inactive waiver type to delete (avoids associations on seed data)
        $newWaiver = $this->WaiverTypes->newEntity([
            'name' => 'Deletable Table Test Waiver',
            'description' => 'Created for deletion test',
            'retention_policy' => '{"anchor":"permanent"}',
            'convert_to_pdf' => false,
            'is_active' => false,
        ]);
        $saved = $this->WaiverTypes->save($newWaiver);
        $this->assertNotFalse($saved);
        $deletableId = $saved->id;

        $waiverType = $this->WaiverTypes->get($deletableId);
        $result = $this->WaiverTypes->delete($waiverType);

        $this->assertTrue($result, 'Delete should succeed');

        // Verify it's gone
        $query = $this->WaiverTypes->find()->where(['id' => $deletableId]);
        $this->assertEquals(0, $query->count(), 'Waiver type should be deleted');
    }

    /**
     * Test deactivating instead of deleting active waiver types
     *
     * @return void
     */
    public function testDeactivateInsteadOfDelete(): void
    {
        $waiverType = $this->WaiverTypes->get(1);
        $this->assertTrue($waiverType->is_active, 'Should start as active');

        // Soft delete by deactivating
        $waiverType->is_active = false;
        $result = $this->WaiverTypes->save($waiverType);

        $this->assertNotFalse($result, 'Deactivation should succeed');

        // Verify it still exists but is inactive
        $updated = $this->WaiverTypes->get(1);
        $this->assertFalse($updated->is_active, 'Should be inactive');
    }

    /**
     * Test unique name validation
     *
     * @return void
     */
    public function testUniqueNameValidation(): void
    {
        // Try to create a waiver type with duplicate name
        $data = [
            'name' => 'Participation Roster Waiver', // Same as seed data id=1
            'description' => 'Duplicate name test',
            'retention_policy' => '{"anchor":"gathering_end_date","duration":{"years":7}}',
        ];

        $waiverType = $this->WaiverTypes->newEntity($data);
        $result = $this->WaiverTypes->save($waiverType);

        $this->assertFalse($result, 'Should fail to save duplicate name');
        $this->assertNotEmpty($waiverType->getErrors());
        $this->assertTrue(isset($waiverType->getErrors()['name']), 'Should have name validation error');
    }

    /**
     * Test retention policy validation for required structure
     *
     * @return void
     */
    public function testRetentionPolicyStructureValidation(): void
    {
        // Test missing anchor
        $data = [
            'name' => 'Test Waiver',
            'retention_policy' => '{"duration":{"years":7}}',
        ];
        $waiverType = $this->WaiverTypes->newEntity($data);
        $this->assertNotEmpty($waiverType->getErrors());

        // Test invalid anchor value
        $data = [
            'name' => 'Test Waiver',
            'retention_policy' => '{"anchor":"invalid_anchor","duration":{"years":7}}',
        ];
        $waiverType = $this->WaiverTypes->newEntity($data);
        $this->assertNotEmpty($waiverType->getErrors());

        // Test permanent anchor (should be valid without duration)
        $data = [
            'name' => 'Permanent Waiver',
            'retention_policy' => '{"anchor":"permanent"}',
        ];
        $waiverType = $this->WaiverTypes->newEntity($data);
        $this->assertEmpty($waiverType->getErrors(), 'Permanent anchor should be valid without duration');
    }
}
