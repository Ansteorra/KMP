<?php

declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\TestCase\Support\HttpIntegrationTestCase;

/**
 * Integration tests for backward-compatible redirects from old
 * authorization-approval routes to the unified /approvals page.
 */
class ApprovalRedirectsTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
    }

    // =====================================================
    // Core routes.php redirects
    // =====================================================

    public function testMyQueueRedirectsToApprovals(): void
    {
        $this->authenticateAsSuperUser();
        $this->get('/authorization-approvals/my-queue');
        $this->assertRedirectContains('/approvals');
    }

    public function testMyQueueWithTokenRedirectsToApprovals(): void
    {
        $this->authenticateAsSuperUser();
        $this->get('/authorization-approvals/my-queue/some-token-value');
        $this->assertRedirectContains('/approvals');
    }

    // =====================================================
    // Activities plugin redirects
    // =====================================================

    public function testActivitiesMyQueueRedirectsToApprovals(): void
    {
        $this->authenticateAsSuperUser();
        $this->get('/activities/authorization-approvals/my-queue');
        $this->assertRedirectContains('/approvals');
    }

    public function testMobileApproveAuthorizationsRedirects(): void
    {
        $this->authenticateAsSuperUser();
        $this->get('/activities/authorization-approvals/mobile-approve-authorizations');
        $this->assertRedirectContains('/approvals');
    }

    public function testMobileApproveRedirects(): void
    {
        $this->authenticateAsSuperUser();
        $this->get('/activities/authorization-approvals/mobile-approve/123');
        $this->assertRedirectContains('/approvals');
    }

    public function testMobileDenyRedirects(): void
    {
        $this->authenticateAsSuperUser();
        $this->get('/activities/authorization-approvals/mobile-deny/456');
        $this->assertRedirectContains('/approvals');
    }
}
