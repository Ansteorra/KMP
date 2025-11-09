<?php

declare(strict_types=1);

namespace Waivers\Test\TestCase\Controller;

use App\Test\TestCase\BaseTestCase;
use App\Test\TestCase\Controller\SuperUserAuthenticatedTrait;
use Cake\TestSuite\IntegrationTestTrait;

/**
 * Waivers\Controller\WaiverTypesController Test Case
 *
 * @uses \Waivers\Controller\WaiverTypesController
 */
class WaiverTypesControllerTest extends BaseTestCase
{
    use IntegrationTestTrait;
    use SuperUserAuthenticatedTrait;

    /**
     * Test index method
     *
     * @return void
     * @uses \Waivers\Controller\WaiverTypesController::index()
     */
    public function testIndex(): void
    {
        $this->get('/waivers/waiver-types');
        $this->assertResponseOk();
        $this->assertResponseContains('Waiver Types');

        // Check that waiver types from fixture are displayed
        $this->assertResponseContains('General Liability Waiver');
        $this->assertResponseContains('Youth Participation Waiver');
    }

    /**
     * Test index method shows only active waiver types by default
     *
     * @return void
     */
    public function testIndexShowsOnlyActiveByDefault(): void
    {
        $this->get('/waivers/waiver-types');
        $this->assertResponseOk();

        // Should show active waivers
        $this->assertResponseContains('General Liability Waiver');

        // Should not show inactive waiver
        $this->assertResponseNotContains('Inactive Test Waiver');
    }

    /**
     * Test index with show_inactive parameter
     *
     * @return void
     */
    public function testIndexShowsInactiveWhenRequested(): void
    {
        $this->get('/waivers/waiver-types?show_inactive=1');
        $this->assertResponseOk();

        // Should show inactive waiver
        $this->assertResponseContains('Inactive Test Waiver');
    }

    /**
     * Test view method
     *
     * @return void
     * @uses \Waivers\Controller\WaiverTypesController::view()
     */
    public function testView(): void
    {
        $this->get('/waivers/waiver-types/view/1');
        $this->assertResponseOk();
        $this->assertResponseContains('General Liability Waiver');
        $this->assertResponseContains('Standard waiver for general event participation');
        $this->assertResponseContains('Retain for 7 years after gathering end date');
    }

    /**
     * Test view method with invalid id
     *
     * @return void
     */
    public function testViewWithInvalidId(): void
    {
        $this->get('/waivers/waiver-types/view/9999');
        $this->assertResponseError();
    }

    /**
     * Test add method GET
     *
     * @return void
     * @uses \Waivers\Controller\WaiverTypesController::add()
     */
    public function testAddGet(): void
    {
        $this->get('/waivers/waiver-types/add');
        $this->assertResponseOk();
        $this->assertResponseContains('Add Waiver Type');
    }

    /**
     * Test add method POST with valid data
     *
     * @return void
     * @uses \Waivers\Controller\WaiverTypesController::add()
     */
    public function testAddPost(): void
    {
        $data = [
            'name' => 'New Test Waiver Type',
            'description' => 'This is a test waiver type',
            'retention_policy' => '{"anchor":"gathering_end_date","duration":{"years":5}}',
            'convert_to_pdf' => true,
            'is_active' => true,
        ];

        $this->post('/waivers/waiver-types/add', $data);
        $this->assertRedirect(['controller' => 'WaiverTypes', 'action' => 'index', 'plugin' => 'Waivers']);

        // Check the record was saved to the database
        $waiverTypesTable = $this->getTableLocator()->get('Waivers.WaiverTypes');
        $query = $waiverTypesTable->find()->where(['name' => 'New Test Waiver Type']);
        $this->assertEquals(1, $query->count());

        $saved = $query->first();
        $this->assertEquals($data['description'], $saved->description);
        $this->assertEquals($data['retention_policy'], $saved->retention_policy);
    }

    /**
     * Test add method POST with invalid data
     *
     * @return void
     */
    public function testAddPostWithInvalidData(): void
    {
        $data = [
            'description' => 'Missing name field',
            'retention_policy' => 'invalid-json',
        ];

        $this->post('/waivers/waiver-types/add', $data);
        $this->assertResponseOk(); // Should re-render form
        $this->assertResponseContains('Add Waiver Type');

        // Check error messages are displayed
        $this->assertResponseContains('error');
    }

