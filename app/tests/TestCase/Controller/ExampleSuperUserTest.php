<?php

declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\TestCase\BaseTestCase;
use Cake\TestSuite\IntegrationTestTrait;

/**
 * Example Test Demonstrating Test Super User Authentication
 * 
 * This test class shows how to use the SuperUserAuthenticatedTrait
 * to solve permission issues in tests.
 */
class ExampleSuperUserTest extends BaseTestCase
{
    use IntegrationTestTrait;
    use SuperUserAuthenticatedTrait;

    /**
     * Test that authentication is set up via trait
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
