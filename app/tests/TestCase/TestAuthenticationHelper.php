<?php

declare(strict_types=1);

namespace App\Test\TestCase;

/**
 * TestAuthenticationHelper
 * 
 * Helper trait for authenticating test users in integration tests.
 * Provides convenient methods to authenticate as the test super user
 * or other predefined test accounts.
 */
trait TestAuthenticationHelper
{
    /**
     * Authenticate as the test super user
     * 
     * This user has the "Is Super User" permission and full system access.
     * Use this to bypass all authorization checks in tests.
     *
     * @return void
     */
    protected function authenticateAsSuperUser(): void
    {
        $this->session([
            'Auth' => [
                'id' => 1,
                'email_address' => 'admin@amp.ansteorra.org',
                'sca_name' => 'Admin von Admin',
                'first_name' => 'Admin',
                'last_name' => 'Admin',
                'membership_number' => 'Admin001',
                'branch_id' => 1,
                'status' => 'verified',
            ]
        ]);
    }

    /**
     * Authenticate as the admin user
     * 
     * The admin user has the Admin role with super user permissions.
     *
     * @return void
     */
    protected function authenticateAsAdmin(): void
    {
        $this->session([
            'Auth' => [
                'id' => 1,
                'email_address' => 'admin@amp.ansteorra.org',
                'sca_name' => 'Admin von Admin',
                'first_name' => 'Admin',
                'last_name' => 'Admin',
                'membership_number' => 'Admin001',
                'branch_id' => 1,
                'status' => 'verified',
            ]
        ]);
    }

    /**
     * Authenticate as a custom user by member ID
     * 
     * Note: The member must exist in your test fixtures.
     *
     * @param int $memberId The ID of the member to authenticate as
     * @param array $additionalData Additional session data to set
     * @return void
     */
    protected function authenticateAsMember(int $memberId, array $additionalData = []): void
    {
        $sessionData = array_merge([
            'id' => $memberId,
        ], $additionalData);

        $this->session([
            'Auth' => $sessionData
        ]);
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
        if ($session && $session->check('Auth.id')) {
            return (int)$session->read('Auth.id');
        }
        // Fall back to pre-request session data
        if (!empty($this->_session['Auth']['id'])) {
            return (int)$this->_session['Auth']['id'];
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
