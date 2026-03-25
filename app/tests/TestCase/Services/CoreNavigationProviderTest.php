<?php

declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Model\Entity\Member;
use App\Services\CoreNavigationProvider;
use App\Test\TestCase\BaseTestCase;

class CoreNavigationProviderTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
    }

    public function testGetNavigationItemsReturnsArray(): void
    {
        $member = $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);
        $items = CoreNavigationProvider::getNavigationItems($member);

        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
    }

    public function testNavigationItemsContainParentTypes(): void
    {
        $member = $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);
        $items = CoreNavigationProvider::getNavigationItems($member);

        $parents = array_filter($items, fn($item) => $item['type'] === 'parent');
        $this->assertNotEmpty($parents, 'Should contain parent navigation items');

        $parentLabels = array_column($parents, 'label');
        $this->assertContains('Action Items', $parentLabels);
        $this->assertContains('Members', $parentLabels);
        $this->assertContains('Reports', $parentLabels);
        $this->assertContains('Config', $parentLabels);
        $this->assertContains('Security', $parentLabels);
        $this->assertContains('Gatherings', $parentLabels);
    }

    public function testNavigationItemsContainLinkTypes(): void
    {
        $member = $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);
        $items = CoreNavigationProvider::getNavigationItems($member);

        $links = array_filter($items, fn($item) => $item['type'] === 'link');
        $this->assertNotEmpty($links, 'Should contain link navigation items');
    }

    public function testAllParentItemsHaveRequiredKeys(): void
    {
        $member = $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);
        $items = CoreNavigationProvider::getNavigationItems($member);

        $parents = array_filter($items, fn($item) => $item['type'] === 'parent');
        foreach ($parents as $parent) {
            $this->assertArrayHasKey('label', $parent);
            $this->assertArrayHasKey('icon', $parent);
            $this->assertArrayHasKey('id', $parent);
            $this->assertArrayHasKey('order', $parent);
        }
    }

    public function testAllLinkItemsHaveRequiredKeys(): void
    {
        $member = $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);
        $items = CoreNavigationProvider::getNavigationItems($member);

        $links = array_filter($items, fn($item) => $item['type'] === 'link');
        foreach ($links as $link) {
            $this->assertArrayHasKey('label', $link);
            $this->assertArrayHasKey('url', $link);
            $this->assertArrayHasKey('order', $link);
            $this->assertArrayHasKey('mergePath', $link);
            $this->assertArrayHasKey('icon', $link);
        }
    }

    public function testUserProfileLinkContainsScaName(): void
    {
        $member = $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);
        $items = CoreNavigationProvider::getNavigationItems($member);

        $profileLinks = array_filter($items, fn($item) =>
            $item['type'] === 'link' &&
            isset($item['url']['action']) &&
            $item['url']['action'] === 'profile'
        );
        $this->assertNotEmpty($profileLinks, 'Should contain profile link');

        $profileLink = array_values($profileLinks)[0];
        $this->assertEquals($member->sca_name, $profileLink['label']);
    }

    public function testMyAuthCardLinkContainsUserId(): void
    {
        $member = $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);
        $items = CoreNavigationProvider::getNavigationItems($member);

        $cardLinks = array_filter($items, fn($item) =>
            $item['type'] === 'link' &&
            isset($item['url']['action']) &&
            $item['url']['action'] === 'viewCard'
        );
        $this->assertNotEmpty($cardLinks, 'Should contain auth card link');

        $cardLink = array_values($cardLinks)[0];
        $this->assertContains($member->id, $cardLink['url']);
    }

    public function testBadgeValueItemsHaveRequiredBadgeKeys(): void
    {
        $member = $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);
        $items = CoreNavigationProvider::getNavigationItems($member);

        $badgeItems = array_filter($items, fn($item) => isset($item['badgeValue']));
        $this->assertNotEmpty($badgeItems, 'Should contain items with badges');

        foreach ($badgeItems as $item) {
            $this->assertArrayHasKey('badgeClass', $item);
            $this->assertArrayHasKey('class', $item['badgeValue']);
            $this->assertArrayHasKey('method', $item['badgeValue']);
        }
    }

    public function testEmptyParamsDoesNotAffectNavigation(): void
    {
        $member = $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);

        $itemsNoParams = CoreNavigationProvider::getNavigationItems($member);
        $itemsEmptyParams = CoreNavigationProvider::getNavigationItems($member, []);

        $this->assertEquals($itemsNoParams, $itemsEmptyParams);
    }

    public function testDifferentMemberGetsPersonalizedNavigation(): void
    {
        $admin = $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);
        $agatha = $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_AGATHA_ID);

        $adminItems = CoreNavigationProvider::getNavigationItems($admin);
        $agathaItems = CoreNavigationProvider::getNavigationItems($agatha);

        // Both should have items but profile labels should differ
        $this->assertNotEmpty($adminItems);
        $this->assertNotEmpty($agathaItems);

        $adminProfile = array_values(array_filter($adminItems, fn($i) =>
            $i['type'] === 'link' && isset($i['url']['action']) && $i['url']['action'] === 'profile'
        ));
        $agathaProfile = array_values(array_filter($agathaItems, fn($i) =>
            $i['type'] === 'link' && isset($i['url']['action']) && $i['url']['action'] === 'profile'
        ));

        $this->assertNotEquals($adminProfile[0]['label'], $agathaProfile[0]['label']);
    }

    public function testNavigationOrderValues(): void
    {
        $member = $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);
        $items = CoreNavigationProvider::getNavigationItems($member);

        foreach ($items as $item) {
            $this->assertArrayHasKey('order', $item);
            $this->assertIsInt($item['order']);
        }
    }
}
