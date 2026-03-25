<?php

declare(strict_types=1);

namespace Waivers\Test\TestCase\Services;

use Waivers\Services\WaiversNavigationProvider;
use App\Model\Entity\Member;
use App\Test\TestCase\BaseTestCase;

class WaiversProvidersTest extends BaseTestCase
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
        $items = WaiversNavigationProvider::getNavigationItems($this->user);
        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
    }

    public function testNavigationItemsHaveRequiredKeys(): void
    {
        $items = WaiversNavigationProvider::getNavigationItems($this->user);
        foreach ($items as $item) {
            $this->assertArrayHasKey('type', $item);
            $this->assertArrayHasKey('label', $item);
            if ($item['type'] === 'link') {
                $this->assertArrayHasKey('url', $item);
                $this->assertArrayHasKey('icon', $item);
                $this->assertArrayHasKey('order', $item);
                $this->assertArrayHasKey('mergePath', $item);
            }
            if ($item['type'] === 'parent') {
                $this->assertArrayHasKey('icon', $item);
                $this->assertArrayHasKey('id', $item);
                $this->assertArrayHasKey('order', $item);
            }
        }
    }

    public function testNavigationContainsParentHeader(): void
    {
        $items = WaiversNavigationProvider::getNavigationItems($this->user);
        $parents = array_filter($items, fn($i) => $i['type'] === 'parent');
        $this->assertNotEmpty($parents);
        $parentLabels = array_column($parents, 'label');
        $this->assertContains('Waivers', $parentLabels);
    }

    public function testNavigationContainsExpectedLabels(): void
    {
        $items = WaiversNavigationProvider::getNavigationItems($this->user);
        $labels = array_column($items, 'label');
        $this->assertContains('Waivers Uploaded', $labels);
        $this->assertContains('Waiver Dashboard', $labels);
        $this->assertContains('Waiver Types', $labels);
        $this->assertContains('Gatherings Needing Waivers', $labels);
    }

    public function testNavigationWaiversUploadedUrlReferencesPlugin(): void
    {
        $items = WaiversNavigationProvider::getNavigationItems($this->user);
        $linkItems = array_filter($items, fn($i) => $i['type'] === 'link');
        foreach ($linkItems as $item) {
            $this->assertSame('Waivers', $item['url']['plugin']);
        }
    }

    public function testNavigationGatheringsNeedingWaiversHasBadge(): void
    {
        $items = WaiversNavigationProvider::getNavigationItems($this->user);
        $gatheringsItem = null;
        foreach ($items as $item) {
            if ($item['label'] === 'Gatherings Needing Waivers') {
                $gatheringsItem = $item;
                break;
            }
        }
        $this->assertNotNull($gatheringsItem);
        $this->assertArrayHasKey('badgeClass', $gatheringsItem);
        $this->assertArrayHasKey('badgeValue', $gatheringsItem);
        $this->assertSame('bg-danger', $gatheringsItem['badgeClass']);
    }

    public function testNavigationReturnsFewerItemsWithoutUser(): void
    {
        $itemsWithUser = WaiversNavigationProvider::getNavigationItems($this->user);
        $itemsWithoutUser = WaiversNavigationProvider::getNavigationItems(null);
        $this->assertGreaterThan(count($itemsWithoutUser), count($itemsWithUser));
    }

    public function testNavigationWithNullUserStillReturnsWaiverTypes(): void
    {
        $items = WaiversNavigationProvider::getNavigationItems(null);
        $labels = array_column($items, 'label');
        $this->assertContains('Waiver Types', $labels);
    }

    public function testGetMemberNavigationItemsReturnsArray(): void
    {
        $items = WaiversNavigationProvider::getMemberNavigationItems($this->user, self::ADMIN_MEMBER_ID);
        $this->assertIsArray($items);
    }

    public function testGetBranchNavigationItemsReturnsArray(): void
    {
        $items = WaiversNavigationProvider::getBranchNavigationItems($this->user, self::KINGDOM_BRANCH_ID);
        $this->assertIsArray($items);
    }

    public function testNavigationAcceptsOptionalParams(): void
    {
        $items = WaiversNavigationProvider::getNavigationItems($this->user, ['test' => true]);
        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
    }
}
