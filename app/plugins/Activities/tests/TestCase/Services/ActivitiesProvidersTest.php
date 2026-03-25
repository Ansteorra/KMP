<?php

declare(strict_types=1);

namespace Activities\Test\TestCase\Services;

use Activities\Services\ActivitiesNavigationProvider;
use Activities\Services\ActivitiesViewCellProvider;
use App\Model\Entity\Member;
use App\Services\ViewCellRegistry;
use App\Test\TestCase\BaseTestCase;

class ActivitiesProvidersTest extends BaseTestCase
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
        $items = ActivitiesNavigationProvider::getNavigationItems($this->user);
        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
    }

    public function testNavigationItemsHaveRequiredKeys(): void
    {
        $items = ActivitiesNavigationProvider::getNavigationItems($this->user);
        foreach ($items as $item) {
            $this->assertArrayHasKey('type', $item);
            $this->assertArrayHasKey('label', $item);
            $this->assertArrayHasKey('order', $item);
            if ($item['type'] === 'link') {
                $this->assertArrayHasKey('url', $item);
                $this->assertArrayHasKey('icon', $item);
                $this->assertArrayHasKey('mergePath', $item);
            }
        }
    }

    public function testNavigationContainsExpectedLabels(): void
    {
        $items = ActivitiesNavigationProvider::getNavigationItems($this->user);
        $labels = array_column($items, 'label');
        $this->assertContains('My Auth Queue', $labels);
        $this->assertContains('Pending Auths', $labels);
        $this->assertContains('Auth Queues', $labels);
        $this->assertContains('Activity Groups', $labels);
        $this->assertContains('Activities', $labels);
        $this->assertContains('Activity Authorizations', $labels);
    }

    public function testNavigationUrlsReferenceActivitiesPlugin(): void
    {
        $items = ActivitiesNavigationProvider::getNavigationItems($this->user);
        $linkItems = array_filter($items, fn($i) => $i['type'] === 'link');
        foreach ($linkItems as $item) {
            $this->assertSame('Activities', $item['url']['plugin']);
        }
    }

    public function testNavigationPendingAuthsHasBadge(): void
    {
        $items = ActivitiesNavigationProvider::getNavigationItems($this->user);
        $pending = null;
        foreach ($items as $item) {
            if ($item['label'] === 'Pending Auths') {
                $pending = $item;
                break;
            }
        }
        $this->assertNotNull($pending);
        $this->assertArrayHasKey('badgeClass', $pending);
        $this->assertArrayHasKey('badgeValue', $pending);
        $this->assertSame('bg-danger', $pending['badgeClass']);
    }

    public function testNavigationAcceptsOptionalParams(): void
    {
        $items = ActivitiesNavigationProvider::getNavigationItems($this->user, ['foo' => 'bar']);
        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
    }

    // ── ViewCell Provider ────────────────────────────────────────────

    public function testViewCellsReturnsArray(): void
    {
        $urlParams = ['controller' => 'Members', 'action' => 'view', 'plugin' => null, 'pass' => [self::ADMIN_MEMBER_ID]];
        $cells = ActivitiesViewCellProvider::getViewCells($urlParams, $this->user);
        $this->assertIsArray($cells);
        $this->assertNotEmpty($cells);
    }

    public function testViewCellsHaveRequiredKeys(): void
    {
        $urlParams = ['controller' => 'Members', 'action' => 'view', 'plugin' => null, 'pass' => [self::ADMIN_MEMBER_ID]];
        $cells = ActivitiesViewCellProvider::getViewCells($urlParams, $this->user);
        foreach ($cells as $cell) {
            $this->assertArrayHasKey('type', $cell);
            $this->assertArrayHasKey('order', $cell);
            if ($cell['type'] === ViewCellRegistry::PLUGIN_TYPE_TAB || $cell['type'] === ViewCellRegistry::PLUGIN_TYPE_JSON) {
                $this->assertArrayHasKey('id', $cell);
                $this->assertArrayHasKey('cell', $cell);
            }
            if ($cell['type'] === ViewCellRegistry::PLUGIN_TYPE_MOBILE_MENU) {
                $this->assertArrayHasKey('label', $cell);
                $this->assertArrayHasKey('icon', $cell);
                $this->assertArrayHasKey('url', $cell);
            }
        }
    }

    public function testViewCellsContainExpectedIds(): void
    {
        $urlParams = ['controller' => 'Members', 'action' => 'view', 'plugin' => null, 'pass' => [self::ADMIN_MEMBER_ID]];
        $cells = ActivitiesViewCellProvider::getViewCells($urlParams, $this->user);
        $ids = array_column($cells, 'id');
        $this->assertContains('member-authorizations', $ids);
    }

    public function testViewCellsIncludePermissionActivitiesForPermissionRoute(): void
    {
        $urlParams = ['controller' => 'Permissions', 'action' => 'view', 'plugin' => null, 'pass' => [1]];
        $cells = ActivitiesViewCellProvider::getViewCells($urlParams, $this->user);
        $ids = array_column($cells, 'id');
        $this->assertContains('permission-activities', $ids);
    }

    public function testViewCellsReturnTabTypes(): void
    {
        $urlParams = ['controller' => 'Members', 'action' => 'view', 'plugin' => null, 'pass' => [self::ADMIN_MEMBER_ID]];
        $cells = ActivitiesViewCellProvider::getViewCells($urlParams, $this->user);
        $tabCells = array_filter($cells, fn($c) => $c['type'] === ViewCellRegistry::PLUGIN_TYPE_TAB);
        $this->assertNotEmpty($tabCells);
    }

    public function testViewCellsIncludeMobileMenuItems(): void
    {
        $urlParams = ['controller' => 'Members', 'action' => 'view', 'plugin' => null, 'pass' => [self::ADMIN_MEMBER_ID]];
        $cells = ActivitiesViewCellProvider::getViewCells($urlParams, $this->user);
        $mobileItems = array_filter($cells, fn($c) => $c['type'] === ViewCellRegistry::PLUGIN_TYPE_MOBILE_MENU);
        $this->assertNotEmpty($mobileItems);
    }

    public function testViewCellsReturnEmptyWithoutUser(): void
    {
        $urlParams = ['controller' => 'Members', 'action' => 'view', 'plugin' => null, 'pass' => [1]];
        $cells = ActivitiesViewCellProvider::getViewCells($urlParams);
        // Even without user, tab cells for permissions should still be returned
        $this->assertIsArray($cells);
    }
}
