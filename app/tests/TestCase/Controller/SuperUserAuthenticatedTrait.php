<?php

declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;

/**
 * SuperUserAuthenticatedTrait
 * 
 * A trait that sets up tests with test super user authentication.
 * This provides full system access for tests, solving permission issues.
 * 
 * Use this trait in your test classes to automatically authenticate
 * as the test super user before each test runs.
 * 
 * Example:
 * ```php
 * class MyControllerTest extends TestCase
 * {
 *     use SuperUserAuthenticatedTrait;
 *     
 *     protected array $fixtures = [
 *         'app.Branches',
 *         'app.Permissions',
 *         'app.Roles',
 *         'app.Members',
 *         'app.RolesPermissions',
 *         'app.MemberRoles',
 *         'app.TestSuperUser',
 *         'app.TestSuperUserRole',
 *         'app.TestSuperUserRolePermission',
 *         'app.TestSuperUserMemberRole',
 *     ];
 *     
 *     public function testSomething(): void
 *     {
 *         // Already authenticated as test super user
 *         $this->get('/protected/route');
 *         $this->assertResponseOk();
 *     }
 * }
 * ```
 */
trait SuperUserAuthenticatedTrait
{
    use IntegrationTestTrait;

    /**
     * Set up the test with super user authentication
     * 
     * This method:
     * 1. Enables CSRF and security tokens
     * 2. Loads the test super user from the database
     * 3. Loads and attaches the super user permission
     * 4. Sets up the session with the authenticated user
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->enableCsrfToken();
        $this->enableSecurityToken();

        $membersTable = $this->getTableLocator()->get('Members');

        // Look up test super user by email (works with auto-increment fixtures)
        $member = $membersTable->findByEmailAddress('testsuper@test.com')->firstOrFail();
        $member->warrantableReview();

        // Load the super user permission to enable authorization
        // This simulates what happens in production when permissions are loaded dynamically
        $permissionsTable = $this->getTableLocator()->get('Permissions');
        $superUserPermission = $permissionsTable->findByName('Is Super User')->first();

        if ($superUserPermission) {
            // Manually set permissions on the member entity for testing
            $member->set('permissions', [$superUserPermission]);
        }

        // Save without triggering beforeSave to avoid recursion
        $membersTable->save($member, ['checkRules' => false, 'callbacks' => false]);

        // Set up session with authenticated member
        $this->session([
            'Auth' => $member,
        ]);
    }

    /**
     * Get the authenticated member ID (helper method)
     *
     * @return int Test super user ID (2)
     */
    protected function getAuthenticatedMemberId(): int
    {
        return 2; // Test super user ID
    }

    /**
     * Get the authenticated member email (helper method)
     *
     * @return string Test super user email
     */
    protected function getAuthenticatedMemberEmail(): string
    {
        return 'testsuper@test.com';
    }
}
