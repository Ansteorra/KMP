<?php

declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Services\NavigationRegistry;
use App\Services\NavigationService;
use App\Test\TestCase\BaseTestCase;

class NavigationServiceTest extends BaseTestCase
{
    protected ?NavigationService $service = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
        NavigationRegistry::clear();
        $this->service = new NavigationService();
    }

    protected function tearDown(): void
    {
        NavigationRegistry::clear();
        parent::tearDown();
    }

    public function testInstantiation(): void
    {
        $this->assertInstanceOf(NavigationService::class, $this->service);
    }

    public function testProcessBadgeValueWithDirectInteger(): void
    {
        $this->assertEquals(5, $this->service->processBadgeValue(5));
    }

    public function testProcessBadgeValueWithStringCastsToInt(): void
    {
        $this->assertEquals(0, $this->service->processBadgeValue('not-a-number'));
        $this->assertEquals(42, $this->service->processBadgeValue('42'));
    }

    public function testProcessBadgeValueWithCallableConfig(): void
    {
        $result = $this->service->processBadgeValue([
            'class' => self::class,
            'method' => 'badgeCountHelper',
            'argument' => 10,
        ]);
        $this->assertEquals(10, $result);
    }

    public static function badgeCountHelper(int $arg): int
    {
        return $arg;
    }

    public function testProcessBadgeValueWithIncompleteArrayCastsToInt(): void
    {
        $result = $this->service->processBadgeValue(['class' => 'SomeClass']);
        $this->assertEquals(1, $result);
    }

    public function testProcessNavBarStateWithNull(): void
    {
        $result = $this->service->processNavBarState(null);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testProcessNavBarStateWithData(): void
    {
        $state = ['expanded' => true, 'section' => 'members'];
        $result = $this->service->processNavBarState($state);
        $this->assertEquals($state, $result);
    }

    public function testProcessNavBarStateWithEmptyArray(): void
    {
        $result = $this->service->processNavBarState([]);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testBuildNavItemClassesDefault(): void
    {
        $result = $this->service->buildNavItemClasses([]);
        $this->assertEquals('nav-link', $result);
    }

    public function testBuildNavItemClassesWithCustomLinkType(): void
    {
        $result = $this->service->buildNavItemClasses(['linkTypeClass' => 'dropdown-item']);
        $this->assertEquals('dropdown-item', $result);
    }

    public function testBuildNavItemClassesWithActive(): void
    {
        $result = $this->service->buildNavItemClasses([], true);
        // trim() only strips outer whitespace; internal double spaces remain
        $this->assertEquals('nav-link  active', $result);
    }

    public function testBuildNavItemClassesWithOtherClasses(): void
    {
        $result = $this->service->buildNavItemClasses([
            'linkTypeClass' => 'nav-link',
            'otherClasses' => 'text-danger fw-bold',
        ], true);
        $this->assertEquals('nav-link text-danger fw-bold active', $result);
    }

    public function testGetDebugInfoDelegatesToRegistry(): void
    {
        NavigationRegistry::register('core', [
            ['label' => 'Item'],
        ]);

        $debug = $this->service->getDebugInfo();
        $this->assertArrayHasKey('sources', $debug);
        $this->assertArrayHasKey('total_items', $debug);
        $this->assertArrayHasKey('core', $debug['sources']);
    }

    public function testGetNavigationItemsDelegatesToRegistry(): void
    {
        $membersTable = $this->getTableLocator()->get('Members');
        $user = $membersTable->get(self::ADMIN_MEMBER_ID);

        NavigationRegistry::register('core', [
            ['type' => 'parent', 'label' => 'Home'],
        ]);

        $items = $this->service->getNavigationItems($user);
        $this->assertCount(1, $items);
        $this->assertEquals('Home', $items[0]['label']);
    }

    public function testGetNavigationItemsFromSourceDelegatesToRegistry(): void
    {
        $membersTable = $this->getTableLocator()->get('Members');
        $user = $membersTable->get(self::ADMIN_MEMBER_ID);

        NavigationRegistry::register('Awards', [
            ['type' => 'item', 'label' => 'Award List'],
        ]);

        $items = $this->service->getNavigationItemsFromSource('Awards', $user);
        $this->assertCount(1, $items);
        $this->assertEquals('Award List', $items[0]['label']);
    }

    public function testGetNavigationItemsFromSourceReturnsEmptyForUnknown(): void
    {
        $membersTable = $this->getTableLocator()->get('Members');
        $user = $membersTable->get(self::ADMIN_MEMBER_ID);

        $items = $this->service->getNavigationItemsFromSource('DoesNotExist', $user);
        $this->assertEmpty($items);
    }
}
