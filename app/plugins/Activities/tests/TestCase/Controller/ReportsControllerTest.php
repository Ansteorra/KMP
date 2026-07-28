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
        $activity = $this->getTableLocator()
            ->get('Activities.Activities')
            ->find()
            ->select(['id'])
            ->where(['name' => 'Armored'])
            ->firstOrFail();

        $this->get(
            '/activities/reports/authorizations'
            . '?validOn=2026-07-24'
            . '&branches=' . self::KINGDOM_BRANCH_ID
            . '&activities=&activities%5B%5D=' . $activity->id,
        );

        $this->assertResponseOk();
        $this->assertResponseContains('Authorized Members');
    }
}
