<?php

declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\GatheringActivityService;
use Cake\TestSuite\TestCase;

/**
 * App\Service\GatheringActivityService Test Case
 *
 * Tests business logic for gathering activities including:
 * - Consolidating waivers from multiple activities
 * - Determining required waivers for a gathering
 * - Activity locking rules when waivers are uploaded
 */
class GatheringActivityServiceTest extends TestCase
{
    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Branches',
        'app.GatheringTypes',
        'app.Gatherings',
        'app.GatheringActivities',
        'plugin.Waivers.WaiverTypes',
        'plugin.Waivers.GatheringActivityWaivers',
    ];

    /**
     * Test subject
     *
     * @var \App\Service\GatheringActivityService
     */
    protected GatheringActivityService $GatheringActivityService;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->GatheringActivityService = new GatheringActivityService();
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->GatheringActivityService);
        parent::tearDown();
    }

    /**
     * Test consolidating waivers from multiple activities
     *
     * When multiple activities require the same waiver type,
     * it should only appear once in the consolidated list.
     *
     * @return void
     */
    public function testConsolidateWaivers(): void
    {
        // Activities 1, 2, 3 all require waiver_type_id = 1 (General Liability)
        $activityIds = [1, 2, 3]; // Armored, Rapier, Youth Combat

        $result = $this->GatheringActivityService->getRequiredWaivers($activityIds);

        $this->assertTrue($result->isSuccess());
        $waivers = $result->getData();

        // Should have exactly 2 unique waivers:
        // - General Liability (from all 3 activities)
        // - Youth Participation (from activity 3 only)
        $this->assertCount(2, $waivers);

        $waiverIds = array_column($waivers, 'id');
        $this->assertContains(1, $waiverIds); // General Liability
        $this->assertContains(2, $waiverIds); // Youth Participation

        // Verify no duplicates
        $this->assertCount(2, array_unique($waiverIds));
    }

    /**
     * Test getting waivers for a single activity
     *
     * @return void
     */
    public function testGetWaiversForSingleActivity(): void
    {
        $result = $this->GatheringActivityService->getRequiredWaivers([1]); // Armored Combat

        $this->assertTrue($result->isSuccess());
        $waivers = $result->getData();

        $this->assertCount(1, $waivers);
        $this->assertEquals(1, $waivers[0]['id']); // General Liability
        $this->assertEquals('General Liability Waiver', $waivers[0]['name']);
    }

    /**
     * Test getting waivers for an activity with no waiver requirements
     *
     * @return void
     */
    public function testGetWaiversForActivityWithNoRequirements(): void
    {
        $result = $this->GatheringActivityService->getRequiredWaivers([6]); // Arts & Sciences

        $this->assertTrue($result->isSuccess());
        $waivers = $result->getData();

        $this->assertCount(0, $waivers);
    }

    /**
     * Test getting waivers for activities with mixed requirements
     *
     * @return void
     */
    public function testGetWaiversForMixedActivities(): void
    {
        // Mix of activities: some with waivers, some without
        $activityIds = [1, 6]; // Armored Combat + Arts & Sciences

        $result = $this->GatheringActivityService->getRequiredWaivers($activityIds);

        $this->assertTrue($result->isSuccess());
        $waivers = $result->getData();

        // Should only get waiver from Armored Combat
        $this->assertCount(1, $waivers);
        $this->assertEquals(1, $waivers[0]['id']);
    }

    /**
     * Test waiver data includes all necessary information
     *
     * @return void
     */
    public function testWaiverDataStructure(): void
    {
        $result = $this->GatheringActivityService->getRequiredWaivers([1]);

        $this->assertTrue($result->isSuccess());
        $waivers = $result->getData();

        $waiver = $waivers[0];
        $this->assertArrayHasKey('id', $waiver);
        $this->assertArrayHasKey('name', $waiver);
        $this->assertArrayHasKey('description', $waiver);
        $this->assertArrayHasKey('retention_policy', $waiver);
    }

    /**
     * Test with invalid activity IDs
     *
     * @return void
     */
    public function testGetWaiversWithInvalidIds(): void
    {
        $result = $this->GatheringActivityService->getRequiredWaivers([9999]);

        // Should return success with empty array (no waivers found)
        $this->assertTrue($result->isSuccess());
        $waivers = $result->getData();
        $this->assertCount(0, $waivers);
    }

    /**
     * Test with empty activity list
     *
     * @return void
     */
    public function testGetWaiversWithEmptyList(): void
    {
        $result = $this->GatheringActivityService->getRequiredWaivers([]);

        $this->assertTrue($result->isSuccess());
        $waivers = $result->getData();
        $this->assertCount(0, $waivers);
    }

    /**
     * Test checking if activities can be modified
     *
     * Activities should not be modifiable once waivers have been uploaded
     * for a gathering using those activities.
     *
     * @return void
     */
    public function testCanModifyActivity(): void
    {
        // Template activities (gathering_id = null) should always be modifiable
        $result = $this->GatheringActivityService->canModifyActivity(1);
        $this->assertTrue($result->isSuccess());
        $this->assertTrue($result->getData());

        // This will be fully tested once we have gatherings with uploaded waivers
        $this->markTestIncomplete('Full test requires gatherings with waiver submissions');
    }

    /**
     * Test checking if activity can be deleted
     *
     * Template activities should be deletable unless they are currently
     * being used by a gathering.
     *
     * @return void
     */
    public function testCanDeleteActivity(): void
    {
        // Template activity not in use
        $result = $this->GatheringActivityService->canDeleteActivity(6); // Arts & Sciences
        $this->assertTrue($result->isSuccess());
        $this->assertTrue($result->getData());

        $this->markTestIncomplete('Full test requires gatherings using activities');
    }

    /**
     * Test waiver consolidation performance with many activities
     *
     * Ensures the consolidation algorithm is efficient even with
     * many activities that share waiver requirements.
     *
     * @return void
     */
    public function testConsolidateWaiversPerformance(): void
    {
        // All combat activities require the same waiver
        $activityIds = [1, 2, 3, 4, 5]; // All activities

        $startTime = microtime(true);
        $result = $this->GatheringActivityService->getRequiredWaivers($activityIds);
        $endTime = microtime(true);

        $this->assertTrue($result->isSuccess());

        // Should complete in well under 1 second
        $executionTime = $endTime - $startTime;
        $this->assertLessThan(1.0, $executionTime);
    }

    /**
     * Test getting activity summary with waiver count
     *
     * @return void
     */
    public function testGetActivitySummary(): void
    {
        $result = $this->GatheringActivityService->getActivitySummary(1); // Armored Combat

        $this->assertTrue($result->isSuccess());
        $summary = $result->getData();

        $this->assertArrayHasKey('id', $summary);
        $this->assertArrayHasKey('name', $summary);
        $this->assertArrayHasKey('waiver_count', $summary);
        $this->assertEquals(1, $summary['waiver_count']);
    }

    /**
     * Test getting summary for activity with multiple waivers
     *
     * @return void
     */
    public function testGetActivitySummaryMultipleWaivers(): void
    {
        $result = $this->GatheringActivityService->getActivitySummary(3); // Youth Combat

        $this->assertTrue($result->isSuccess());
        $summary = $result->getData();

        $this->assertEquals(2, $summary['waiver_count']); // General + Youth waivers
    }

    /**
     * Test getting summary for activity with no waivers
     *
     * @return void
     */
    public function testGetActivitySummaryNoWaivers(): void
    {
        $result = $this->GatheringActivityService->getActivitySummary(6); // Arts & Sciences

        $this->assertTrue($result->isSuccess());
        $summary = $result->getData();

        $this->assertEquals(0, $summary['waiver_count']);
    }

    /**
     * Test sorting waivers by name in consolidated list
     *
     * @return void
     */
    public function testConsolidatedWaiversAreSorted(): void
    {
        $result = $this->GatheringActivityService->getRequiredWaivers([1, 2, 3]);

        $this->assertTrue($result->isSuccess());
        $waivers = $result->getData();

        // Verify waivers are sorted by name
        $names = array_column($waivers, 'name');
        $sortedNames = $names;
        sort($sortedNames);
        $this->assertEquals($sortedNames, $names);
    }
}
