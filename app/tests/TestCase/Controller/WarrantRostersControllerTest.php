<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Cake\ORM\TableRegistry;

/**
 * Tests for WarrantRostersController, focusing on grid sorting.
 */
class WarrantRostersControllerTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->authenticateAsSuperUser();
    }

    public function testIndex(): void
    {
        $this->get('/warrant-rosters');
        $this->assertResponseOk();
    }

    public function testGridDataNoSort(): void
    {
        $this->get('/warrant-rosters/grid-data');
        $this->assertResponseOk();
    }

    public function testGridDataSortByWarrantCountAsc(): void
    {
        $this->get('/warrant-rosters/grid-data?ignore_default=1&sort=warrant_count&direction=asc');
        $this->assertResponseOk();
    }

    public function testGridDataSortByWarrantCountDesc(): void
    {
        $this->get('/warrant-rosters/grid-data?ignore_default=1&sort=warrant_count&direction=desc');
        $this->assertResponseOk();
    }

    public function testGridDataSortByName(): void
    {
        $this->get('/warrant-rosters/grid-data?ignore_default=1&sort=name&direction=asc');
        $this->assertResponseOk();
    }

    public function testGridDataSortByStatus(): void
    {
        $this->get('/warrant-rosters/grid-data?ignore_default=1&sort=status&direction=asc');
        $this->assertResponseOk();
    }

    public function testGridDataSortByCreated(): void
    {
        $this->get('/warrant-rosters/grid-data?ignore_default=1&sort=created&direction=desc');
        $this->assertResponseOk();
    }

    public function testGridDataSortByApprovalsRequired(): void
    {
        $this->get('/warrant-rosters/grid-data?ignore_default=1&sort=approvals_required&direction=asc');
        $this->assertResponseOk();
    }

    public function testGridDataSortByApprovalCount(): void
    {
        $this->get('/warrant-rosters/grid-data?ignore_default=1&sort=approval_count&direction=desc');
        $this->assertResponseOk();
    }

    /**
     * Non-sortable columns should fall back to default sort, not error.
     */
    public function testGridDataSortByNonSortableColumn(): void
    {
        $this->get('/warrant-rosters/grid-data?ignore_default=1&sort=created_by_member_sca_name&direction=asc');
        $this->assertResponseOk();
    }

    /**
     * Completely unknown sort field should fall back gracefully.
     */
    public function testGridDataSortByUnknownColumn(): void
    {
        $this->get('/warrant-rosters/grid-data?ignore_default=1&sort=nonexistent_field&direction=asc');
        $this->assertResponseOk();
    }

    public function testViewUsesApprovalsQueueInsteadOfLegacyRosterActions(): void
    {
        $pendingRoster = TableRegistry::getTableLocator()->get('WarrantRosters')
            ->find()
            ->where(['status' => 'Pending'])
            ->firstOrFail();

        $this->get('/warrant-rosters/view/' . $pendingRoster->id);
        $this->assertResponseOk();
        $this->assertResponseContains('Open My Approvals');
        $this->assertResponseNotContains('data-controller="roster-approval"');
        $this->assertResponseNotContains('/warrant-rosters/approve/');
        $this->assertResponseNotContains('/warrant-rosters/decline/');
    }
}
