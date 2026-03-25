<?php

declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Services\ViewCellRegistry;
use App\Test\TestCase\BaseTestCase;

class ViewCellRegistryTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
        ViewCellRegistry::clear();
    }

    protected function tearDown(): void
    {
        ViewCellRegistry::clear();
        parent::tearDown();
    }

    public function testRegisterAndIsRegistered(): void
    {
        $this->assertFalse(ViewCellRegistry::isRegistered('TestPlugin'));

        ViewCellRegistry::register('TestPlugin', [
            [
                'type' => ViewCellRegistry::PLUGIN_TYPE_TAB,
                'label' => 'Test Tab',
                'id' => 'test-tab',
                'order' => 10,
                'cell' => 'TestPlugin.TestCell',
                'validRoutes' => [['controller' => 'Members', 'action' => 'view', 'plugin' => null]],
            ],
        ]);

        $this->assertTrue(ViewCellRegistry::isRegistered('TestPlugin'));
    }

    public function testUnregister(): void
    {
        ViewCellRegistry::register('TestPlugin', []);
        $this->assertTrue(ViewCellRegistry::isRegistered('TestPlugin'));

        ViewCellRegistry::unregister('TestPlugin');
        $this->assertFalse(ViewCellRegistry::isRegistered('TestPlugin'));
    }

    public function testClearRemovesAll(): void
    {
        ViewCellRegistry::register('PluginA', []);
        ViewCellRegistry::register('PluginB', []);

        ViewCellRegistry::clear();

        $this->assertEmpty(ViewCellRegistry::getRegisteredSources());
    }

    public function testGetRegisteredSources(): void
    {
        ViewCellRegistry::register('Alpha', []);
        ViewCellRegistry::register('Beta', []);
        ViewCellRegistry::register('Gamma', []);

        $sources = ViewCellRegistry::getRegisteredSources();
        $this->assertCount(3, $sources);
        $this->assertContains('Alpha', $sources);
        $this->assertContains('Beta', $sources);
        $this->assertContains('Gamma', $sources);
    }

    public function testGetViewCellsFiltersByRoute(): void
    {
        ViewCellRegistry::register('TestPlugin', [
            [
                'type' => ViewCellRegistry::PLUGIN_TYPE_TAB,
                'label' => 'Members Tab',
                'id' => 'members-tab',
                'order' => 5,
                'cell' => 'TestPlugin.MembersCell',
                'validRoutes' => [['controller' => 'Members', 'action' => 'view', 'plugin' => null]],
            ],
            [
                'type' => ViewCellRegistry::PLUGIN_TYPE_TAB,
                'label' => 'Branches Tab',
                'id' => 'branches-tab',
                'order' => 10,
                'cell' => 'TestPlugin.BranchesCell',
                'validRoutes' => [['controller' => 'Branches', 'action' => 'view', 'plugin' => null]],
            ],
        ]);

        $cells = ViewCellRegistry::getViewCells([
            'controller' => 'Members',
            'action' => 'view',
            'plugin' => null,
        ]);

        $this->assertArrayHasKey(ViewCellRegistry::PLUGIN_TYPE_TAB, $cells);
        $tabCells = $cells[ViewCellRegistry::PLUGIN_TYPE_TAB];
        $this->assertCount(1, $tabCells);
        $this->assertEquals('Members Tab', reset($tabCells)['label']);
    }

    public function testGetViewCellsOrganizesByTypeAndOrder(): void
    {
        ViewCellRegistry::register('TestPlugin', [
            [
                'type' => ViewCellRegistry::PLUGIN_TYPE_TAB,
                'label' => 'Second Tab',
                'id' => 'second-tab',
                'order' => 20,
                'cell' => 'Test.Second',
                'validRoutes' => [['controller' => 'Members', 'action' => 'view', 'plugin' => null]],
            ],
            [
                'type' => ViewCellRegistry::PLUGIN_TYPE_TAB,
                'label' => 'First Tab',
                'id' => 'first-tab',
                'order' => 5,
                'cell' => 'Test.First',
                'validRoutes' => [['controller' => 'Members', 'action' => 'view', 'plugin' => null]],
            ],
            [
                'type' => ViewCellRegistry::PLUGIN_TYPE_DETAIL,
                'label' => 'Detail Section',
                'id' => 'detail',
                'order' => 1,
                'cell' => 'Test.Detail',
                'validRoutes' => [['controller' => 'Members', 'action' => 'view', 'plugin' => null]],
            ],
        ]);

        $cells = ViewCellRegistry::getViewCells([
            'controller' => 'Members',
            'action' => 'view',
            'plugin' => null,
        ]);

        $this->assertArrayHasKey(ViewCellRegistry::PLUGIN_TYPE_TAB, $cells);
        $this->assertArrayHasKey(ViewCellRegistry::PLUGIN_TYPE_DETAIL, $cells);

        $tabKeys = array_keys($cells[ViewCellRegistry::PLUGIN_TYPE_TAB]);
        $this->assertEquals([5, 20], $tabKeys);
    }

    public function testGetViewCellsReturnsEmptyForNoMatch(): void
    {
        ViewCellRegistry::register('TestPlugin', [
            [
                'type' => ViewCellRegistry::PLUGIN_TYPE_TAB,
                'label' => 'Tab',
                'id' => 'tab',
                'order' => 1,
                'cell' => 'Test.Cell',
                'validRoutes' => [['controller' => 'Members', 'action' => 'view', 'plugin' => null]],
            ],
        ]);

        $cells = ViewCellRegistry::getViewCells([
            'controller' => 'Branches',
            'action' => 'index',
            'plugin' => null,
        ]);

        $this->assertEmpty($cells);
    }

    public function testGetViewCellsFromSource(): void
    {
        ViewCellRegistry::register('PluginA', [
            [
                'type' => ViewCellRegistry::PLUGIN_TYPE_TAB,
                'label' => 'A Tab',
                'id' => 'a-tab',
                'order' => 1,
                'cell' => 'A.Cell',
                'validRoutes' => [['controller' => 'Members', 'action' => 'view', 'plugin' => null]],
            ],
        ]);
        ViewCellRegistry::register('PluginB', [
            [
                'type' => ViewCellRegistry::PLUGIN_TYPE_TAB,
                'label' => 'B Tab',
                'id' => 'b-tab',
                'order' => 2,
                'cell' => 'B.Cell',
                'validRoutes' => [['controller' => 'Members', 'action' => 'view', 'plugin' => null]],
            ],
        ]);

        $route = ['controller' => 'Members', 'action' => 'view', 'plugin' => null];
        $cells = ViewCellRegistry::getViewCellsFromSource('PluginA', $route);

        $this->assertArrayHasKey(ViewCellRegistry::PLUGIN_TYPE_TAB, $cells);
        $tabCells = $cells[ViewCellRegistry::PLUGIN_TYPE_TAB];
        $this->assertCount(1, $tabCells);
        $this->assertEquals('A Tab', reset($tabCells)['label']);
    }

    public function testGetViewCellsFromSourceReturnsEmptyForUnknown(): void
    {
        $cells = ViewCellRegistry::getViewCellsFromSource('Nonexistent', [
            'controller' => 'Members',
            'action' => 'view',
            'plugin' => null,
        ]);

        $this->assertEmpty($cells);
    }

    public function testDynamicCallbackProvidesCells(): void
    {
        ViewCellRegistry::register('Dynamic', [], function (array $urlParams, $user) {
            return [
                [
                    'type' => ViewCellRegistry::PLUGIN_TYPE_TAB,
                    'label' => 'Dynamic Tab',
                    'id' => 'dynamic-tab',
                    'order' => 50,
                    'cell' => 'Dynamic.Cell',
                    'validRoutes' => [['controller' => 'Members', 'action' => 'view', 'plugin' => null]],
                ],
            ];
        });

        $cells = ViewCellRegistry::getViewCells([
            'controller' => 'Members',
            'action' => 'view',
            'plugin' => null,
        ]);

        $this->assertArrayHasKey(ViewCellRegistry::PLUGIN_TYPE_TAB, $cells);
        $tabCells = $cells[ViewCellRegistry::PLUGIN_TYPE_TAB];
        $this->assertEquals('Dynamic Tab', reset($tabCells)['label']);
    }

    public function testCellWithoutTypeOrOrderIsSkipped(): void
    {
        ViewCellRegistry::register('Incomplete', [
            [
                'label' => 'No Type',
                'id' => 'no-type',
                'order' => 1,
                'validRoutes' => [['controller' => 'Members', 'action' => 'view', 'plugin' => null]],
            ],
            [
                'type' => ViewCellRegistry::PLUGIN_TYPE_TAB,
                'label' => 'No Order',
                'id' => 'no-order',
                'validRoutes' => [['controller' => 'Members', 'action' => 'view', 'plugin' => null]],
            ],
        ]);

        $cells = ViewCellRegistry::getViewCells([
            'controller' => 'Members',
            'action' => 'view',
            'plugin' => null,
        ]);

        $this->assertEmpty($cells);
    }

    public function testMobileMenuWithNoValidRoutesShowsEverywhere(): void
    {
        ViewCellRegistry::register('Mobile', [
            [
                'type' => ViewCellRegistry::PLUGIN_TYPE_MOBILE_MENU,
                'label' => 'Mobile Item',
                'id' => 'mobile-item',
                'order' => 1,
            ],
        ]);

        $cells = ViewCellRegistry::getViewCells([
            'controller' => 'AnyController',
            'action' => 'anyAction',
            'plugin' => null,
        ]);

        $this->assertArrayHasKey(ViewCellRegistry::PLUGIN_TYPE_MOBILE_MENU, $cells);
    }

    public function testGetDebugInfo(): void
    {
        ViewCellRegistry::register('PluginA', [
            ['type' => 'tab', 'order' => 1],
            ['type' => 'tab', 'order' => 2],
        ]);
        ViewCellRegistry::register('PluginB', [
            ['type' => 'detail', 'order' => 1],
        ], fn() => []);

        $debug = ViewCellRegistry::getDebugInfo();

        $this->assertArrayHasKey('sources', $debug);
        $this->assertArrayHasKey('total_cells', $debug);
        $this->assertEquals(3, $debug['total_cells']);
        $this->assertEquals(2, $debug['sources']['PluginA']['static_cells']);
        $this->assertFalse($debug['sources']['PluginA']['has_callback']);
        $this->assertEquals(1, $debug['sources']['PluginB']['static_cells']);
        $this->assertTrue($debug['sources']['PluginB']['has_callback']);
    }

    public function testConstants(): void
    {
        $this->assertEquals('tab', ViewCellRegistry::PLUGIN_TYPE_TAB);
        $this->assertEquals('detail', ViewCellRegistry::PLUGIN_TYPE_DETAIL);
        $this->assertEquals('modal', ViewCellRegistry::PLUGIN_TYPE_MODAL);
        $this->assertEquals('json', ViewCellRegistry::PLUGIN_TYPE_JSON);
        $this->assertEquals('mobile_menu', ViewCellRegistry::PLUGIN_TYPE_MOBILE_MENU);
    }

    public function testRegisterOverwritesSameSource(): void
    {
        ViewCellRegistry::register('TestPlugin', [
            [
                'type' => ViewCellRegistry::PLUGIN_TYPE_TAB,
                'label' => 'Original',
                'id' => 'original',
                'order' => 1,
                'validRoutes' => [['controller' => 'Members', 'action' => 'view', 'plugin' => null]],
            ],
        ]);

        ViewCellRegistry::register('TestPlugin', [
            [
                'type' => ViewCellRegistry::PLUGIN_TYPE_TAB,
                'label' => 'Replaced',
                'id' => 'replaced',
                'order' => 1,
                'validRoutes' => [['controller' => 'Members', 'action' => 'view', 'plugin' => null]],
            ],
        ]);

        $cells = ViewCellRegistry::getViewCells([
            'controller' => 'Members',
            'action' => 'view',
            'plugin' => null,
        ]);

        $tabCells = $cells[ViewCellRegistry::PLUGIN_TYPE_TAB];
        $this->assertCount(1, $tabCells);
        $this->assertEquals('Replaced', reset($tabCells)['label']);
    }

    public function testMultipleValidRoutes(): void
    {
        ViewCellRegistry::register('MultiRoute', [
            [
                'type' => ViewCellRegistry::PLUGIN_TYPE_TAB,
                'label' => 'Shared Tab',
                'id' => 'shared',
                'order' => 1,
                'cell' => 'Test.Shared',
                'validRoutes' => [
                    ['controller' => 'Members', 'action' => 'view', 'plugin' => null],
                    ['controller' => 'Branches', 'action' => 'view', 'plugin' => null],
                ],
            ],
        ]);

        $membersResult = ViewCellRegistry::getViewCells([
            'controller' => 'Members',
            'action' => 'view',
            'plugin' => null,
        ]);
        $branchesResult = ViewCellRegistry::getViewCells([
            'controller' => 'Branches',
            'action' => 'view',
            'plugin' => null,
        ]);

        $this->assertNotEmpty($membersResult);
        $this->assertNotEmpty($branchesResult);
    }

    public function testAuthCallbackFiltersCell(): void
    {
        ViewCellRegistry::register('AuthPlugin', [
            [
                'type' => ViewCellRegistry::PLUGIN_TYPE_TAB,
                'label' => 'Auth Tab',
                'id' => 'auth-tab',
                'order' => 1,
                'cell' => 'Auth.Cell',
                'validRoutes' => [['controller' => 'Members', 'action' => 'view', 'plugin' => null]],
                'authCallback' => function ($urlParams, $user) {
                    return false;
                },
            ],
        ]);

        $cells = ViewCellRegistry::getViewCells([
            'controller' => 'Members',
            'action' => 'view',
            'plugin' => null,
        ]);

        $this->assertEmpty($cells);
    }
}
