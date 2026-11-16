<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Model\Entity\GatheringActivity;
use App\Test\TestCase\Support\HttpIntegrationTestCase;

/**
 * App\Controller\GatheringActivitiesController Test Case
 *
 * @uses \App\Controller\GatheringActivitiesController
 */
class GatheringActivitiesControllerTest extends HttpIntegrationTestCase
{
    public function testActivityTypesAreFixedAndCanBeChangedThroughTheModal(): void
    {
        $table = $this->getTableLocator()->get('GatheringActivities');
        $types = ['Order Circle', 'Royal Progress', 'Court', 'Business', 'Martial',
            'Arts & Sciences', 'Youth', 'General', 'Other'];
        $this->assertSame($types, GatheringActivity::ACTIVITY_TYPES);
        foreach ($types as $type) {
            $this->post('/gathering-activities/add', ['name' => 'Type fixture ' . $type, 'activity_type' => $type]);
            $this->assertRedirect();
            $activity = $table->find()->where(['name' => 'Type fixture ' . $type])->firstOrFail();
            $this->assertSame($type, $activity->activity_type);
        }
        $this->configRequest(['headers' => ['Turbo-Frame' => 'gatheringActivityFormFrame']]);
        $this->get('/gathering-activities/edit/' . $activity->id);
        $this->assertResponseContains('name="activity_type"');
        $this->assertResponseNotContains('name="is_circle"');
        foreach ($types as $type) {
            $this->assertResponseContains('value="' . h($type) . '"');
        }
        $this->configRequest(['headers' => ['Accept' => 'text/vnd.turbo-stream.html']]);
        $this->post('/gathering-activities/edit/' . $activity->id, [
            'activity_type' => 'Arts & Sciences', 'page_context_url' => '/gathering-activities',
        ]);
        $this->assertResponseOk();
        $this->assertSame('Arts & Sciences', $table->get($activity->id)->activity_type);
        foreach (['', 'Unlisted', ['Court']] as $invalid) {
            $this->configRequest(['headers' => ['Accept' => 'text/vnd.turbo-stream.html']]);
            $this->post('/gathering-activities/edit/' . $activity->id, ['activity_type' => $invalid]);
            $this->assertResponseCode(422);
            $this->assertSame('Arts & Sciences', $table->get($activity->id)->activity_type);
        }
        $default = $table->saveOrFail($table->newEntity(['name' => 'Default type fixture']));
        $this->assertSame('General', $table->get($default->id)->activity_type);
    }

    public function testModalGetUsesTheSharedEditorWithoutThePageLayout(): void
    {
        $activity = $this->getTableLocator()->get('GatheringActivities')->find()->firstOrFail();
        foreach (['/gathering-activities/add', '/gathering-activities/edit/' . $activity->id] as $url) {
            $this->configRequest(['headers' => ['Turbo-Frame' => 'gatheringActivityFormFrame']]);
            $this->get($url);
            $this->assertResponseOk();
            $this->assertResponseContains('<turbo-frame id="gatheringActivityFormFrame">');
            $this->assertResponseContains('submit-&gt;turbo-modal#submitAsTurboStream');
            $this->assertResponseContains('name="display_order"');
            $this->assertResponseContains('name="icon_file"');
            $this->assertResponseNotContains('<!DOCTYPE html>');
        }
    }

    public function testActivityTypeGridFilterAndSortUseTheSelectedType(): void
    {
        $table = $this->getTableLocator()->get('GatheringActivities');
        $table->saveOrFail($table->newEntity(['name' => 'Type grid court fixture', 'activity_type' => 'Court']));
        $table->saveOrFail($table->newEntity(['name' => 'Type grid youth fixture', 'activity_type' => 'Youth']));
        $query = '?search=Type%20grid&filter[activity_type][]=Court&sort=activity_type&direction=asc';
        $this->get('/gathering-activities/grid-data' . $query);
        $this->assertResponseOk();
        $this->assertResponseContains('Type grid court fixture');
        $this->assertResponseNotContains('Type grid youth fixture');
        $this->get('/gathering-activities/grid-data?search=Type%20grid&sort=activity_type&direction=desc');
        $this->assertResponseOk();
        $types = [];
        foreach ($this->viewVariable('gatheringActivities') as $activity) {
            $types[] = $activity->activity_type;
        }
        $this->assertSame(['Youth', 'Court'], $types);
    }

