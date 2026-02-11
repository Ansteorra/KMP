<?php

declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\TestCase\Support\HttpIntegrationTestCase;

/**
 * Example Test Demonstrating Test Super User Authentication
 * 
 * This test class shows how to use authenticateAsSuperUser()
 * via HttpIntegrationTestCase to solve permission issues in tests.
 */
class ExampleSuperUserTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->authenticateAsSuperUser();
    }

    /**
     * Test that authentication is set up via HttpIntegrationTestCase
     *
     * @return void
     */
    public function testWithHelperTrait(): void
    {
        $this->get('/members');
        $this->assertResponseOk();
    }

    /**
     * Test that super user can access restricted resources
     *
     * @return void
     */
    public function testSuperUserAccessToRestrictedResource(): void
    {
        $this->get('/roles');
        $this->assertResponseOk();
    }

    /**
     * Test creating data as super user
     *
     * @return void
     */
    public function testCreateAsuperUser(): void
    {
        $data = [
            'name' => 'Test Role',
            'is_system' => false,
        ];

        $this->post('/roles/add', $data);
        $this->assertResponseSuccess();
    }
}
