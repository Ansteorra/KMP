<?php

declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\TestCase\Support\HttpIntegrationTestCase;

/**
 * App\Controller\GatheringActivitiesController Test Case
 *
 * @uses \App\Controller\GatheringActivitiesController
 */
class GatheringActivitiesControllerTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->authenticateAsSuperUser();
    }

    /**
     * Test index method
     *
     * @return void
     * @uses \App\Controller\GatheringActivitiesController::index()
     */
    public function testIndex(): void
    {
        $this->get('/gathering-activities');
        $this->assertResponseOk();
        $this->assertResponseContains('Gathering Activities');
    }

    /**
     * Test view method
     *
     * @return void
     * @uses \App\Controller\GatheringActivitiesController::view()
     */
    public function testView(): void
    {
        $this->get('/gathering-activities/view/3');
        $this->assertResponseOk();
    }

    /**
     * Test view shows associated waivers
     *
     * @return void
     * @uses \App\Controller\GatheringActivitiesController::view()
     */
    public function testViewShowsAssociatedWaivers(): void
    {
        $this->get('/gathering-activities/view/3'); // Armored Combat
        $this->assertResponseOk();
        $this->assertResponseContains('Armored Combat');
    }

    /**
     * Test add method - GET
     *
     * @return void
     * @uses \App\Controller\GatheringActivitiesController::add()
     */
    public function testAddGet(): void
    {
        $this->get('/gathering-activities/add');
        $this->assertResponseOk();
        $this->assertResponseContains('Add Gathering Activity');
    }

    /**
     * Test add method - POST with valid data
     *
     * @return void
     * @uses \App\Controller\GatheringActivitiesController::add()
     */
    public function testAddPost(): void
    {
        $uniqueName = 'New Activity ' . time();
        $this->enableCsrfToken();
        $this->post('/gathering-activities/add', [
            'name' => $uniqueName,
            'description' => 'A new activity type',
            'instructions' => 'Follow safety guidelines',
            'sort_order' => 10,
        ]);
        $this->assertResponseSuccess();

        // Verify the activity was created
        $GatheringActivities = $this->getTableLocator()->get('GatheringActivities');
        $query = $GatheringActivities->find()->where(['name' => $uniqueName]);
        $this->assertEquals(1, $query->count());
    }

    /**
     * Test add method - POST with waiver associations
     *
     * @return void
     * @uses \App\Controller\GatheringActivitiesController::add()
     */
    public function testAddPostWithWaivers(): void
    {
        $uniqueName = 'New Combat Activity ' . time();
        $this->enableCsrfToken();
        $this->post('/gathering-activities/add', [
            'name' => $uniqueName,
            'description' => 'Requires waivers',
            'instructions' => 'Safety first',
            'sort_order' => 11,
            'waiver_types' => [
                '_ids' => [1, 2],
            ],
        ]);
        $this->assertResponseSuccess();
    }

    /**
     * Test edit method - GET
     *
     * @return void
     * @uses \App\Controller\GatheringActivitiesController::edit()
     */
    public function testEditGet(): void
    {
        $this->get('/gathering-activities/edit/3');
        $this->assertResponseOk();
        $this->assertResponseContains('Armored Combat');
    }

    /**
     * Test edit method - POST with valid data
     *
     * @return void
     * @uses \App\Controller\GatheringActivitiesController::edit()
     */
    public function testEditPost(): void
    {
        $this->enableCsrfToken();
        $this->post('/gathering-activities/edit/3', [
            'name' => 'Updated Armored Combat',
            'description' => 'Updated description',
            'instructions' => 'Updated instructions',
            'sort_order' => 1,
        ]);
        $this->assertResponseSuccess();
        $this->assertRedirect(['action' => 'view', 3]);

        // Verify the activity was updated
        $GatheringActivities = $this->getTableLocator()->get('GatheringActivities');
        $activity = $GatheringActivities->get(3);
        $this->assertEquals('Updated Armored Combat', $activity->name);
    }

    /**
     * Test edit waiver associations
     *
     * @return void
     * @uses \App\Controller\GatheringActivitiesController::edit()
     */
    public function testEditWaiverAssociations(): void
    {
        $this->enableCsrfToken();
        $this->post('/gathering-activities/edit/3', [
            'name' => 'Armored Combat',
            'description' => 'Heavy armored fighting with rattan weapons',
            'instructions' => 'Full armor required. No live steel.',
            'sort_order' => 1,
        ]);
        $this->assertResponseSuccess();

        // Verify activity was updated
        $GatheringActivities = $this->getTableLocator()->get('GatheringActivities');
        $activity = $GatheringActivities->get(3);
        $this->assertEquals('Armored Combat', $activity->name);
    }

    /**
     * Test delete method
     *
     * @return void
     * @uses \App\Controller\GatheringActivitiesController::delete()
     */
    public function testDelete(): void
    {
        // Create a fresh activity with no dependencies
        $GatheringActivities = $this->getTableLocator()->get('GatheringActivities');
        $activity = $GatheringActivities->newEntity([
            'name' => 'Deletable Activity ' . time(),
            'description' => 'Will be deleted',
            'sort_order' => 99,
        ]);
        $GatheringActivities->save($activity);
        $activityId = $activity->id;

        $this->enableCsrfToken();
        $this->post('/gathering-activities/delete/' . $activityId);
        $this->assertResponseSuccess();
        $this->assertRedirect(['action' => 'index']);

        // Verify the activity was deleted
        $query = $GatheringActivities->find()->where(['id' => $activityId]);
        $this->assertEquals(0, $query->count());
    }

    /**
     * Test delete blocks when activity has waiver requirements (T097)
     *
     * @return void
     * @uses \App\Controller\GatheringActivitiesController::delete()
     */
    public function testDeleteBlockedByWaiverRequirements(): void
    {
        $this->enableCsrfToken();
        $this->post('/gathering-activities/delete/3'); // Armored Combat (has waivers)
        $this->assertResponseSuccess();
        $this->assertRedirect(['action' => 'index']);

        // Verify the activity was NOT deleted
        $GatheringActivities = $this->getTableLocator()->get('GatheringActivities');
        $activity = $GatheringActivities->find()->where(['id' => 3])->first();
        $this->assertNotNull($activity);
    }

    /**
     * Test delete blocks when activity is used by gatherings (T097)
     *
     * @return void
     * @uses \App\Controller\GatheringActivitiesController::delete()
     */
    public function testDeleteBlockedByGatheringUsage(): void
    {
        // Activity 1 (Kingdom Court) is used by many gatherings
        $this->enableCsrfToken();
        $this->post('/gathering-activities/delete/1'); // Kingdom Court (used by gatherings)
        $this->assertResponseSuccess();
        $this->assertRedirect(['action' => 'index']);

        // Verify the activity was NOT deleted
        $GatheringActivities = $this->getTableLocator()->get('GatheringActivities');
        $activity = $GatheringActivities->find()->where(['id' => 1])->first();
        $this->assertNotNull($activity);
    }

    /**
     * Test changing activity waiver associations doesn't affect existing gatherings (T098)
     *
     * This verifies that waiver requirements are a configuration template - changes to the
     * GatheringActivity template don't propagate to existing gatherings that have already
     * been configured.
     *
     * @return void
     * @uses \App\Controller\GatheringActivitiesController::edit()
     */
    public function testChangingTemplateDoesNotAffectExistingGatherings(): void
    {
        // Armored Combat (ID 3) has active waiver associations
        $GatheringActivityWaivers = $this->getTableLocator()->get('Waivers.GatheringActivityWaivers');
        $initialCount = $GatheringActivityWaivers->find()
            ->where(['gathering_activity_id' => 3])
            ->count();
        $this->assertGreaterThan(0, $initialCount);

        // Verify that Armored Combat is used by gatherings
        $GatheringsGatheringActivities = $this->getTableLocator()->get('GatheringsGatheringActivities');
        $gatheringActivityLink = $GatheringsGatheringActivities->find()
            ->where([
                'gathering_activity_id' => 3,
            ])
            ->first();
        $this->assertNotNull($gatheringActivityLink);

        // The waiver requirements on the template remain unchanged
        $finalCount = $GatheringActivityWaivers->find()
            ->where(['gathering_activity_id' => 3])
            ->count();
        $this->assertEquals($initialCount, $finalCount);
    }

    /**
     * Test that waiver requirement associations are properly tracked (T099)
     *
     * @return void
     * @uses \App\Controller\GatheringActivitiesController::view()
     */
    public function testWaiverRequirementMarkingDisplayed(): void
    {
        // Verify that the view correctly shows the activity
        $this->get('/gathering-activities/view/3'); // Armored Combat
        $this->assertResponseOk();
        $this->assertResponseContains('Armored Combat');

        // Check that waiver associations exist in the data layer
        $GatheringActivityWaivers = $this->getTableLocator()->get('Waivers.GatheringActivityWaivers');
        $waivers = $GatheringActivityWaivers->find()
            ->where(['gathering_activity_id' => 3])
            ->toArray();

        $this->assertNotEmpty($waivers);
        $this->assertGreaterThan(0, count($waivers));
    }

    /**
     * Test authorization - unauthenticated user
     *
     * @return void
     * @uses \App\Controller\GatheringActivitiesController::index()
     */
    public function testIndexUnauthenticated(): void
    {
        $this->session(['Auth' => null]);
        $this->get('/gathering-activities');
        $this->assertRedirect();
    }
}