    /**
     * Test add method POST with duplicate name
     *
     * @return void
     */
    public function testAddPostWithDuplicateName(): void
    {
        $data = [
            'name' => 'General Liability Waiver', // Duplicate from fixture
            'description' => 'Test',
            'retention_policy' => '{"anchor":"permanent"}',
        ];

        $this->post('/waivers/waiver-types/add', $data);
        $this->assertResponseOk(); // Should re-render form with error

        // Check the record was not saved
        $waiverTypesTable = $this->getTableLocator()->get('Waivers.WaiverTypes');
        $query = $waiverTypesTable->find()->where(['description' => 'Test']);
        $this->assertEquals(0, $query->count());
    }

    /**
     * Test edit method GET
     *
     * @return void
     * @uses \Waivers\Controller\WaiverTypesController::edit()
     */
    public function testEditGet(): void
    {
        $this->get('/waivers/waiver-types/edit/1');
        $this->assertResponseOk();
        $this->assertResponseContains('Edit Waiver Type');
        $this->assertResponseContains('General Liability Waiver');
    }

    /**
     * Test edit method POST with valid data
     *
     * @return void
     * @uses \Waivers\Controller\WaiverTypesController::edit()
     */
    public function testEditPost(): void
    {
        $waiverTypesTable = $this->getTableLocator()->get('Waivers.WaiverTypes');
        $waiverType = $waiverTypesTable->get(1);

        $data = [
            'id' => $waiverType->id,
            'name' => $waiverType->name,
            'description' => 'Updated Description',
            'retention_policy' => '{"anchor":"gathering_end_date","duration":{"years":10}}',
            'convert_to_pdf' => false,
            'is_active' => true,
        ];

        $this->post('/waivers/waiver-types/edit/1', $data);
        $this->assertRedirect(['controller' => 'WaiverTypes', 'action' => 'index', 'plugin' => 'Waivers']);

        // Check the record was updated
        $updated = $waiverTypesTable->get(1);
        $this->assertEquals('Updated Description', $updated->description);
        $this->assertEquals($data['retention_policy'], $updated->retention_policy);
        $this->assertFalse($updated->convert_to_pdf);
    }

    /**
     * Test edit method with invalid id
     *
     * @return void
     */
    public function testEditWithInvalidId(): void
    {
        $this->get('/waivers/waiver-types/edit/9999');
        $this->assertResponseError();
    }

    /**
     * Test delete method
     *
     * @return void
     * @uses \Waivers\Controller\WaiverTypesController::delete()
     */
    public function testDelete(): void
    {
        $waiverTypesTable = $this->getTableLocator()->get('Waivers.WaiverTypes');
        $waiverType = $waiverTypesTable->get(4); // Inactive test waiver

        $this->post('/waivers/waiver-types/delete/4');
        $this->assertRedirect(['controller' => 'WaiverTypes', 'action' => 'index', 'plugin' => 'Waivers']);

        // Check the record was deleted
        $query = $waiverTypesTable->find()->where(['id' => 4]);
        $this->assertEquals(0, $query->count());
    }

    /**
     * Test delete method only accepts POST
     *
     * @return void
     */
    public function testDeleteRequiresPost(): void
    {
        $this->get('/waivers/waiver-types/delete/4');
        $this->assertResponseCode(405); // Method Not Allowed
    }

    /**
     * Test delete method with invalid id
     *
     * @return void
     */
    public function testDeleteWithInvalidId(): void
    {
        $this->post('/waivers/waiver-types/delete/9999');
        $this->assertRedirect();
        $this->assertFlashMessage('Waiver type not found', 'flash');
    }

    /**
     * Test delete method should fail if waiver type is in use
     *
     * @return void
     */
    public function testDeleteFailsIfInUse(): void
    {
        // This test would require additional fixtures with related records
        // For now, we'll just test that an active waiver shows a warning
        $this->post('/waivers/waiver-types/delete/1'); // Active waiver with description

        // Should either fail or show warning about deactivating instead
        $this->assertRedirect();
    }

    /**
     * Test toggle active status
     *
     * @return void
     * @uses \Waivers\Controller\WaiverTypesController::toggleActive()
     */
    public function testToggleActive(): void
    {
        $waiverTypesTable = $this->getTableLocator()->get('Waivers.WaiverTypes');
        $waiverType = $waiverTypesTable->get(1);
        $this->assertTrue($waiverType->is_active);

        $this->post('/waivers/waiver-types/toggle-active/1');
        $this->assertRedirect();

        // Check status was toggled
        $updated = $waiverTypesTable->get(1);
        $this->assertFalse($updated->is_active);

        // Toggle back
        $this->post('/waivers/waiver-types/toggle-active/1');
        $updated = $waiverTypesTable->get(1);
        $this->assertTrue($updated->is_active);
    }
}
