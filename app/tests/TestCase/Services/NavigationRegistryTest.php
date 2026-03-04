<?php

declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Services\NavigationRegistry;
use App\Model\Entity\Member;
use App\Test\TestCase\BaseTestCase;

/**
 * NavigationRegistry Test Case
 */
class NavigationRegistryTest extends BaseTestCase
{
    /**
     * setUp method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        NavigationRegistry::clear();
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown(): void
    {
        NavigationRegistry::clear();
        parent::tearDown();
    }

    /**
     * Test basic registration and retrieval
     *
     * @return void
     */
    public function testBasicRegistration(): void
    {
        $items = [
            [
                'type' => 'link',
                'label' => 'Test Link',
                'url' => ['controller' => 'Test', 'action' => 'index']
            ]
        ];

        NavigationRegistry::register('test', $items);

        $this->assertTrue(NavigationRegistry::isRegistered('test'));
        $this->assertContains('test', NavigationRegistry::getRegisteredSources());

        // Create a mock user
        $user = new Member(['id' => 1, 'sca_name' => 'Test User']);
        $retrievedItems = NavigationRegistry::getNavigationItems($user);

        $this->assertNotEmpty($retrievedItems);
        $this->assertEquals('Test Link', $retrievedItems[0]['label']);
    }

    /**
     * Test registration with callback
     *
     * @return void
     */
    public function testRegistrationWithCallback(): void
    {
        $staticItems = [
            [
                'type' => 'parent',
                'label' => 'Static Menu',
            ]
        ];

        $callback = function ($user, $params) {
            return [
                [
                    'type' => 'link',
                    'label' => "Dynamic link for {$user->sca_name}",
                    'url' => ['controller' => 'Users', 'action' => 'view', $user->id]
                ]
            ];
        };

        NavigationRegistry::register('test-dynamic', $staticItems, $callback);

        $user = new Member(['id' => 1, 'sca_name' => 'John Doe']);
        $items = NavigationRegistry::getNavigationItems($user);

        $this->assertCount(2, $items);
        $this->assertEquals('Static Menu', $items[0]['label']);
        $this->assertEquals('Dynamic link for John Doe', $items[1]['label']);
    }

    /**
     * Test getting items from specific source
     *
     * @return void
     */
    public function testGetNavigationItemsFromSource(): void
    {
        $coreItems = [
            ['type' => 'link', 'label' => 'Core Item']
        ];
        $pluginItems = [
            ['type' => 'link', 'label' => 'Plugin Item']
        ];

        NavigationRegistry::register('core', $coreItems);
        NavigationRegistry::register('plugin', $pluginItems);

        $user = new Member(['id' => 1, 'sca_name' => 'Test User']);

        $coreResult = NavigationRegistry::getNavigationItemsFromSource('core', $user);
        $pluginResult = NavigationRegistry::getNavigationItemsFromSource('plugin', $user);

        $this->assertCount(1, $coreResult);
        $this->assertCount(1, $pluginResult);
        $this->assertEquals('Core Item', $coreResult[0]['label']);
        $this->assertEquals('Plugin Item', $pluginResult[0]['label']);
    }

    /**
     * Test unregistering sources
     *
     * @return void
     */
    public function testUnregister(): void
    {
        NavigationRegistry::register('test', [['label' => 'Test']]);

        $this->assertTrue(NavigationRegistry::isRegistered('test'));

        NavigationRegistry::unregister('test');

        $this->assertFalse(NavigationRegistry::isRegistered('test'));
        $this->assertNotContains('test', NavigationRegistry::getRegisteredSources());
    }

    /**
     * Test debug information
     *
     * @return void
     */
    public function testGetDebugInfo(): void
    {
        $items = [
            ['type' => 'link', 'label' => 'Item 1'],
            ['type' => 'link', 'label' => 'Item 2']
        ];

        $callback = function () {
            return [['type' => 'link', 'label' => 'Dynamic Item']];
        };

        NavigationRegistry::register('source1', $items);
        NavigationRegistry::register('source2', [], $callback);

        $debug = NavigationRegistry::getDebugInfo();

        $this->assertArrayHasKey('sources', $debug);
        $this->assertArrayHasKey('total_items', $debug);
        $this->assertEquals(2, $debug['total_items']);
        $this->assertEquals(2, $debug['sources']['source1']['static_items']);
        $this->assertEquals(0, $debug['sources']['source2']['static_items']);
        $this->assertFalse($debug['sources']['source1']['has_callback']);
        $this->assertTrue($debug['sources']['source2']['has_callback']);
    }

    /**
     * Ensure empty cached navigation does not suppress registered menu sources.
     *
     * @return void
     */
    public function testIgnoresEmptyCachedNavigationWhenSourcesExist(): void
    {
        $_SESSION['navigation_items'] = [
            'user_id' => 1,
            'items' => [],
            'generated_at' => (new \DateTimeImmutable('now'))->format(\DateTimeInterface::ATOM),
        ];

        NavigationRegistry::register('test', [[
            'type' => 'link',
            'label' => 'Recovered Link',
            'url' => ['controller' => 'Members', 'action' => 'index'],
        ]]);

        $user = new Member(['id' => 1, 'sca_name' => 'Test User']);
        $items = NavigationRegistry::getNavigationItems($user);

        $this->assertNotEmpty($items);
        $this->assertSame('Recovered Link', $items[0]['label']);
    }
}
