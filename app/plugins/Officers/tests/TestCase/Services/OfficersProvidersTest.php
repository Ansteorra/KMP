<?php

declare(strict_types=1);

namespace Officers\Test\TestCase\Services;

use Officers\Services\OfficersNavigationProvider;
use Officers\Services\OfficersViewCellProvider;
use App\Model\Entity\Member;
use App\Services\ViewCellRegistry;
use App\Test\TestCase\BaseTestCase;

class OfficersProvidersTest extends BaseTestCase
{
    private Member $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
        $membersTable = $this->getTableLocator()->get('Members');
        $this->user = $membersTable->get(self::ADMIN_MEMBER_ID);
    }

    // ── Navigation Provider ──────────────────────────────────────────

    public function testNavigationReturnsArray(): void
    {
        $items = OfficersNavigationProvider::getNavigationItems($this->user);
        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
    }

    public function testNavigationItemsHaveRequiredKeys(): void
    {
        $items = OfficersNavigationProvider::getNavigationItems($this->user);
        foreach ($items as $item) {
            $this->assertArrayHasKey('type', $item);
            $this->assertArrayHasKey('label', $item);
            $this->assertArrayHasKey('order', $item);
            $this->assertArrayHasKey('url', $item);
            $this->assertArrayHasKey('icon', $item);
            $this->assertArrayHasKey('mergePath', $item);
        }
    }

    public function testNavigationContainsExpectedLabels(): void
    {
        $items = OfficersNavigationProvider::getNavigationItems($this->user);
        $labels = array_column($items, 'label');
        $this->assertContains('Departments', $labels);
        $this->assertContains('Offices', $labels);
        $this->assertContains('Dept. Officer Roster', $labels);
        $this->assertContains('New Officer Roster', $labels);
    }

    public function testNavigationUrlsReferenceOfficersPlugin(): void
    {
        $items = OfficersNavigationProvider::getNavigationItems($this->user);
        foreach ($items as $item) {
            $this->assertSame('Officers', $item['url']['plugin']);
        }
    }

    public function testNavigationItemsAreAllLinks(): void
    {
        $items = OfficersNavigationProvider::getNavigationItems($this->user);
        foreach ($items as $item) {
            $this->assertSame('link', $item['type']);
        }
    }

    public function testNavigationAcceptsOptionalParams(): void
    {
        $items = OfficersNavigationProvider::getNavigationItems($this->user, ['context' => 'test']);
        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
    }

    // ── ViewCell Provider ────────────────────────────────────────────

    public function testViewCellsReturnsArray(): void
    {
        $urlParams = ['controller' => 'Members', 'action' => 'view', 'plugin' => null, 'pass' => [self::ADMIN_MEMBER_ID]];
        $cells = OfficersViewCellProvider::getViewCells($urlParams, $this->user);
        $this->assertIsArray($cells);
        $this->assertNotEmpty($cells);
    }

    public function testViewCellsHaveRequiredKeys(): void
    {
        $urlParams = ['controller' => 'Members', 'action' => 'view', 'plugin' => null, 'pass' => [self::ADMIN_MEMBER_ID]];
        $cells = OfficersViewCellProvider::getViewCells($urlParams, $this->user);
        foreach ($cells as $cell) {
            $this->assertArrayHasKey('type', $cell);
            $this->assertArrayHasKey('label', $cell);
            $this->assertArrayHasKey('id', $cell);
            $this->assertArrayHasKey('order', $cell);
            $this->assertArrayHasKey('cell', $cell);
            $this->assertArrayHasKey('validRoutes', $cell);
        }
    }

    public function testViewCellsContainExpectedIds(): void
    {
        $urlParams = ['controller' => 'Members', 'action' => 'view', 'plugin' => null, 'pass' => [self::ADMIN_MEMBER_ID]];
        $cells = OfficersViewCellProvider::getViewCells($urlParams, $this->user);
        $ids = array_column($cells, 'id');
        $this->assertContains('branch-officers', $ids);
        $this->assertContains('member-officers', $ids);
    }

    public function testViewCellsAllTabType(): void
    {
        $urlParams = ['controller' => 'Members', 'action' => 'view', 'plugin' => null, 'pass' => [self::ADMIN_MEMBER_ID]];
        $cells = OfficersViewCellProvider::getViewCells($urlParams, $this->user);
        foreach ($cells as $cell) {
            $this->assertSame(ViewCellRegistry::PLUGIN_TYPE_TAB, $cell['type']);
        }
    }

    public function testViewCellsMemberOfficersOnProfile(): void
    {
        $urlParams = ['controller' => 'Members', 'action' => 'profile', 'plugin' => null, 'pass' => []];
        $cells = OfficersViewCellProvider::getViewCells($urlParams, $this->user);
        $ids = array_column($cells, 'id');
        $this->assertContains('member-officers', $ids);
    }

    public function testViewCellsBranchOfficersForBranchView(): void
    {
        $urlParams = ['controller' => 'Branches', 'action' => 'view', 'plugin' => null, 'pass' => ['some-id']];
        $cells = OfficersViewCellProvider::getViewCells($urlParams, $this->user);
        $ids = array_column($cells, 'id');
        $this->assertContains('branch-officers', $ids);
    }

    public function testViewCellsBranchOfficersHasAuthCallback(): void
    {
        $urlParams = ['controller' => 'Members', 'action' => 'view', 'plugin' => null, 'pass' => [self::ADMIN_MEMBER_ID]];
        $cells = OfficersViewCellProvider::getViewCells($urlParams, $this->user);
        $branchOfficersCell = null;
        foreach ($cells as $cell) {
            if ($cell['id'] === 'branch-officers') {
                $branchOfficersCell = $cell;
                break;
            }
        }
        $this->assertNotNull($branchOfficersCell);
        $this->assertArrayHasKey('authCallback', $branchOfficersCell);
        $this->assertIsCallable($branchOfficersCell['authCallback']);
    }
}
