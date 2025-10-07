<?php

declare(strict_types=1);

namespace Template\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * Template\Controller\HelloWorldController Test Case
 *
 * This test demonstrates the testing patterns for KMP plugin controllers.
 * It uses CakePHP's TestCase with IntegrationTestTrait to test full HTTP 
 * request/response cycles.
 *
 * ## Testing Approach
 *
 * Integration tests should verify:
 * - HTTP responses are correct (status codes, redirects)
 * - Content appears in views
 * - Forms work correctly
 * - Authorization is enforced
 * - Database operations succeed
 *
 * ## CakePHP 5 Testing Pattern
 *
 * In CakePHP 5, integration tests extend TestCase and use IntegrationTestTrait
 * instead of extending IntegrationTestCase (which was used in CakePHP 4).
 *
 * ## Test Fixtures
 *
 * Use fixtures to provide test data. Define them in tests/Fixture/ directory.
 *
 * @uses \Template\Controller\HelloWorldController
 */
class HelloWorldControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected array $fixtures = [
        // 'plugin.Template.HelloWorldItems',
        // 'app.Members',
        // 'app.Branches',
    ];

    /**
     * Test index method
     *
     * @return void
     * @uses \Template\Controller\HelloWorldController::index()
     */
    public function testIndex(): void
    {
        // Test that index page loads successfully
        $this->get('/template/hello-world');

        // Check response is OK (200)
        $this->assertResponseOk();

        // Check that expected content appears
        $this->assertResponseContains('Hello World');
        $this->assertResponseContains('Hello World Items');
    }

    /**
     * Test index method with authentication
     *
     * @return void
     * @uses \Template\Controller\HelloWorldController::index()
     */
    public function testIndexAuthenticated(): void
    {
        // Create a mock session/user
        // $this->session(['Auth' => ['id' => 1, 'username' => 'testuser']]);

        $this->get('/template/hello-world');
        $this->assertResponseOk();
    }

    /**
     * Test view method
     *
     * @return void
     * @uses \Template\Controller\HelloWorldController::view()
     */
    public function testView(): void
    {
        // In a real test, you would:
        // 1. Create a fixture record
        // 2. Fetch that record's ID
        // 3. Request the view page with that ID

        // $this->get('/template/hello-world/view/1');
        // $this->assertResponseOk();
        // $this->assertResponseContains('Hello, World!');

        // For this template, we test with any ID
        $this->get('/template/hello-world/view/1');
        $this->assertResponseOk();
    }

    /**
     * Test add method GET
     *
     * @return void
     * @uses \Template\Controller\HelloWorldController::add()
     */
    public function testAddGet(): void
    {
        $this->get('/template/hello-world/add');
        $this->assertResponseOk();
        $this->assertResponseContains('Add Hello World Item');
    }

    /**
     * Test add method POST
     *
     * @return void
     * @uses \Template\Controller\HelloWorldController::add()
     */
    public function testAddPost(): void
    {
        // Prepare POST data
        $data = [
            'title' => 'Test Item',
            'description' => 'This is a test description',
        ];

        // Submit the form
        $this->post('/template/hello-world/add', $data);

        // Should redirect after successful save
        $this->assertRedirect(['action' => 'index']);

        // Check flash message
        $this->assertFlashMessage('This is a template example');

        // In a real test, verify the record was created:
        // $this->assertEquals(1, $this->HelloWorldItems->find()->where(['title' => 'Test Item'])->count());
    }

    /**
     * Test add method POST with invalid data
     *
     * @return void
     * @uses \Template\Controller\HelloWorldController::add()
     */
    public function testAddPostInvalid(): void
    {
        // In a real test, submit invalid data
        // $data = ['title' => '']; // Empty title should fail validation
        // $this->post('/template/hello-world/add', $data);

        // Should re-render form with errors
        // $this->assertResponseOk();
        // $this->assertResponseContains('is required');
    }

    /**
     * Test edit method GET
     *
     * @return void
     * @uses \Template\Controller\HelloWorldController::edit()
     */
    public function testEditGet(): void
    {
        $this->get('/template/hello-world/edit/1');
        $this->assertResponseOk();
        $this->assertResponseContains('Edit Hello World Item');
    }

    /**
     * Test edit method POST
     *
     * @return void
     * @uses \Template\Controller\HelloWorldController::edit()
     */
    public function testEditPost(): void
    {
        $data = [
            'title' => 'Updated Title',
            'description' => 'Updated description',
        ];

        $this->post('/template/hello-world/edit/1', $data);
        $this->assertRedirect(['action' => 'index']);
        $this->assertFlashMessage('updated in the database');
    }

    /**
     * Test delete method
     *
     * @return void
     * @uses \Template\Controller\HelloWorldController::delete()
     */
    public function testDelete(): void
    {
        // Delete requires POST
        $this->post('/template/hello-world/delete/1');

        $this->assertRedirect(['action' => 'index']);
        $this->assertFlashMessage('deleted from the database');

        // In a real test, verify the record was deleted:
        // $this->assertEquals(0, $this->HelloWorldItems->find()->where(['id' => 1])->count());
    }

    /**
     * Test delete method with GET (should fail)
     *
     * @return void
     * @uses \Template\Controller\HelloWorldController::delete()
     */
    public function testDeleteWithGet(): void
    {
        // DELETE action should not allow GET requests
        $this->get('/template/hello-world/delete/1');
        $this->assertResponseCode(405); // Method Not Allowed
    }

    /**
     * Test authorization - unauthenticated user
     *
     * @return void
     */
    public function testAuthorizationUnauthenticated(): void
    {
        // In a real test with proper authorization:
        // Actions requiring authentication should redirect to login

        // $this->get('/template/hello-world/add');
        // $this->assertRedirect(['controller' => 'Users', 'action' => 'login']);
    }

    /**
     * Test authorization - unauthorized user
     *
     * @return void
     */
    public function testAuthorizationUnauthorized(): void
    {
        // In a real test:
        // Login as a user without proper permissions
        // Attempt to access restricted action
        // Should get forbidden response

        // $this->session(['Auth' => ['id' => 999, 'username' => 'limiteduser']]);
        // $this->get('/template/hello-world/admin-only-action');
        // $this->assertResponseCode(403); // Forbidden
    }

    /**
     * Example: Test JSON response
     *
     * @return void
     */
    public function testJsonResponse(): void
    {
        // If your controller has JSON endpoints:
        // $this->get('/template/hello-world/index.json');
        // $this->assertResponseOk();
        // $this->assertResponseContains('"items"');

        // $result = json_decode((string)$this->_response->getBody(), true);
        // $this->assertIsArray($result);
        // $this->assertArrayHasKey('items', $result);
    }

    /**
     * Example: Test with query parameters
     *
     * @return void
     */
    public function testWithQueryParameters(): void
    {
        // Test pagination, filtering, etc.
        // $this->get('/template/hello-world?page=2&sort=title&direction=asc');
        // $this->assertResponseOk();
    }
}
