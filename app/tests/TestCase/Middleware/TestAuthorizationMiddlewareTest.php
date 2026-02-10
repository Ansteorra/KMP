<?php

declare(strict_types=1);

namespace App\Test\TestCase\Middleware;

use App\Test\TestCase\Support\HttpIntegrationTestCase;

/**
 * Tests that the authorization middleware pipeline correctly handles
 * unauthenticated and authenticated requests.
 */
class TestAuthorizationMiddlewareTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
    }

    /**
     * Test that unauthenticated requests to a protected route redirect to login.
     */
    public function testUnauthenticatedRequestRedirectsToLogin(): void
    {
        // Do NOT authenticate — request as anonymous user
        $this->get('/members/view/1');

        $this->assertRedirectContains('/members/login');
    }

    /**
     * Test that authenticated super-user requests pass through authorization.
     */
    public function testAuthenticatedSuperUserAccessesProtectedRoute(): void
    {
        $this->authenticateAsSuperUser();

        $this->get('/members/view/1');

        $this->assertResponseOk();
    }

    /**
     * Test that unauthenticated POST requests are rejected.
     */
    public function testUnauthenticatedPostIsRejected(): void
    {
        $this->post('/members/edit/1', ['sca_name' => 'Test']);

        $this->assertRedirectContains('/members/login');
    }
}