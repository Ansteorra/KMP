<?php

declare(strict_types=1);

namespace App\Test\TestCase;

use Cake\ORM\TableRegistry;

/**
 * TestAuthenticationHelper
 * 
 * Helper trait for authenticating test users in integration tests.
 * Provides convenient methods to authenticate as the test super user
 * or other predefined test accounts.
 *
 * Sessions must contain a Member entity (not a plain array) because
 * the authorization middleware expects KmpIdentityInterface.
 */
trait TestAuthenticationHelper
{
    /**
     * Authenticate as the test super user
     * 
     * Loads the admin member from the database and sets it in the session.
     * The authorization layer will check permissions via PermissionsLoader.
     *
     * @return void
     */
    protected function authenticateAsSuperUser(): void
    {
        $membersTable = TableRegistry::getTableLocator()->get('Members');
        $member = $membersTable->findByEmailAddress('admin@amp.ansteorra.org')->firstOrFail();
        $this->session(['Auth' => $member]);
    }

    /**
     * Authenticate as the admin user
     * 
     * Alias for authenticateAsSuperUser() — same admin account.
     *
     * @return void
     */
    protected function authenticateAsAdmin(): void
    {
        $this->authenticateAsSuperUser();
    }

    /**
     * Authenticate as a custom user by member ID
     * 
     * Loads the member from the database. The member must exist in seed data.
     *
     * @param int $memberId The ID of the member to authenticate as
     * @return void
     */
    protected function authenticateAsMember(int $memberId): void
    {
        $membersTable = TableRegistry::getTableLocator()->get('Members');
        $member = $membersTable->get($memberId);
        $this->session(['Auth' => $member]);
    }

    /**
     * Log out the current user
     *
     * @return void
     */
    protected function logout(): void
    {
        $this->session(['Auth' => null]);
    }

    /**
     * Get the currently authenticated member ID
     *
     * @return int|null
     */
    protected function getAuthenticatedMemberId(): ?int
    {
        // Check post-request session first
        $session = $this->_requestSession ?? null;
        if ($session && $session->check('Auth')) {
            $auth = $session->read('Auth');
            if (is_object($auth) && isset($auth->id)) {
                return (int)$auth->id;
            }
            if (is_array($auth) && !empty($auth['id'])) {
                return (int)$auth['id'];
            }
        }
        // Fall back to pre-request session data
        if (!empty($this->_session['Auth'])) {
            $auth = $this->_session['Auth'];
            if (is_object($auth) && isset($auth->id)) {
                return (int)$auth->id;
            }
            if (is_array($auth) && !empty($auth['id'])) {
                return (int)$auth['id'];
            }
        }
        return null;
    }

    /**
     * Assert that a user is authenticated
     *
     * @param string|null $message Custom assertion message
     * @return void
     */
    protected function assertAuthenticated(?string $message = null): void
    {
        $message = $message ?? 'Expected a user to be authenticated';
        $this->assertNotNull($this->getAuthenticatedMemberId(), $message);
    }

    /**
     * Assert that no user is authenticated
     *
     * @param string|null $message Custom assertion message
     * @return void
     */
    protected function assertNotAuthenticated(?string $message = null): void
    {
        $message = $message ?? 'Expected no user to be authenticated';
        $this->assertNull($this->getAuthenticatedMemberId(), $message);
    }

    /**
     * Assert that a specific member is authenticated
     *
     * @param int $memberId Expected member ID
     * @param string|null $message Custom assertion message
     * @return void
     */
    protected function assertAuthenticatedAs(int $memberId, ?string $message = null): void
    {
        $message = $message ?? "Expected member {$memberId} to be authenticated";
        $this->assertEquals($memberId, $this->getAuthenticatedMemberId(), $message);
    }
}
