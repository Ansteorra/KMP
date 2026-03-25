<?php

declare(strict_types=1);

namespace Waivers\Test\TestCase\Services;

use App\Test\TestCase\BaseTestCase;
use Cake\ORM\TableRegistry;
use Waivers\Services\GatheringActivityService;

/**
 * Waivers\Services\GatheringActivityService Test Case
 *
 * Tests the GatheringActivityService for managing waiver requirements
 * on gathering activities: add, remove, and get operations.
 *
 * @uses \Waivers\Services\GatheringActivityService
 */
class GatheringActivityServiceTest extends BaseTestCase
{
    /**
     * Service under test
     *
     * @var \Waivers\Services\GatheringActivityService
     */
    protected GatheringActivityService $service;

    /**
     * GatheringActivities table
     *
     * @var \App\Model\Table\GatheringActivitiesTable
     */
    protected $GatheringActivities;

    /**
     * GatheringActivityWaivers table
     *
     * @var \Waivers\Model\Table\GatheringActivityWaiversTable
     */
    protected $GatheringActivityWaivers;

    /**
     * WaiverTypes table
     *
     * @var \Waivers\Model\Table\WaiverTypesTable
     */
    protected $WaiverTypes;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();

        $this->GatheringActivities = TableRegistry::getTableLocator()->get('GatheringActivities');
        $this->GatheringActivityWaivers = TableRegistry::getTableLocator()->get('Waivers.GatheringActivityWaivers');
        $this->WaiverTypes = TableRegistry::getTableLocator()->get('Waivers.WaiverTypes');