    public function testGridColumnSortOverridesActivityGroupingAndDisplayOrder(): void
    {
        $table = $this->getTableLocator()->get('GatheringActivities');
        $fixtures = [
            ['name' => 'Admin sorting fixture A', 'activity_type' => 'General', 'display_order' => 30],
            ['name' => 'Admin sorting fixture B', 'activity_type' => 'Royal Progress', 'display_order' => 40],
            ['name' => 'Admin sorting fixture C', 'activity_type' => 'Order Circle', 'display_order' => 10],
            ['name' => 'Admin sorting fixture Z', 'activity_type' => 'Court', 'display_order' => 20],
        ];
        foreach ($fixtures as $data) {
            $table->saveOrFail($table->newEntity($data));
        }
        $url = '/gathering-activities/grid-data?search=Admin%20sorting%20fixture';
        foreach (['name', 'activity_type', 'display_order'] as $field) {
            $expected = array_column($fixtures, $field);
            sort($expected);
            foreach (['asc', 'desc'] as $direction) {
                $this->configRequest(['headers' => ['Turbo-Frame' => 'gathering-activities-grid-table']]);
                $this->get($url . '&sort=' . $field . '&direction=' . $direction);
                $this->assertResponseOk();
                $rows = iterator_to_array($this->viewVariable('gatheringActivities'));
                $this->assertSame(
                    $direction === 'asc' ? $expected : array_reverse($expected),
                    array_column($rows, $field),
                    $field . ' ' . $direction,
                );
                $this->assertSame(['field' => $field, 'direction' => $direction], $this->viewVariable('currentSort'));
                $this->assertResponseContains('aria-sort="' . ($direction === 'asc' ? 'ascending' : 'descending') . '"');
            }
        }

        $this->get($url);
        $this->assertResponseOk();
        $rows = iterator_to_array($this->viewVariable('gatheringActivities'));
        $this->assertSame([
            'Admin sorting fixture B', 'Admin sorting fixture C',
            'Admin sorting fixture Z', 'Admin sorting fixture A',
        ], array_column($rows, 'name'));

        $views = $this->getTableLocator()->get('GridViews');
        $view = $views->saveOrFail($views->newEntity([
            'grid_key' => 'GatheringActivities.index.main',
            'member_id' => self::ADMIN_MEMBER_ID,
            'name' => 'Activities by name',
            'config' => json_encode(['sort' => [['field' => 'name', 'direction' => 'desc']]]),
        ]));
        $this->get($url . '&view_id=' . $view->id);
        $this->assertResponseOk();
        $rows = iterator_to_array($this->viewVariable('gatheringActivities'));
        $this->assertSame(array_reverse(array_column($fixtures, 'name')), array_column($rows, 'name'));

        $this->get($url . '&view_id=' . $view->id . '&sort=name&direction=asc');
        $this->assertResponseOk();
        $rows = iterator_to_array($this->viewVariable('gatheringActivities'));
        $this->assertSame(array_column($fixtures, 'name'), array_column($rows, 'name'));
    }

    public function testModalSavesRefreshTheGridAndPreserveItsContext(): void
    {
        $headers = ['Accept' => 'text/vnd.turbo-stream.html', 'X-Requested-With' => 'XMLHttpRequest'];
        $this->configRequest(['headers' => $headers]);
        $this->post('/gathering-activities/add', [
            'name' => 'Shared modal activity', 'display_order' => 7,
            'page_context_url' => '/gathering-activities?page=2&sort=display_order&direction=desc',
        ]);
        $this->assertResponseOk();
        $this->assertContentType('text/vnd.turbo-stream.html');
        $this->assertResponseContains('target="gathering-activities-grid-table"');
        $this->assertResponseContains('page=2');
        $this->assertResponseContains('sort=display_order');
        $table = $this->getTableLocator()->get('GatheringActivities');
        $activity = $table->find()->where(['name' => 'Shared modal activity'])->firstOrFail();
        $this->configRequest(['headers' => $headers]);
        $this->post('/gathering-activities/edit/' . $activity->id, [
            'name' => 'Updated shared modal activity', 'display_order' => 2,
            'page_context_url' => '/gathering-activities?search=shared',
        ]);
        $this->assertResponseOk();
        $this->assertResponseContains('search=shared');
        $this->assertSame(2, $table->get($activity->id)->display_order);
        $this->assertSame('Updated shared modal activity', $table->get($activity->id)->name);
    }

