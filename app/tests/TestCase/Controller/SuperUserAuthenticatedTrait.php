<?php

declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;

/**
 * SuperUserAuthenticatedTrait
 *
 * @deprecated Use TestAuthenticationHelper (via HttpIntegrationTestCase) instead.
 *   This trait writes to the database and is incompatible with transaction rollback.
 *   Extend HttpIntegrationTestCase and call $this->authenticateAsSuperUser() instead.
 * @see \App\Test\TestCase\TestAuthenticationHelper
 * @see \App\Test\TestCase\Support\HttpIntegrationTestCase
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
        $member = $membersTable->findByEmailAddress('admin@amp.ansteorra.org')->firstOrFail();
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
     * @return int Test super user ID
     */
    protected function getAuthenticatedMemberId(): int
    {
        $membersTable = $this->getTableLocator()->get('Members');
        $member = $membersTable->findByEmailAddress('admin@amp.ansteorra.org')->firstOrFail();
        return $member->id;
    }

    /**
     * Get the authenticated member email (helper method)
     *
     * @return string Test super user email
     */
    protected function getAuthenticatedMemberEmail(): string
    {
        return 'admin@amp.ansteorra.org';
    }
}