        $this->service = new GatheringActivityService();
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->service);
        unset($this->GatheringActivities);
        unset($this->GatheringActivityWaivers);
        unset($this->WaiverTypes);

        parent::tearDown();
    }

    // =========================================================================
    // Tests for addWaiverRequirement()
    // =========================================================================

    /**
     * Test adding a new waiver requirement to an activity succeeds
     */
    public function testAddWaiverRequirementSuccess(): void
    {
        // Find a gathering activity and waiver type that are NOT already linked
        $activity = $this->GatheringActivities->find()->first();
        $this->assertNotNull($activity, 'Need at least one gathering activity in seed data');

        // Get all waiver type IDs already linked to this activity (non-deleted)
        $existingWaiverTypeIds = $this->GatheringActivityWaivers->find()
            ->where(['gathering_activity_id' => $activity->id])
            ->all()
            ->extract('waiver_type_id')
            ->toArray();

        // Find a waiver type not linked to this activity
        $query = $this->WaiverTypes->find();
        if (!empty($existingWaiverTypeIds)) {
            $query->where(['id NOT IN' => $existingWaiverTypeIds]);
        }
        $unlinkedWaiverType = $query->first();

        if (!$unlinkedWaiverType) {
            // Create a new waiver type for testing
            $unlinkedWaiverType = $this->WaiverTypes->newEntity([
                'name' => 'Test Waiver Type ' . uniqid(),
                'description' => 'Test waiver type for unit testing',
                'is_active' => true,
                'retention_policy' => '{"anchor":"gathering_end_date","duration":{"years":7}}',
            ]);
            $this->WaiverTypes->saveOrFail($unlinkedWaiverType);
        }

        $result = $this->service->addWaiverRequirement($activity->id, $unlinkedWaiverType->id);

        $this->assertTrue($result->success, 'Should successfully add waiver requirement');
        $this->assertStringContainsString('successfully', $result->reason);

        // Verify the record was created in the database
        $exists = $this->GatheringActivityWaivers->exists([
            'gathering_activity_id' => $activity->id,
            'waiver_type_id' => $unlinkedWaiverType->id,
        ]);
        $this->assertTrue($exists, 'Waiver requirement should exist in database');
    }

    /**
     * Test adding a duplicate waiver requirement fails
     */
    public function testAddWaiverRequirementDuplicateFails(): void
    {
        // Find an existing waiver requirement
        $existing = $this->GatheringActivityWaivers->find()
            ->where(['deleted IS' => null])
            ->first();
        $this->assertNotNull($existing, 'Need at least one existing waiver requirement');

        $result = $this->service->addWaiverRequirement(
            $existing->gathering_activity_id,
            $existing->waiver_type_id,
        );

        $this->assertFalse($result->success, 'Should fail on duplicate');
        $this->assertStringContainsString('already exists', $result->reason);
    }

    /**
     * Test adding waiver requirement with non-existent activity fails
     */
    public function testAddWaiverRequirementInvalidActivityFails(): void
    {
        $waiverType = $this->WaiverTypes->find()->first();
        $this->assertNotNull($waiverType, 'Need at least one waiver type');

        $result = $this->service->addWaiverRequirement(999999, $waiverType->id);

        $this->assertFalse($result->success, 'Should fail for non-existent activity');
        $this->assertStringContainsString('not found', $result->reason);
    }

    /**
     * Test adding waiver requirement with non-existent waiver type fails
     */
    public function testAddWaiverRequirementInvalidWaiverTypeFails(): void
    {
        $activity = $this->GatheringActivities->find()->first();
        $this->assertNotNull($activity, 'Need at least one gathering activity');

        $result = $this->service->addWaiverRequirement($activity->id, 999999);

        $this->assertFalse($result->success, 'Should fail for non-existent waiver type');
        $this->assertStringContainsString('not found', $result->reason);
    }

    // =========================================================================
    // Tests for removeWaiverRequirement()
    // =========================================================================

    /**
     * Test removing an existing waiver requirement succeeds
     */
    public function testRemoveWaiverRequirementSuccess(): void
    {
        // First, create a fresh requirement to remove
        $activity = $this->GatheringActivities->find()->first();
        $this->assertNotNull($activity);

        $newWaiverType = $this->WaiverTypes->newEntity([
            'name' => 'Removable Waiver ' . uniqid(),
            'description' => 'Will be removed in test',
            'is_active' => true,
            'retention_policy' => '{"anchor":"gathering_end_date","duration":{"years":7}}',
        ]);
        $this->WaiverTypes->saveOrFail($newWaiverType);

        // Add it first
        $addResult = $this->service->addWaiverRequirement($activity->id, $newWaiverType->id);
        $this->assertTrue($addResult->success, 'Prerequisite: add should succeed');

        // Now remove it
        $result = $this->service->removeWaiverRequirement($activity->id, $newWaiverType->id);

        $this->assertTrue($result->success, 'Should successfully remove waiver requirement');
        $this->assertStringContainsString('successfully', $result->reason);
    }

    /**
     * Test removing a non-existent waiver requirement fails
     */
    public function testRemoveWaiverRequirementNotFoundFails(): void
    {
        $result = $this->service->removeWaiverRequirement(999999, 999999);

        $this->assertFalse($result->success, 'Should fail when requirement does not exist');
        $this->assertStringContainsString('not found', $result->reason);
    }

    // =========================================================================
    // Tests for getRequiredWaiverTypes()
    // =========================================================================

    /**
     * Test getting required waiver types for an activity with requirements
     */
    public function testGetRequiredWaiverTypesReturnsData(): void
    {
        // Find an activity that has non-deleted waiver requirements
        $requirementRow = $this->GatheringActivityWaivers->find()
            ->where(['deleted IS' => null])
            ->first();
        $this->assertNotNull($requirementRow, 'Need at least one active waiver requirement');

        $result = $this->service->getRequiredWaiverTypes($requirementRow->gathering_activity_id);

        $this->assertTrue($result->success, 'Should succeed');
        $this->assertIsArray($result->data, 'Data should be an array of waiver types');
        $this->assertGreaterThan(0, count($result->data), 'Should return at least one waiver type');

        // Verify each element is a WaiverType entity
        $firstWaiverType = $result->data[0];
        $this->assertNotNull($firstWaiverType->name, 'Waiver type should have a name');
    }

    /**
     * Test getting required waiver types for an activity with no requirements
     */
    public function testGetRequiredWaiverTypesEmptyResult(): void
    {
        // Create a new activity with no waiver requirements
        $newActivity = $this->GatheringActivities->newEntity([
            'name' => 'No Waivers Activity ' . uniqid(),
        ]);
        $this->GatheringActivities->saveOrFail($newActivity);

        $result = $this->service->getRequiredWaiverTypes($newActivity->id);

        $this->assertTrue($result->success, 'Should succeed even with no requirements');
        $this->assertIsArray($result->data, 'Data should be an array');
        $this->assertCount(0, $result->data, 'Should return empty array');
    }

    /**
     * Test getting required waiver types for a non-existent activity returns empty
     */
    public function testGetRequiredWaiverTypesForNonExistentActivity(): void
    {
        $result = $this->service->getRequiredWaiverTypes(999999);

        $this->assertTrue($result->success, 'Should succeed (no matching records)');
        $this->assertIsArray($result->data);
        $this->assertCount(0, $result->data, 'Should return empty array for non-existent activity');
    }

    // =========================================================================
    // Integration / round-trip tests
    // =========================================================================

    /**
     * Test full add-get-remove cycle
     */
    public function testAddGetRemoveCycle(): void
    {
        $activity = $this->GatheringActivities->find()->first();
        $this->assertNotNull($activity);

        // Create a fresh waiver type for clean test
        $waiverType = $this->WaiverTypes->newEntity([
            'name' => 'Cycle Test Waiver ' . uniqid(),
            'description' => 'For round-trip testing',
            'is_active' => true,
            'retention_policy' => '{"anchor":"gathering_end_date","duration":{"years":7}}',
        ]);
        $this->WaiverTypes->saveOrFail($waiverType);

        // Get initial count
        $initialResult = $this->service->getRequiredWaiverTypes($activity->id);
        $this->assertTrue($initialResult->success);
        $initialCount = count($initialResult->data);

        // Add
        $addResult = $this->service->addWaiverRequirement($activity->id, $waiverType->id);
        $this->assertTrue($addResult->success, 'Add should succeed');

        // Get — should have one more
        $afterAddResult = $this->service->getRequiredWaiverTypes($activity->id);
        $this->assertTrue($afterAddResult->success);
        $this->assertCount($initialCount + 1, $afterAddResult->data, 'Should have one more waiver type');

        // Verify the new waiver type is in the list
        $foundNew = false;
        foreach ($afterAddResult->data as $wt) {
            if ($wt->id === $waiverType->id) {
                $foundNew = true;
                break;
            }
        }
        $this->assertTrue($foundNew, 'New waiver type should be in the list');

        // Remove
        $removeResult = $this->service->removeWaiverRequirement($activity->id, $waiverType->id);
        $this->assertTrue($removeResult->success, 'Remove should succeed');

        // Get — should be back to initial count
        $afterRemoveResult = $this->service->getRequiredWaiverTypes($activity->id);
        $this->assertTrue($afterRemoveResult->success);
        $this->assertCount($initialCount, $afterRemoveResult->data, 'Should be back to initial count');
    }
}
