<?php
declare(strict_types=1);

namespace App\Test\TestCase\View\Cell;

use App\Test\TestCase\Support\HttpIntegrationTestCase;

/**
 * NavigationCell regression tests.
 */
class NavigationCellTest extends HttpIntegrationTestCase
{
    public function testAuthenticatedUsersSeeMyApprovalsNavigation(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_BRYCE_ID);

        $this->get('/members/view/' . self::TEST_MEMBER_BRYCE_ID);

        $this->assertResponseOk();
        $this->assertResponseContains('Action Items');
        $this->assertResponseContains('My Approvals');
        $this->assertResponseContains('/approvals');
    }
}
