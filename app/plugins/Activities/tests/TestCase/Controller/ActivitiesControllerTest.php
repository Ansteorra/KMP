<?php
declare(strict_types=1);

namespace Activities\Test\TestCase\Controller;

use App\Test\TestCase\Support\HttpIntegrationTestCase;

class ActivitiesControllerTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticateAsSuperUser();
    }

    public function testApproversListRejectsMissingMemberIdWithoutServerError(): void
    {
        $this->get('/activities/activities/approvers-list/41');

        $this->assertResponseCode(400);
        $this->assertSame('[]', (string)$this->_response->getBody());
    }
}
