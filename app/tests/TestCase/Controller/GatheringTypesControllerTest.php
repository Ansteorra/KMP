<?php

declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\TestCase\Controller\SuperUserAuthenticatedTrait;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * App\Controller\GatheringTypesController Test Case
 *
 * @uses \App\Controller\GatheringTypesController
 */
class GatheringTypesControllerTest extends TestCase
{
    use IntegrationTestTrait;
    use SuperUserAuthenticatedTrait;

    /**
     * Test index method
     *
     * @return void
     * @uses \App\Controller\GatheringTypesController::index()
     */
    public function testIndex(): void
    {
        $this->get('/gathering-types');
        $this->assertResponseOk();
        $this->assertResponseContains('Gathering Types');

        // Check that gathering types from fixture are displayed
        $this->assertResponseContains('Fighter Practice');
        $this->assertResponseContains('Arts &amp; Sciences Workshop');
    }

    /**
     * Test view method
     *
     * @return void
     * @uses \App\Controller\GatheringTypesController::view()
     */
    public function testView(): void
    {
        $this->get('/gathering-types/view/1');
        $this->assertResponseOk();
        $this->assertResponseContains('Fighter Practice');
        $this->assertResponseContains('Regular heavy and light armored combat practice');
    }

    /**
     * Test add method - GET
     *
     * @return void
     * @uses \App\Controller\GatheringTypesController::add()
     */
    public function testAddGet(): void
    {
        $this->get('/gathering-types/add');
        $this->assertResponseOk();
        $this->assertResponseContains('Add Gathering Type');
    }

    /**
     * Test add method - POST with valid data
     *
     * @return void
     * @uses \App\Controller\GatheringTypesController::add()
     */
    public function testAddPost(): void
    {
        $this->enableCsrfToken();
        $this->post('/gathering-types/add', [
            'name' => 'New Gathering Type',
            'description' => 'A new type of gathering',
            'clonable' => true,
        ]);
        $this->assertResponseSuccess();
        $this->assertRedirect(['action' => 'index']);

        // Verify the gathering type was created
        $GatheringTypes = $this->getTableLocator()->get('GatheringTypes');
        $query = $GatheringTypes->find()->where(['name' => 'New Gathering Type']);
        $this->assertEquals(1, $query->count());
    }

    /**
     * Test add method - POST with invalid data
     *
     * @return void
     * @uses \App\Controller\GatheringTypesController::add()
     */
    public function testAddPostInvalid(): void
    {
        $this->enableCsrfToken();
        $this->post('/gathering-types/add', [
            'name' => '', // Required field
            'description' => 'Missing name',
        ]);
        $this->assertResponseOk();
        $this->assertResponseContains('name');
    }

    /**
     * Test add method - POST with duplicate name
     *
     * @return void
     * @uses \App\Controller\GatheringTypesController::add()
     */
    public function testAddPostDuplicateName(): void
    {
        $this->enableCsrfToken();
        $this->post('/gathering-types/add', [
            'name' => 'Fighter Practice', // Already exists
            'description' => 'Duplicate name',
            'clonable' => true,
        ]);
        $this->assertResponseOk();
        $this->assertResponseContains('already exists');
    }

    /**
     * Test edit method - GET
     *
     * @return void
     * @uses \App\Controller\GatheringTypesController::edit()
     */
    public function testEditGet(): void
    {
        $this->get('/gathering-types/edit/1');
        $this->assertResponseOk();
        $this->assertResponseContains('Fighter Practice');
    }

    /**
     * Test edit method - POST with valid data
     *
     * @return void
     * @uses \App\Controller\GatheringTypesController::edit()
     */
    public function testEditPost(): void
    {
        $this->enableCsrfToken();
        $this->post('/gathering-types/edit/1', [
            'name' => 'Updated Fighter Practice',
            'description' => 'Updated description',
            'clonable' => false,
        ]);
        $this->assertResponseSuccess();
        $this->assertRedirect(['action' => 'view', 1]);

        // Verify the gathering type was updated
        $GatheringTypes = $this->getTableLocator()->get('GatheringTypes');
        $gatheringType = $GatheringTypes->get(1);
        $this->assertEquals('Updated Fighter Practice', $gatheringType->name);
        $this->assertFalse($gatheringType->clonable);
    }

    /**
     * Test edit with invalid ID
     *
     * @return void
     * @uses \App\Controller\GatheringTypesController::edit()
     */
    public function testEditWithInvalidId(): void
    {
        $this->get('/gathering-types/edit/999');
        $this->assertResponseError();
    }

    /**
     * Test delete method
     *
     * @return void
     * @uses \App\Controller\GatheringTypesController::delete()
     */
    public function testDelete(): void
    {
        $this->enableCsrfToken();
        $this->post('/gathering-types/delete/4'); // Archery Range Day - no gatherings
        $this->assertResponseSuccess();
        $this->assertRedirect(['action' => 'index']);

        // Verify the gathering type was deleted
        $GatheringTypes = $this->getTableLocator()->get('GatheringTypes');
        $query = $GatheringTypes->find()->where(['id' => 4]);
        $this->assertEquals(0, $query->count());
    }

    /**
     * Test delete with invalid ID
     *
     * @return void
     * @uses \App\Controller\GatheringTypesController::delete()
     */
    public function testDeleteWithInvalidId(): void
    {
        $this->enableCsrfToken();
        $this->post('/gathering-types/delete/999');
        $this->assertResponseError();
    }

    /**
     * Test delete fails if gathering type is referenced
     *
     * Note: This test requires a gathering type that has associated gatherings.
     * If no gatherings exist in fixtures, this test will be skipped.
     *
     * @return void
     * @uses \App\Controller\GatheringTypesController::delete()
     */
    public function testDeleteFailsIfInUse(): void
    {
        // First check if there are any gatherings
        $Gatherings = $this->getTableLocator()->get('Gatherings');
        $gatheringsCount = $Gatherings->find()->count();

        if ($gatheringsCount === 0) {
            $this->markTestSkipped('No gatherings exist to test cascade prevention');
        }

        // Try to delete a gathering type that has gatherings
        $gathering = $Gatherings->find()->first();
        $gatheringTypeId = $gathering->gathering_type_id;

        $this->enableCsrfToken();
        $this->post('/gathering-types/delete/' . $gatheringTypeId);

        // Should either redirect with error message or show error
        $this->assertResponseSuccess();
        $this->assertFlashMessage('Cannot delete gathering type that is being used by gatherings');

        // Verify the gathering type was NOT deleted
        $GatheringTypes = $this->getTableLocator()->get('GatheringTypes');
        $query = $GatheringTypes->find()->where(['id' => $gatheringTypeId]);
        $this->assertEquals(1, $query->count());
    }

    /**
     * Test authorization - unauthenticated user
     *
     * @return void
     * @uses \App\Controller\GatheringTypesController::index()
     */
    public function testIndexUnauthenticated(): void
    {
        $this->logout();
        $this->get('/gathering-types');
        $this->assertResponseError();
    }
}
