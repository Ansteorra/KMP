<?php
declare(strict_types=1);

namespace Activities\Test\TestCase\Controller;

use App\Test\TestCase\Support\HttpIntegrationTestCase;

class ReportsControllerTest extends HttpIntegrationTestCase
{
    private const ARMORED_ACTIVITY_ID = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticateAsSuperUser();
    }

    public function testAuthorizationsReportRendersFilteredPostgresRollup(): void
    {
        $this->get(
            '/activities/reports/authorizations'
            . '?validOn=2026-07-24'
            . '&branches=' . self::KINGDOM_BRANCH_ID
            . '&activities=&activities%5B%5D=' . self::ARMORED_ACTIVITY_ID,
        );

        $this->assertResponseOk();
        $this->assertResponseContains('Authorized Members');
    }
}
