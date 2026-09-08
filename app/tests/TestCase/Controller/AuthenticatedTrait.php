<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Services\Security\MemberSessionState;
use Cake\TestSuite\IntegrationTestTrait;

/**
 * AuthenticatedTrait
 *
 * @deprecated Use TestAuthenticationHelperTrait (via HttpIntegrationTestCase) instead.
 *   Extend HttpIntegrationTestCase and call $this->authenticateAsSuperUser() instead.
 * @see \App\Test\TestCase\TestAuthenticationHelperTrait
 * @see \App\Test\TestCase\Support\HttpIntegrationTestCase
 */

trait AuthenticatedTrait
{
    use IntegrationTestTrait;

    /** Authenticate with the persisted seed account and current credential epoch. */
    public function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $member = $this->getTableLocator()->get('Members')->find()
            ->where(['email_address IN' => ['admin@amp.ansteorra.org', 'admin@test.com']])->firstOrFail();
        $this->session(['Auth' => MemberSessionState::fromMember($member)]);
    }
}
