<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Model\Table\WorkflowApprovalsTable;
use App\Test\TestCase\Support\HttpIntegrationTestCase;

/**
 * Request-scoped Workflow filter coverage for My Approvals.
 */
class ApprovalsWorkflowFilterControllerTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticateAsSuperUser();
    }

    public function testWorkflowFilterOptionsAreScopedToCurrentMemberApprovals(): void
    {
        $this->get('/approvals/grid-data');

        $this->assertResponseOk();
        $expectedNames = WorkflowApprovalsTable::getWorkflowNamesForMember(self::ADMIN_MEMBER_ID);
        $filterOptions = $this->viewVariable('filterOptions')['workflow_name'] ?? [];
        $availableFilter = $this->viewVariable('gridState')['filters']['available']['workflow_name'] ?? [];

        $this->assertSame($expectedNames, array_column($filterOptions, 'value'));
        $this->assertSame($expectedNames, array_column($filterOptions, 'label'));
        $this->assertSame('Workflow', $availableFilter['label'] ?? null);
        $this->assertSame($filterOptions, $availableFilter['options'] ?? null);
    }
}
