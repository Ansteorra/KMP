<?php
declare(strict_types=1);

namespace App\Test\TestCase\KMP\GridColumns;

use App\KMP\GridColumns\ApprovalsGridColumns;
use App\Model\Entity\WorkflowApproval;
use Cake\TestSuite\TestCase;

/**
 * Tests for ApprovalsGridColumns grid column metadata.
 */
class ApprovalsGridColumnsTest extends TestCase
{
    public function testGetColumnsReturnsNonEmptyArray(): void
    {
        $columns = ApprovalsGridColumns::getColumns();
        $this->assertIsArray($columns);
        $this->assertNotEmpty($columns);
    }

    public function testColumnsHaveRequiredKeys(): void
    {
        $columns = ApprovalsGridColumns::getColumns();
        foreach ($columns as $key => $column) {
            $this->assertArrayHasKey('label', $column, "Column '$key' missing 'label'");
            $this->assertArrayHasKey('type', $column, "Column '$key' missing 'type'");
            $this->assertArrayHasKey('key', $column, "Column '$key' missing 'key'");
            $this->assertEquals($key, $column['key'], "Column key mismatch for '$key'");
        }
    }

    public function testDefaultVisibleColumnsExist(): void
    {
        $visible = ApprovalsGridColumns::getDefaultVisibleColumns();
        $this->assertNotEmpty($visible, 'Should have at least one default visible column');

        foreach ($visible as $column) {
            $this->assertTrue($column['defaultVisible']);
        }

        $visibleKeys = array_keys($visible);
        $this->assertContains('workflow_name', $visibleKeys);
        $this->assertContains('status_label', $visibleKeys);
        $this->assertContains('created', $visibleKeys);
    }

    public function testFilterConfigsExist(): void
    {
        $filterable = ApprovalsGridColumns::getFilterableColumns();
        $this->assertNotEmpty($filterable, 'Should have filterable columns');

        $columns = ApprovalsGridColumns::getColumns();
        foreach ($filterable as $key) {
            $this->assertTrue(
                $columns[$key]['filterable'],
                "Column '$key' should be filterable",
            );
        }

        // status_label has dropdown filterType with filterOptions
        $statusColumn = $columns['status_label'];
        $this->assertEquals('dropdown', $statusColumn['filterType']);
        $this->assertArrayHasKey('filterOptions', $statusColumn);
        $this->assertNotEmpty($statusColumn['filterOptions']);
    }

    public function testAdminColumnsIncludeCurrentApprover(): void
    {
        $adminColumns = ApprovalsGridColumns::getAdminColumns();
        $this->assertArrayHasKey('current_approver', $adminColumns);
        $this->assertTrue(
            $adminColumns['current_approver']['defaultVisible'],
            'Admin columns should have current_approver visible by default',
        );

        // Regular columns should NOT have current_approver visible by default
        $regularColumns = ApprovalsGridColumns::getColumns();
        $this->assertFalse($regularColumns['current_approver']['defaultVisible']);
    }

    public function testGetRowActionsReturnsExpectedActions(): void
    {
        $actions = ApprovalsGridColumns::getRowActions();
        $this->assertArrayHasKey('detail', $actions);
        $this->assertArrayHasKey('respond', $actions);
        $this->assertArrayHasKey('send_feedback', $actions);
        $this->assertSame(['is_feedback_response' => false], $actions['respond']['condition']);
        $this->assertSame(['is_feedback_response' => true], $actions['send_feedback']['condition']);
    }

    public function testGetAdminRowActionsIncludesReassign(): void
    {
        $actions = ApprovalsGridColumns::getAdminRowActions();
        $this->assertArrayHasKey('detail', $actions);
        $this->assertArrayHasKey('respond', $actions);
        $this->assertArrayHasKey('reassign', $actions);
    }

    public function testGetSystemViewsReturnsViews(): void
    {
        $views = ApprovalsGridColumns::getSystemViews();
        $this->assertArrayHasKey('sys-approvals-pending', $views);
        $this->assertArrayHasKey('sys-approvals-triage-board', $views);
        $this->assertArrayHasKey('sys-approvals-decisions', $views);
    }

    public function testTriageBoardSystemViewIsPendingKanbanView(): void
    {
        $views = ApprovalsGridColumns::getSystemViews();
        $view = $views['sys-approvals-triage-board'];

        $this->assertSame('kanban', $view['layout']);
        $this->assertFalse($view['canManage']);
        $this->assertSame(20, $view['config']['pageSize']);
        $this->assertSame(
            ['field' => 'status_label', 'operator' => 'eq', 'value' => WorkflowApproval::STATUS_PENDING],
            $view['config']['filters'][0],
        );
        $this->assertSame('request', $view['config']['columns'][0]['key']);
    }

    public function testGetAdminSystemViewsReturnsViews(): void
    {
        $views = ApprovalsGridColumns::getAdminSystemViews();
        $this->assertArrayHasKey('sys-admin-pending', $views);
        $this->assertArrayHasKey('sys-admin-approved', $views);
        $this->assertArrayHasKey('sys-admin-rejected', $views);
        $this->assertArrayHasKey('sys-admin-all', $views);
    }

    public function testSearchableColumnsExist(): void
    {
        $searchable = ApprovalsGridColumns::getSearchableColumns();
        $this->assertNotEmpty($searchable);
        $this->assertContains('workflow_name', $searchable);
        $this->assertContains('request', $searchable);
        $this->assertContains('current_approver', $searchable);
        // 'requester' is virtual (computed from JSON context), not searchable via SQL
        $this->assertNotContains('requester', $searchable);

        $columns = ApprovalsGridColumns::getColumns();
        $this->assertSame('WorkflowApprovals.request_title', $columns['request']['queryField']);
    }

    public function testStatusFilterOptionsUseConstants(): void
    {
        $columns = ApprovalsGridColumns::getColumns();
        $options = $columns['status_label']['filterOptions'];
        $values = array_column($options, 'value');

        $this->assertContains(WorkflowApproval::STATUS_PENDING, $values);
        $this->assertContains(WorkflowApproval::STATUS_APPROVED, $values);
        $this->assertContains(WorkflowApproval::STATUS_REJECTED, $values);
        $this->assertContains(WorkflowApproval::STATUS_EXPIRED, $values);
        $this->assertContains(WorkflowApproval::STATUS_CANCELLED, $values);
    }
}