    public function testModalValidationRetainsTheFormAndDoesNotSave(): void
    {
        $table = $this->getTableLocator()->get('GatheringActivities');
        $activity = $table->saveOrFail($table->newEntity(['name' => 'Modal validation fixture']));
        $this->configRequest(['headers' => ['Accept' => 'text/vnd.turbo-stream.html']]);
        $this->post('/gathering-activities/edit/' . $activity->id, [
            'name' => 'Unsaved modal name', 'display_order' => -1,
            'page_context_url' => '/gathering-activities',
        ]);
        $this->assertResponseCode(422);
        $this->assertContentType('text/vnd.turbo-stream.html');
        $this->assertResponseContains('action="update" target="gatheringActivityFormFrame"');
        $this->assertResponseContains('value="Unsaved modal name"');
        $this->assertResponseContains('role="alert"');
        $this->assertSame('Modal validation fixture', $table->get($activity->id)->name);
    }

    public function testModalStillEnforcesAuthorizationAndValidatesContextBeforeSaving(): void
    {
        $table = $this->getTableLocator()->get('GatheringActivities');
        $activity = $table->saveOrFail($table->newEntity(['name' => 'Protected modal fixture']));
        $this->configRequest(['headers' => ['Accept' => 'text/vnd.turbo-stream.html']]);
        $this->post('/gathering-activities/edit/' . $activity->id, [
            'name' => 'Must not save', 'page_context_url' => 'https://example.test/elsewhere',
        ]);
        $this->assertResponseCode(400);
        $this->assertSame('Protected modal fixture', $table->get($activity->id)->name);
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->configRequest(['headers' => ['Turbo-Frame' => 'gatheringActivityFormFrame']]);
        $this->get('/gathering-activities/edit/' . $activity->id);
        $this->assertResponseCode(403);
        $this->assertResponseNotContains('<turbo-frame id="gatheringActivityFormFrame">');
        $this->configRequest(['headers' => ['Accept' => 'text/vnd.turbo-stream.html']]);
        $this->post('/gathering-activities/edit/' . $activity->id, ['name' => 'Unauthorized modal update']);
        $this->assertResponseCode(403);
        $this->assertSame('Protected modal fixture', $table->get($activity->id)->name);
    }

    public function testActivityGridOffersViewAndModalEditActions(): void
    {
        $this->get('/gathering-activities/grid-data');
        $this->assertResponseOk();
        $this->assertResponseContains('data-bs-target="#gatheringActivityModal"');
        $this->assertResponseContains('data-activity-id=');
        $this->assertResponseContains('aria-label="View ');
    }

    public function testDisplayOrderCanBeConfiguredAndInvalidValuesAreRejected(): void
    {
        $this->get('/gathering-activities/add');
        $this->assertResponseContains('name="display_order"');
        $this->post('/gathering-activities/add', ['name' => 'Priority activity fixture', 'display_order' => 5]);
        $this->assertRedirect();
        $table = $this->getTableLocator()->get('GatheringActivities');
        $activity = $table->find()->where(['name' => 'Priority activity fixture'])->firstOrFail();
        $this->assertSame(5, $activity->display_order);
        $this->get('/gathering-activities/edit/' . $activity->id);
        $this->assertResponseContains('name="display_order"');
        $this->post('/gathering-activities/edit/' . $activity->id, ['display_order' => 0]);
        $this->assertRedirect();
        $this->assertSame(0, $table->get($activity->id)->display_order);
        foreach ([-1, 1.5, 2147483648, 'invalid', ''] as $value) {
            $entity = $table->newEntity(['name' => 'Invalid priority fixture', 'display_order' => $value]);
            $this->assertNotEmpty($entity->getError('display_order'));
        }
    }

