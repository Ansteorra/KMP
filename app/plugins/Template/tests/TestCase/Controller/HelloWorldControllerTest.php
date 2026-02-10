<?php

declare(strict_types=1);

namespace Template\Test\TestCase\Controller;

use App\Test\TestCase\Support\PluginIntegrationTestCase;

/**
 * Template\Controller\HelloWorldController Test Case
 *
 * @uses \Template\Controller\HelloWorldController
 */
class HelloWorldControllerTest extends PluginIntegrationTestCase
{
    protected const PLUGIN_NAME = 'Template';

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
     * @uses \Template\Controller\HelloWorldController::index()
     */
    public function testIndex(): void
    {
        $this->get('/template/hello-world');
        $this->assertResponseOk();
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
        $this->get('/template/hello-world');
        $this->assertResponseOk();
        $this->assertResponseContains('Hello World');
    }

    /**
     * Test view method
     *
     * @return void
     * @uses \Template\Controller\HelloWorldController::view()
     */
    public function testView(): void
    {
        $this->get('/template/hello-world/view/1');
        $this->assertResponseOk();
        $this->assertResponseContains('Hello, World!');
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
        $data = [
            'title' => 'Test Item',
            'description' => 'This is a test description',
        ];

        $this->post('/template/hello-world/add', $data);
        $this->assertRedirect(['action' => 'index']);

        $flash = $_SESSION['Flash']['flash'] ?? [];
        $this->assertNotEmpty($flash, 'Should have flash message');
        $this->assertStringContainsString('template example', $flash[0]['message']);
    }

    /**
     * Test add method POST with invalid data
     *
     * @return void
     * @uses \Template\Controller\HelloWorldController::add()
     */
    public function testAddPostInvalid(): void
    {
        // The template controller has no real model — all POSTs succeed.
        // In a real plugin, empty/invalid data would fail validation and
        // re-render the add form (no redirect). This test documents the
        // template's pass-through behavior as a reference.
        $data = [];

        $this->post('/template/hello-world/add', $data);
        $this->assertRedirect(['action' => 'index']);

        $flash = $_SESSION['Flash']['flash'] ?? [];
        $this->assertNotEmpty($flash, 'Empty POST should still produce a flash message');
        $this->assertStringContainsString('template example', $flash[0]['message']);
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

        $flash = $_SESSION['Flash']['flash'] ?? [];
        $this->assertNotEmpty($flash, 'Should have flash message');
        $this->assertStringContainsString('updated in the database', $flash[0]['message']);
    }

    /**
     * Test delete method
     *
     * @return void
     * @uses \Template\Controller\HelloWorldController::delete()
     */
    public function testDelete(): void
    {
        $this->post('/template/hello-world/delete/1');
        $this->assertRedirect(['action' => 'index']);

        $flash = $_SESSION['Flash']['flash'] ?? [];
        $this->assertNotEmpty($flash, 'Should have flash message');
        $this->assertStringContainsString('deleted from the database', $flash[0]['message']);
    }

    /**
     * Test delete method with GET (should fail)
     *
     * @return void
     * @uses \Template\Controller\HelloWorldController::delete()
     */
    public function testDeleteWithGet(): void
    {
        $this->get('/template/hello-world/delete/1');
        $this->assertResponseCode(405);
    }

    /**
     * Test authorization - unauthenticated user
     *
     * @return void
     */
    public function testAuthorizationUnauthenticated(): void
    {
        // Clear the super user session set in setUp to simulate no login
        $this->session(['Auth' => null]);
        $this->get('/template/hello-world');
        $this->assertRedirect();
    }

    /**
     * Test authorization - unauthorized user
     *
     * @return void
     */
    public function testAuthorizationUnauthorized(): void
    {
        // Authenticate as Agatha (non-admin member with no Template plugin permissions).
        // authorizeCurrentUrl() passes an array to the policy. BasePolicy::before()
        // handles array resources via _hasPolicyForUrl(), returning false for users
        // without the required permission. This results in a redirect (302).
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->get('/template/hello-world');
        $this->assertRedirect();
    }

    /**
     * Test with query parameters
     *
     * @return void
     */
    public function testWithQueryParameters(): void
    {
        // Verify query params don't cause errors even without pagination support
        $this->get('/template/hello-world?page=2&sort=title&direction=asc');
        $this->assertResponseOk();
        $this->assertResponseContains('Hello World');
    }
}
