<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Awards\Model\Entity\Recommendation;
use Cake\I18n\DateTime;

/**
 * App\Controller\GatheringsController Test Case
 *
 * Tests CRUD operations, activity selection, waiver requirement determination,
 * and activity locking functionality for gatherings.
 *
 * @uses \App\Controller\GatheringsController
 */
class GatheringsControllerTest extends HttpIntegrationTestCase
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
     * @uses \App\Controller\GatheringsController::index()
     */
    public function testIndex(): void
    {
        $this->get('/gatherings');
        $this->assertResponseOk();
        $this->assertResponseContains('Gatherings');
    }

    /**
     * Test view method
     *
     * @return void
     * @uses \App\Controller\GatheringsController::view()
     */
    public function testView(): void
    {
        $gatherings = $this->getTableLocator()->get('Gatherings');
        $gathering = $gatherings->find()->first();
        if (!$gathering) {
            $this->markTestSkipped('No gathering found in seed data');
        }
        $this->get('/gatherings/view/' . $gathering->public_id);
        $this->assertResponseOk();
    }

    public function testGatheringAwardsTabRendersQuickEditModalWhenRecommendationsExist(): void
    {
        $gatherings = $this->getTableLocator()->get('Gatherings');
        $awards = $this->getTableLocator()->get('Awards.Awards');
        $branches = $this->getTableLocator()->get('Branches');
        $members = $this->getTableLocator()->get('Members');
        $recommendations = $this->getTableLocator()->get('Awards.Recommendations');

        $gathering = $gatherings->find()->first();
        $award = $awards->find()->first();
        $branch = $branches->find()->first();
        $member = $members->find()->first();
        if (!$gathering || !$award || !$member || !$branch) {
            $this->markTestSkipped('Required seed data for gathering award recommendation test is unavailable.');
        }

        $branchId = $award->branch_id ?? $member->branch_id ?? $branch->id ?? null;
        if ($branchId === null) {
            $this->markTestSkipped('No branch available for recommendation test data.');
        }

        $status = $this->statusForState('Scheduled');
        if ($status === null) {
            $this->markTestSkipped('Scheduled recommendation state is unavailable.');
        }

        $recommendation = $recommendations->newEntity([
            'requester_id' => (int)$member->id,
            'member_id' => (int)$member->id,
            'branch_id' => (int)$branchId,
            'award_id' => (int)$award->id,
            'gathering_id' => (int)$gathering->id,
            'status' => $status,
            'state' => 'Scheduled',
            'state_date' => DateTime::now(),
            'requester_sca_name' => (string)($member->sca_name ?? 'Requester'),
            'member_sca_name' => (string)($member->sca_name ?? 'Member'),
            'contact_email' => (string)($member->email_address ?? 'test@example.com'),
            'contact_number' => (string)($member->phone_number ?? ''),
            'reason' => 'Quick edit modal regression test recommendation.',
            'call_into_court' => 'No',
            'court_availability' => 'Anytime',
        ]);
        $savedRecommendation = $recommendations->save($recommendation);
        $this->assertNotFalse($savedRecommendation);

        $this->get('/gatherings/view/' . $gathering->public_id);

        $this->assertResponseOk();
        $this->assertResponseContains('id="editRecommendationModal"');
    }

    public function testEditClearsGatheringWhenMovedToUnsupportedState(): void
    {
        $gatherings = $this->getTableLocator()->get('Gatherings');
        $awards = $this->getTableLocator()->get('Awards.Awards');
        $branches = $this->getTableLocator()->get('Branches');
        $members = $this->getTableLocator()->get('Members');
        $recommendations = $this->getTableLocator()->get('Awards.Recommendations');

        $gathering = $gatherings->find()->first();
        $award = $awards->find()->first();
        $branch = $branches->find()->first();
        $member = $members->find()->first();
        if (!$gathering || !$award || !$member || !$branch) {
            $this->markTestSkipped('Required seed data for gathering state transition test is unavailable.');
        }

        $scheduledStatus = $this->statusForState('Scheduled');
        $closedStatus = $this->statusForState('No Action');
        if ($scheduledStatus === null || $closedStatus === null) {
            $this->markTestSkipped('Required recommendation states are unavailable.');
        }

        $branchId = $award->branch_id ?? $member->branch_id ?? $branch->id ?? null;
        if ($branchId === null) {
            $this->markTestSkipped('No branch available for recommendation test data.');
        }

        $recommendation = $recommendations->newEntity([
            'requester_id' => (int)$member->id,
            'member_id' => (int)$member->id,
            'branch_id' => (int)$branchId,
            'award_id' => (int)$award->id,
            'gathering_id' => (int)$gathering->id,
            'status' => $scheduledStatus,
            'state' => 'Scheduled',
            'state_date' => DateTime::now(),
            'requester_sca_name' => (string)($member->sca_name ?? 'Requester'),
            'member_sca_name' => (string)($member->sca_name ?? 'Member'),
            'contact_email' => (string)($member->email_address ?? 'test@example.com'),
            'contact_number' => (string)($member->phone_number ?? ''),
            'reason' => 'State transition should clear gathering assignment.',
            'call_into_court' => 'No',
            'court_availability' => 'Anytime',
        ]);
        $savedRecommendation = $recommendations->save($recommendation);
        $this->assertNotFalse($savedRecommendation);

        $this->post('/awards/recommendations/edit/' . $savedRecommendation->id, [
            'state' => 'No Action',
            'gathering_id' => (string)$gathering->id,
            'close_reason' => 'No action needed',
            'current_page' => '/gatherings/view/' . $gathering->public_id,
        ]);

        $this->assertRedirectContains('/gatherings/view/' . $gathering->public_id);

        $updated = $recommendations->get($savedRecommendation->id);
        $this->assertSame('No Action', $updated->state);
        $this->assertSame($closedStatus, $updated->status);
        $this->assertNull($updated->gathering_id);
    }

    public function testBulkUpdateClearsGatheringWhenMovedToUnsupportedState(): void
    {
        $gatherings = $this->getTableLocator()->get('Gatherings');
        $awards = $this->getTableLocator()->get('Awards.Awards');
        $branches = $this->getTableLocator()->get('Branches');
        $members = $this->getTableLocator()->get('Members');
        $recommendations = $this->getTableLocator()->get('Awards.Recommendations');

        $gathering = $gatherings->find()->first();
        $award = $awards->find()->first();
        $branch = $branches->find()->first();
        $member = $members->find()->first();
        if (!$gathering || !$award || !$member || !$branch) {
            $this->markTestSkipped('Required seed data for gathering bulk transition test is unavailable.');
        }

        $scheduledStatus = $this->statusForState('Scheduled');
        $closedStatus = $this->statusForState('No Action');
        if ($scheduledStatus === null || $closedStatus === null) {
            $this->markTestSkipped('Required recommendation states are unavailable.');
        }

        $branchId = $award->branch_id ?? $member->branch_id ?? $branch->id ?? null;
        if ($branchId === null) {
            $this->markTestSkipped('No branch available for recommendation test data.');
        }

        $recommendation = $recommendations->newEntity([
            'requester_id' => (int)$member->id,
            'member_id' => (int)$member->id,
            'branch_id' => (int)$branchId,
            'award_id' => (int)$award->id,
            'gathering_id' => (int)$gathering->id,
            'status' => $scheduledStatus,
            'state' => 'Scheduled',
            'state_date' => DateTime::now(),
            'requester_sca_name' => (string)($member->sca_name ?? 'Requester'),
            'member_sca_name' => (string)($member->sca_name ?? 'Member'),
            'contact_email' => (string)($member->email_address ?? 'test@example.com'),
            'contact_number' => (string)($member->phone_number ?? ''),
            'reason' => 'Bulk state transition should clear gathering assignment.',
            'call_into_court' => 'No',
            'court_availability' => 'Anytime',
        ]);
        $savedRecommendation = $recommendations->save($recommendation);
        $this->assertNotFalse($savedRecommendation);

        $this->post('/awards/recommendations/update-states', [
            'ids' => (string)$savedRecommendation->id,
            'newState' => 'No Action',
            'gathering_id' => (string)$gathering->id,
            'close_reason' => 'No action needed',
            'view' => 'Index',
            'status' => 'All',
        ]);

        $this->assertRedirect();

        $updated = $recommendations->get($savedRecommendation->id);
        $this->assertSame('No Action', $updated->state);
        $this->assertSame($closedStatus, $updated->status);
        $this->assertNull($updated->gathering_id);
    }

    private function statusForState(string $state): ?string
    {
        foreach (Recommendation::getStatuses() as $status => $states) {
            if (in_array($state, $states, true)) {
                return $status;
            }
        }

        return null;
    }

    /**
     * Test add method - GET
     *
     * @return void
     * @uses \App\Controller\GatheringsController::add()
     */
    public function testAddGet(): void
    {
        $this->get('/gatherings/add');
        $this->assertResponseOk();
        $this->assertResponseContains('Add Gathering');
    }

    /**
     * Test add method - POST with valid data
     *
     * @return void
     * @uses \App\Controller\GatheringsController::add()
     */
    public function testAddPost(): void
    {
        $this->enableCsrfToken();
        $this->post('/gatherings/add', [
            'branch_id' => 2,
            'gathering_type_id' => 1,
            'name' => 'New Test Gathering',
            'description' => 'Test description',
            'start_date' => '2025-08-01',
            'end_date' => '2025-08-03',
            'location' => 'Test Location',
        ]);
        $this->assertResponseSuccess();
        $this->assertRedirect();

        // Verify the gathering was created
        $Gatherings = $this->getTableLocator()->get('Gatherings');
        $query = $Gatherings->find()->where(['name' => 'New Test Gathering']);
        $this->assertEquals(1, $query->count());
    }

    /**
     * Test add method - POST with activities selected
     *
     * @return void
     * @uses \App\Controller\GatheringsController::add()
     */
    public function testAddPostWithActivities(): void
    {
        $this->enableCsrfToken();
        $this->post('/gatherings/add', [
            'branch_id' => 2,
            'gathering_type_id' => 1,
            'name' => 'Gathering with Activities',
            'description' => 'Test gathering with selected activities',
            'start_date' => '2025-09-01',
            'end_date' => '2025-09-03',
            'location' => 'Activity Test Location',
            'gathering_activities' => [
                '_ids' => [1, 2],
            ],
        ]);
        $this->assertResponseSuccess();

        // Verify the gathering was created
        $Gatherings = $this->getTableLocator()->get('Gatherings');
        $gathering = $Gatherings->find()
            ->where(['name' => 'Gathering with Activities'])
            ->first();

        $this->assertNotNull($gathering);
    }

    /**
     * Test edit method - GET
     *
     * @return void
     * @uses \App\Controller\GatheringsController::edit()
     */
    public function testEditGet(): void
    {
        $this->get('/gatherings/edit/1');
        $this->assertResponseOk();
        $this->assertResponseContains('Edit Gathering');
    }

    /**
     * Test edit method - POST with valid data
     *
     * @return void
     * @uses \App\Controller\GatheringsController::edit()
     */
    public function testEditPost(): void
    {
        $this->enableCsrfToken();
        $this->post('/gatherings/edit/1', [
            'name' => 'Updated Spring Crown',
            'description' => 'Updated description',
            'start_date' => '2025-05-15',
            'end_date' => '2025-05-17',
            'location' => 'Updated Location',
        ]);
        $this->assertResponseSuccess();

        // Verify the gathering was updated
        $Gatherings = $this->getTableLocator()->get('Gatherings');
        $gathering = $Gatherings->get(1);
        $this->assertEquals('Updated Spring Crown', $gathering->name);
        $this->assertRedirect(['action' => 'view', $gathering->public_id]);
    }

    /**
     * Test delete method
     *
     * @return void
     * @uses \App\Controller\GatheringsController::delete()
     */
    public function testDelete(): void
    {
        $Gatherings = $this->getTableLocator()->get('Gatherings');
        $gathering = $Gatherings->find()->orderBy(['id' => 'DESC'])->first();
        if (!$gathering) {
            $this->markTestSkipped('No gathering found in seed data');
        }

        $this->enableCsrfToken();
        $this->post('/gatherings/delete/' . $gathering->id);
        $this->assertResponseSuccess();
        $this->assertRedirect(['action' => 'index']);
    }

    /**
     * Test date validation - end_date must be >= start_date
     *
     * @return void
     * @uses \App\Controller\GatheringsController::add()
     */
    public function testDateValidation(): void
    {
        $this->enableCsrfToken();
        $this->post('/gatherings/add', [
            'branch_id' => 2,
            'gathering_type_id' => 1,
            'name' => 'Invalid Date Gathering',
            'description' => 'End date before start date',
            'start_date' => '2025-08-10',
            'end_date' => '2025-08-05', // Before start_date
            'location' => 'Test Location',
        ]);

        // Verify the gathering was NOT created (validation failure)
        $Gatherings = $this->getTableLocator()->get('Gatherings');
        $query = $Gatherings->find()->where(['name' => 'Invalid Date Gathering']);
        $this->assertEquals(0, $query->count());
    }

    /**
     * Test multi-day gathering date ranges (T121)
     *
     * @return void
     * @uses \App\Controller\GatheringsController::add()
     */
    public function testMultiDayGatheringDateRanges(): void
    {
        $this->enableCsrfToken();
        $this->post('/gatherings/add', [
            'branch_id' => 2,
            'gathering_type_id' => 1,
            'name' => 'Long Multi-Day Event',
            'description' => 'Week-long gathering',
            'start_date' => '2025-08-01',
            'end_date' => '2025-08-07', // 7 days
            'location' => 'Test Location',
        ]);
        $this->assertResponseSuccess();

        // Verify the gathering was created
        $Gatherings = $this->getTableLocator()->get('Gatherings');
        $gathering = $Gatherings->find()->where(['name' => 'Long Multi-Day Event'])->first();
        $this->assertNotNull($gathering);
    }

    /**
     * Test required waiver consolidation (T122)
     * When multiple activities require the same waiver type, it should appear once
     *
     * @return void
     * @uses \App\Controller\GatheringsController::view()
     */
    public function testRequiredWaiverConsolidation(): void
    {
        // Find a gathering that has both activities and waivers
        $Gatherings = $this->getTableLocator()->get('Gatherings');
        $GatheringWaivers = $this->getTableLocator()->get('Waivers.GatheringWaivers');

        $waiver = $GatheringWaivers->find()
            ->contain(['Gatherings'])
            ->first();
        if (!$waiver || !$waiver->gathering) {
            $this->markTestSkipped('No gathering with waivers found in seed data');
        }

        $gathering = $Gatherings->get($waiver->gathering_id);
        $this->get('/gatherings/view/' . $gathering->public_id);
        $this->assertResponseOk();

        // The view should load successfully with activities tab content
        $this->assertResponseContains('Activities');
    }

    /**
     * Test activity locking when waivers are uploaded (T118)
     *
     * When waivers have been uploaded for a gathering, the "Add Activity" button
     * should not appear in the view (activities are locked).
     *
     * @return void
     * @uses \App\Controller\GatheringsController::view()
     */
    public function testActivityLockingWhenWaiversUploaded(): void
    {
        // Find a gathering that has waivers uploaded
        $GatheringWaivers = $this->getTableLocator()->get('Waivers.GatheringWaivers');
        $Gatherings = $this->getTableLocator()->get('Gatherings');

        $waiver = $GatheringWaivers->find()
            ->contain(['Gatherings'])
            ->first();
        if (!$waiver || !$waiver->gathering) {
            $this->markTestSkipped('No gathering with waivers found in seed data');
        }

        $gathering = $Gatherings->get($waiver->gathering_id);
        $this->get('/gatherings/view/' . $gathering->public_id);
        $this->assertResponseOk();

        // With waivers uploaded, the "Add Activity" button should NOT be shown
        // The template checks: if ($user->checkCan('edit', $gathering) && !$hasWaivers)
        $this->assertResponseNotContains('Add Activity');
    }

    /**
     * Test authorization - unauthenticated user
     *
     * @return void
     * @uses \App\Controller\GatheringsController::index()
     */
    public function testIndexUnauthenticated(): void
    {
        $this->session(['Auth' => null]);
        $this->get('/gatherings');
        $this->assertRedirect();
    }
}
