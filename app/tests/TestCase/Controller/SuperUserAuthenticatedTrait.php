<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Services\Security\MemberSessionState;
use Cake\TestSuite\IntegrationTestTrait;

/**
 * SuperUserAuthenticatedTrait
 *
 * @deprecated Use TestAuthenticationHelperTrait (via HttpIntegrationTestCase) instead.
 *   Extend HttpIntegrationTestCase and call $this->authenticateAsSuperUser() instead.
 * @see \App\Test\TestCase\TestAuthenticationHelperTrait
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
     * 3. Reads the current credential epoch
     * 4. Stores a tenant-bound credential envelope for normal authentication
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->enableCsrfToken();
        $this->enableSecurityToken();

        $member = $this->getTableLocator()->get('Members')->find()
            ->where(['email_address IN' => ['admin@amp.ansteorra.org', 'admin@test.com']])->firstOrFail();
        $this->session(['Auth' => MemberSessionState::fromMember($member)]);
    }

    /**
     * Get the authenticated member ID (helper method)
     *
     * @return int Test super user ID
     */
    protected function getAuthenticatedMemberId(): int
    {
        $membersTable = $this->getTableLocator()->get('Members');
        $member = $membersTable->find()->where(['email_address IN' => ['admin@amp.ansteorra.org', 'admin@test.com']])->firstOrFail();

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
