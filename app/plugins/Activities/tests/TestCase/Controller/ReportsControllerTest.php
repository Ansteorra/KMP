<?php
declare(strict_types=1);

namespace Activities\Test\TestCase\Controller;

use App\Test\TestCase\Support\HttpIntegrationTestCase;

class ReportsControllerTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticateAsSuperUser();
    }

    public function testAuthorizationsReportRendersFilteredPostgresRollup(): void
    {
        $this->get(
            '/activities/reports/authorizations'
            . '?validOn=2026-07-24&branches=2&activities=&activities%5B%5D=1',
        );

        $this->assertResponseOk();
        $this->assertResponseContains('Authorized Members');
    }
}
