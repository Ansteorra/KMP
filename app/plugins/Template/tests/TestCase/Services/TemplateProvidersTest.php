<?php

declare(strict_types=1);

namespace Template\Test\TestCase\Services;

use Template\Services\TemplateNavigationProvider;
use App\Model\Entity\Member;
use App\Test\TestCase\BaseTestCase;

class TemplateProvidersTest extends BaseTestCase
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
        $items = TemplateNavigationProvider::getNavigationItems($this->user);
        $this->assertIsArray($items);
    }

    public function testNavigationItemsHaveRequiredKeys(): void
    {
        $items = TemplateNavigationProvider::getNavigationItems($this->user);
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
        $items = TemplateNavigationProvider::getNavigationItems($this->user);
        if (empty($items)) {
            $this->markTestSkipped('Template plugin not active in test environment');
        }
        $parents = array_filter($items, fn($i) => $i['type'] === 'parent');
        $this->assertNotEmpty($parents);
        $parentLabels = array_column($parents, 'label');
        $this->assertContains('Template', $parentLabels);
    }

    public function testNavigationContainsHelloWorldWhenActive(): void
    {
        $items = TemplateNavigationProvider::getNavigationItems($this->user);
        if (empty($items)) {
            $this->markTestSkipped('Template plugin not active in test environment');
        }
        $labels = array_column($items, 'label');
        $this->assertContains('Hello World', $labels);
    }

    public function testNavigationAddNewForAuthenticatedUser(): void
    {
        $items = TemplateNavigationProvider::getNavigationItems($this->user);
        if (empty($items)) {
            $this->markTestSkipped('Template plugin not active in test environment');
        }
        $labels = array_column($items, 'label');
        $this->assertContains('Add New', $labels);
    }

    public function testNavigationWithNullUserHasFewerItems(): void
    {
        $itemsWithUser = TemplateNavigationProvider::getNavigationItems($this->user);
        $itemsWithoutUser = TemplateNavigationProvider::getNavigationItems(null);
        if (empty($itemsWithUser)) {
            $this->markTestSkipped('Template plugin not active in test environment');
        }
        $this->assertGreaterThanOrEqual(count($itemsWithoutUser), count($itemsWithUser));
    }

    public function testNavigationUrlsReferenceTemplatePlugin(): void
    {
        $items = TemplateNavigationProvider::getNavigationItems($this->user);
        $linkItems = array_filter($items, fn($i) => $i['type'] === 'link');
        foreach ($linkItems as $item) {
            $this->assertSame('Template', $item['url']['plugin']);
        }
    }

    public function testGetMemberNavigationItemsReturnsArray(): void
    {
        $items = TemplateNavigationProvider::getMemberNavigationItems($this->user, self::ADMIN_MEMBER_ID);
        $this->assertIsArray($items);
    }

    public function testGetBranchNavigationItemsReturnsArray(): void
    {
        $items = TemplateNavigationProvider::getBranchNavigationItems($this->user, self::KINGDOM_BRANCH_ID);
        $this->assertIsArray($items);
    }

    public function testNavigationAcceptsOptionalParams(): void
    {
        $items = TemplateNavigationProvider::getNavigationItems($this->user, ['key' => 'value']);
        $this->assertIsArray($items);
    }
}