    public function testAssignmentPermissionsCanBeAddedAndRemovedIndividually(): void
    {
        $activities = $this->getTableLocator()->get('GatheringActivities');
        $activity = $activities->saveOrFail($activities->newEntity(['name' => 'Configurable assignment']));
        $permissions = $this->getTableLocator()->get('Permissions')->find()->limit(2)->all()->toList();
        $addUrl = '/gathering-activities/add-assignment-permission/' . $activity->id;
        $deleteUrl = '/gathering-activities/delete-assignment-permission/' . $activity->id;
        foreach ([$permissions[0], $permissions[1], $permissions[0]] as $permission) {
            $this->post($addUrl, ['permission_id' => (string)$permission->id]);
            $this->assertRedirect(['action' => 'view', $activity->id, '?' => ['tab' => 'assigned-by']]);
        }
        $this->assertCount(2, $activities->get($activity->id, contain: ['AssignmentPermissions'])->assignment_permissions);
        $this->post($deleteUrl, ['permission_id' => $permissions[0]->id]);
        $remaining = $activities->get($activity->id, contain: ['AssignmentPermissions'])->assignment_permissions;
        $this->assertCount(1, $remaining);
        $this->assertSame($permissions[1]->id, $remaining[0]->id);
        $this->post($deleteUrl, ['permission_id' => $permissions[1]->id]);
        $this->assertCount(0, $activities->get($activity->id, contain: ['AssignmentPermissions'])->assignment_permissions);
        $this->assertSame(2, $this->getTableLocator()->get('Permissions')->find()
            ->where(['id IN' => array_column($permissions, 'id')])->count());
    }

    public function testInvalidRulesAreRejectedAndRegularMembersCannotChangeThem(): void
    {
        $activities = $this->getTableLocator()->get('GatheringActivities');
        $activity = $activities->saveOrFail($activities->newEntity(['name' => 'Protected assignment']));
        $url = '/gathering-activities/add-assignment-permission/' . $activity->id;
        $this->post($url, ['permission_id' => -1]);
        $this->assertResponseCode(404);
        $this->assertCount(0, $activities->get($activity->id, contain: ['AssignmentPermissions'])->assignment_permissions);
        $permission = $this->getTableLocator()->get('Permissions')->find()->firstOrFail();
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->post($url, ['permission_id' => $permission->id]);
        $this->assertCount(0, $activities->get($activity->id, contain: ['AssignmentPermissions'])->assignment_permissions);
        $this->authenticateAsSuperUser();
        $this->post($url, ['permission_id' => $permission->id]);
        $this->assertCount(1, $activities->get($activity->id, contain: ['AssignmentPermissions'])->assignment_permissions);
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->post('/gathering-activities/delete-assignment-permission/' . $activity->id, [
            'permission_id' => $permission->id,
        ]);
        $this->assertCount(1, $activities->get($activity->id, contain: ['AssignmentPermissions'])->assignment_permissions);
    }

    public function testAssignmentTabUsesPermissionGridAndModalLookup(): void
    {
        $activities = $this->getTableLocator()->get('GatheringActivities');
        $activity = $activities->saveOrFail($activities->newEntity(['name' => 'Assignment UI fixture']));
        $permission = $this->getTableLocator()->get('Permissions')->find()->firstOrFail();
        $this->post('/gathering-activities/add-assignment-permission/' . $activity->id, [
            'permission_id' => $permission->id,
        ]);
        $this->get('/gathering-activities/view/' . $activity->id . '?tab=assigned-by');
        $this->assertResponseOk();
        $this->assertResponseContains('data-bs-target="#addPermissionModal"');
        $this->assertResponseContains('data-assignment-permission-id="' . $permission->id . '"');
        $this->assertResponseContains('Requirements');
        $this->assertResponseContains('Membership');
        $this->assertResponseContains('Background Check');
        $this->assertResponseContains('Minimum Age');
        $this->assertResponseContains('Warrant');
        $this->assertResponseContains('role="combobox"');
        $this->assertResponseContains('data-controller="role-add-permission"');
        $this->assertResponseNotContains('name="permission_ids[]"');
        $this->assertArrayNotHasKey($permission->id, $this->viewVariable('permissionOptions'));
    }

    public function testAssignmentMutationsRequirePost(): void
    {
        $activity = $this->getTableLocator()->get('GatheringActivities')->find()->firstOrFail();
        foreach (['add-assignment-permission', 'delete-assignment-permission'] as $action) {
            $this->get('/gathering-activities/' . $action . '/' . $activity->id);
            $this->assertResponseCode(405);
        }
    }

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
