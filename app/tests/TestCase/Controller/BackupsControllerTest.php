<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\TestCase\Support\HttpIntegrationTestCase;

/**
 * Tenant self-service backups surface.
 *
 * The Backups page is gated by the "Can Manage Backups" permission (super
 * users pass via the policy before-hook). Managed listings require the
 * platform connection; without one the page degrades to the read-only
 * legacy section.
 *
 * @covers \App\Controller\BackupsController
 */
class BackupsControllerTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
    }

    public function testIndexRendersForSuperUser(): void
    {
        $this->authenticateAsSuperUser();

        $this->get('/backups');

        $this->assertResponseOk();
        $this->assertResponseContains('Backup Status');
        // No restore, schedule, or encryption-key management on the tenant surface.
        $this->assertResponseNotContains('Import Backup File');
        $this->assertResponseNotContains('Encryption Key');
        $this->assertResponseNotContains('Scheduled Backups');
    }

    public function testIndexIsForbiddenWithoutManageBackupsPermission(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);

        $this->get('/backups');

        $this->assertRedirectContains('/pages/unauthorized');
    }

    public function testCreateIsForbiddenWithoutManageBackupsPermission(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);

        $this->post('/backups/create');

        $this->assertRedirectContains('/pages/unauthorized');
    }

    public function testRetiredActionsAreGone(): void
    {
        $this->authenticateAsSuperUser();

        $this->post('/backups/restore');
        $this->assertResponseCode(404);

        $this->post('/backups/settings');
        $this->assertResponseCode(404);
    }
}
