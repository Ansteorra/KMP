<?php
declare(strict_types=1);

namespace App\Test\TestCase\KMP\GridColumns;

use App\KMP\GridColumns\WorkflowInstancesGridColumns;
use App\Model\Entity\WorkflowInstance;
use Cake\TestSuite\TestCase;

/**
 * Tests for WorkflowInstancesGridColumns metadata.
 */
class WorkflowInstancesGridColumnsTest extends TestCase
{
    public function testGetColumnsReturnsExpectedColumns(): void
    {
        $columns = WorkflowInstancesGridColumns::getColumns();

        $this->assertArrayHasKey('id', $columns);
        $this->assertArrayHasKey('workflow_name', $columns);
        $this->assertArrayHasKey('status', $columns);
        $this->assertArrayHasKey('started_at', $columns);
    }

    public function testDefaultVisibleColumnsIncludeStartedDate(): void
    {
        $visible = WorkflowInstancesGridColumns::getDefaultVisibleColumns();

        $this->assertArrayHasKey('id', $visible);
        $this->assertArrayHasKey('workflow_name', $visible);
        $this->assertArrayHasKey('started_at', $visible);
        $this->assertTrue($visible['started_at']['filterable']);
        $this->assertSame('date-range', $visible['started_at']['filterType']);
    }

    public function testStatusFilterOptionsUseWorkflowInstanceConstants(): void
    {
        $columns = WorkflowInstancesGridColumns::getColumns();
        $options = $columns['status']['filterOptions'];
        $values = array_column($options, 'value');

        $this->assertContains(WorkflowInstance::STATUS_PENDING, $values);
        $this->assertContains(WorkflowInstance::STATUS_RUNNING, $values);
        $this->assertContains(WorkflowInstance::STATUS_WAITING, $values);
        $this->assertContains(WorkflowInstance::STATUS_COMPLETED, $values);
        $this->assertContains(WorkflowInstance::STATUS_FAILED, $values);
        $this->assertContains(WorkflowInstance::STATUS_CANCELLED, $values);
    }

    public function testWorkflowRendererUsesLoadedAssociationName(): void
    {
        $columns = WorkflowInstancesGridColumns::getColumns();
        $renderer = $columns['workflow_name']['cellRenderer'];
        $row = (object)[
            'workflow_definition' => (object)['name' => 'Officer Hire'],
        ];

        $this->assertSame('Officer Hire', $renderer(null, $row, null));
    }

    public function testVersionRendererUsesLoadedAssociationNumber(): void
    {
        $columns = WorkflowInstancesGridColumns::getColumns();
        $renderer = $columns['version_number']['cellRenderer'];
        $row = (object)[
            'workflow_version' => (object)['version_number' => 7],
        ];

        $this->assertSame('v7', $renderer(null, $row, null));
    }

    public function testGetRowActionsReturnsViewAction(): void
    {
        $actions = WorkflowInstancesGridColumns::getRowActions();

        $this->assertArrayHasKey('view', $actions);
        $this->assertSame('link', $actions['view']['type']);
        $this->assertSame('', $actions['view']['label']);
        $this->assertSame('bi-eye', $actions['view']['icon']);
        $this->assertSame('View instance', $actions['view']['title']);
        $this->assertSame('View workflow instance {{id}}', $actions['view']['ariaLabel']);
    }

    public function testGetSystemViewsReturnsRecentView(): void
    {
        $views = WorkflowInstancesGridColumns::getSystemViews();

        $this->assertArrayHasKey('sys-workflow-instances-recent', $views);
        $this->assertSame(
            'started_at',
            $views['sys-workflow-instances-recent']['config']['filters'][0]['field'],
        );
    }
}
