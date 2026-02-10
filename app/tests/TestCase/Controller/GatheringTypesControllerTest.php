<?php

declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\TestCase\Support\HttpIntegrationTestCase;

/**
 * App\Controller\GatheringTypesController Test Case
 *
 * @uses \App\Controller\GatheringTypesController
 */
class GatheringTypesControllerTest extends HttpIntegrationTestCase
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
     * @uses \App\Controller\GatheringTypesController::index()
     */
    public function testIndex(): void
    {
        $this->get('/gathering-types');
        $this->assertResponseOk();
        $this->assertResponseContains('Gathering Types');
    }

    /**
     * Test view method
     *
     * @return void
     * @uses \App\Controller\GatheringTypesController::view()
     */
    public function testView(): void
    {
        $GatheringTypes = $this->getTableLocator()->get('GatheringTypes');
        $gatheringType = $GatheringTypes->find()->first();
        if (!$gatheringType) {
            $this->markTestSkipped('No gathering type found in seed data');
        }
        $this->get('/gathering-types/view/' . $gatheringType->id);
        $this->assertResponseOk();
        $this->assertResponseContains(h($gatheringType->name));
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
        $uniqueName = 'New Gathering Type ' . time();
        $this->post('/gathering-types/add', [
            'name' => $uniqueName,
            'description' => 'A new type of gathering',
            'clonable' => true,
        ]);
        $this->assertResponseSuccess();

        // Verify the gathering type was created
        $GatheringTypes = $this->getTableLocator()->get('GatheringTypes');
        $query = $GatheringTypes->find()->where(['name' => $uniqueName]);
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
        $GatheringTypes = $this->getTableLocator()->get('GatheringTypes');
        $existing = $GatheringTypes->find()->first();
        if (!$existing) {
            $this->markTestSkipped('No gathering type found in seed data');
        }
        $this->enableCsrfToken();
        $this->post('/gathering-types/add', [
            'name' => $existing->name,
            'description' => 'Duplicate name',
            'clonable' => true,
        ]);
        $this->assertResponseOk();
    }

    /**
     * Test edit method - GET
     *
     * @return void
     * @uses \App\Controller\GatheringTypesController::edit()
     */
    public function testEditGet(): void
    {
        $GatheringTypes = $this->getTableLocator()->get('GatheringTypes');
        $gatheringType = $GatheringTypes->find()->first();
        if (!$gatheringType) {
            $this->markTestSkipped('No gathering type found in seed data');
        }
        $this->get('/gathering-types/edit/' . $gatheringType->id);
        $this->assertResponseOk();
    }

    /**
     * Test edit method - POST with valid data
     *
     * @return void
     * @uses \App\Controller\GatheringTypesController::edit()
     */
    public function testEditPost(): void
    {
        // Create a fresh type to edit so we don't affect other tests
        $GatheringTypes = $this->getTableLocator()->get('GatheringTypes');
        $newType = $GatheringTypes->newEntity([
            'name' => 'Editable Type ' . time(),
            'description' => 'Original desc',
            'clonable' => true,
        ]);
        $GatheringTypes->save($newType);

        $this->enableCsrfToken();
        $this->post('/gathering-types/edit/' . $newType->id, [
            'name' => 'Updated Type Name',
            'description' => 'Updated description',
            'clonable' => false,
        ]);
        $this->assertResponseSuccess();
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
        // Create a gathering type specifically for deletion
        $GatheringTypes = $this->getTableLocator()->get('GatheringTypes');
        $newType = $GatheringTypes->newEntity([
            'name' => 'Deletable Type ' . time(),
            'description' => 'To be deleted',
            'clonable' => false,
        ]);
        $GatheringTypes->save($newType);

        $this->enableCsrfToken();
        $this->post('/gathering-types/delete/' . $newType->id);
        $this->assertResponseSuccess();
        $this->assertRedirect(['action' => 'index']);
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

        // Should redirect with error
        $this->assertResponseSuccess();

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
        $this->session(['Auth' => null]);
        $this->get('/gathering-types');
        $this->assertRedirect();
    }
}
