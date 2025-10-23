<?php

declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\TestCase\TestAuthenticationHelper;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * Example Test Demonstrating Test Super User Fixture Usage
 * 
 * This test class shows how to use the TestSuperUser fixtures
 * to solve permission issues in tests.
 */
class ExampleSuperUserTest extends TestCase
{
    use IntegrationTestTrait;
    use TestAuthenticationHelper;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected array $fixtures = [
        'app.AppSettings',
        'app.Branches',
        'app.Permissions',
        'app.Roles',
        'app.Members',
        'app.RolesPermissions',
        'app.MemberRoles',
        // Test Super User fixtures
        'app.TestSuperUser',
        'app.TestSuperUserRole',
        'app.TestSuperUserRolePermission',
        'app.TestSuperUserMemberRole',
    ];

    /**
     * Test using the helper trait method
     *
     * @return void
     */
    public function testWithHelperTrait(): void
    {
        // Authenticate as test super user using helper
        $this->authenticateAsSuperUser();

        // Verify authentication
        $this->assertAuthenticated();
        $this->assertAuthenticatedAs(2);

        // Make a test request that requires authentication
        // Since we're authenticated as super user, all authorization checks pass
        $this->get('/members');
        $this->assertResponseOk();
    }

    /**
     * Test using direct session setup
     *
     * @return void
     */
    public function testWithDirectSession(): void
    {
        // Alternative: authenticate directly without helper
        $this->session([
            'Auth' => [
                'id' => 2,
                'email_address' => 'testsuper@test.com',
                'sca_name' => 'Test Super User',
            ]
        ]);

        // Make authenticated request
        $this->get('/members');
        $this->assertResponseOk();
    }

    /**
     * Test switching between users
     *
     * @return void
     */
    public function testSwitchingUsers(): void
    {
        // Start as super user
        $this->authenticateAsSuperUser();
        $this->assertAuthenticatedAs(2);

        // Make a request
        $this->get('/members');
        $this->assertResponseOk();

        // Switch to admin
        $this->authenticateAsAdmin();
        $this->assertAuthenticatedAs(1);

        // Make another request
        $this->get('/members');
        $this->assertResponseOk();

        // Log out
        $this->logout();
        $this->assertNotAuthenticated();
    }

    /**
     * Test that super user can access restricted resources
     *
     * @return void
     */
    public function testSuperUserAccessToRestrictedResource(): void
    {
        // Without authentication, should be denied
        $this->get('/roles');
        $this->assertResponseCode(302); // Redirect to login

        // With super user authentication, should succeed
        $this->authenticateAsSuperUser();
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
        $this->authenticateAsSuperUser();

        $data = [
            'name' => 'Test Role',
            'is_system' => false,
        ];

        $this->post('/roles/add', $data);
        $this->assertResponseSuccess();
        $this->assertRedirect(['controller' => 'Roles', 'action' => 'index']);
    }

    /**
     * Example setUp for authenticating all tests in the class
     *
     * @return void
     */
    /*
    protected function setUp(): void
    {
        parent::setUp();
        
        // Uncomment to authenticate all tests in this class as super user
        $this->authenticateAsSuperUser();
    }
    */

    /**
     * Test would automatically have super user authentication if setUp is uncommented
     *
     * @return void
     */
    public function testWithSetUpAuthentication(): void
    {
        // If setUp is uncommented, this test is already authenticated
        // For now, authenticate manually
        $this->authenticateAsSuperUser();

        $this->assertAuthenticated();
        $this->get('/members');
        $this->assertResponseOk();
    }
}
