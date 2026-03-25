<?php

declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use Awards\Services\AwardsNavigationProvider;
use Awards\Services\AwardsViewCellProvider;
use App\Model\Entity\Member;
use App\Services\ViewCellRegistry;
use App\Test\TestCase\BaseTestCase;

class AwardsProvidersTest extends BaseTestCase
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
        $items = AwardsNavigationProvider::getNavigationItems($this->user);
        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
    }

    public function testNavigationItemsHaveRequiredKeys(): void
    {
        $items = AwardsNavigationProvider::getNavigationItems($this->user);
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
        $items = AwardsNavigationProvider::getNavigationItems($this->user);
        $parents = array_filter($items, fn($i) => $i['type'] === 'parent');
        $this->assertNotEmpty($parents);
        $parentLabels = array_column($parents, 'label');
        $this->assertContains('Award Recs.', $parentLabels);
    }

    public function testNavigationContainsExpectedLabels(): void
    {
        $items = AwardsNavigationProvider::getNavigationItems($this->user);
        $labels = array_column($items, 'label');
        $this->assertContains('Recommendations', $labels);
        $this->assertContains('Award Domains', $labels);
        $this->assertContains('Award Levels', $labels);
        $this->assertContains('Awards', $labels);
        $this->assertContains('Submit Award Rec.', $labels);
    }

    public function testNavigationUrlsReferenceAwardsPlugin(): void
    {
        $items = AwardsNavigationProvider::getNavigationItems($this->user);
        $linkItems = array_filter($items, fn($i) => $i['type'] === 'link');
        foreach ($linkItems as $item) {
            $this->assertSame('Awards', $item['url']['plugin']);
        }
    }

    public function testNavigationSubmitRecHasButtonClass(): void
    {
        $items = AwardsNavigationProvider::getNavigationItems($this->user);
        $submitRec = null;
        foreach ($items as $item) {
            if ($item['label'] === 'Submit Award Rec.') {
                $submitRec = $item;
                break;
            }
        }
        $this->assertNotNull($submitRec);
        $this->assertArrayHasKey('linkTypeClass', $submitRec);
        $this->assertSame('btn', $submitRec['linkTypeClass']);
    }

    public function testNavigationAcceptsOptionalParams(): void
    {
        $items = AwardsNavigationProvider::getNavigationItems($this->user, ['extra' => true]);
        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
    }

    // ── ViewCell Provider ────────────────────────────────────────────

    public function testViewCellsReturnsArrayForMemberView(): void
    {
        $urlParams = [
            'controller' => 'Members',
            'action' => 'view',
            'plugin' => null,
            'pass' => [(string) self::ADMIN_MEMBER_ID],
        ];
        $cells = AwardsViewCellProvider::getViewCells($urlParams, $this->user);
        $this->assertIsArray($cells);
    }

    public function testViewCellsReturnsEmptyWithoutUser(): void
    {
        $urlParams = ['controller' => 'Members', 'action' => 'view', 'plugin' => null, 'pass' => [1]];
        $cells = AwardsViewCellProvider::getViewCells($urlParams, null);
        $this->assertIsArray($cells);
        $this->assertEmpty($cells);
    }

    public function testViewCellsShowSubmittedRecsOnProfile(): void
    {
        $urlParams = [
            'controller' => 'Members',
            'action' => 'profile',
            'plugin' => null,
            'pass' => [],
        ];
        $cells = AwardsViewCellProvider::getViewCells($urlParams, $this->user);
        $ids = array_column($cells, 'id');
        $this->assertContains('member-submitted-recs', $ids);
    }

    public function testViewCellsHaveRequiredKeys(): void
    {
        $urlParams = [
            'controller' => 'Members',
            'action' => 'profile',
            'plugin' => null,
            'pass' => [],
        ];
        $cells = AwardsViewCellProvider::getViewCells($urlParams, $this->user);
        foreach ($cells as $cell) {
            $this->assertArrayHasKey('type', $cell);
            $this->assertArrayHasKey('id', $cell);
            $this->assertArrayHasKey('order', $cell);
            $this->assertArrayHasKey('cell', $cell);
            $this->assertArrayHasKey('validRoutes', $cell);
        }
    }

    public function testViewCellsReturnTabType(): void
    {
        $urlParams = [
            'controller' => 'Members',
            'action' => 'profile',
            'plugin' => null,
            'pass' => [],
        ];
        $cells = AwardsViewCellProvider::getViewCells($urlParams, $this->user);
        foreach ($cells as $cell) {
            $this->assertSame(ViewCellRegistry::PLUGIN_TYPE_TAB, $cell['type']);
        }
    }

    public function testViewCellsEmptyForNonMemberController(): void
    {
        $urlParams = [
            'controller' => 'Branches',
            'action' => 'view',
            'plugin' => null,
            'pass' => [1],
        ];
        $cells = AwardsViewCellProvider::getViewCells($urlParams, $this->user);
        $this->assertIsArray($cells);
        $this->assertEmpty($cells);
    }
}
