<?php

declare(strict_types=1);

namespace Queue\Test\TestCase\Services;

use Queue\Services\QueueNavigationProvider;
use App\Model\Entity\Member;
use App\Test\TestCase\BaseTestCase;

class QueueProvidersTest extends BaseTestCase
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
        $items = QueueNavigationProvider::getNavigationItems($this->user);
        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
    }

    public function testNavigationReturnsSingleItem(): void
    {
        $items = QueueNavigationProvider::getNavigationItems($this->user);
        $this->assertCount(1, $items);
    }

    public function testNavigationItemHasRequiredKeys(): void
    {
        $items = QueueNavigationProvider::getNavigationItems($this->user);
        $item = $items[0];
        $this->assertArrayHasKey('type', $item);
        $this->assertArrayHasKey('label', $item);
        $this->assertArrayHasKey('order', $item);
        $this->assertArrayHasKey('url', $item);
        $this->assertArrayHasKey('icon', $item);
        $this->assertArrayHasKey('mergePath', $item);
    }

    public function testNavigationItemIsQueueEngine(): void
    {
        $items = QueueNavigationProvider::getNavigationItems($this->user);
        $this->assertSame('Queue Engine', $items[0]['label']);
        $this->assertSame('link', $items[0]['type']);
    }

    public function testNavigationUrlReferencesQueuePlugin(): void
    {
        $items = QueueNavigationProvider::getNavigationItems($this->user);
        $this->assertSame('Queue', $items[0]['url']['plugin']);
        $this->assertSame('Queue', $items[0]['url']['controller']);
        $this->assertSame('index', $items[0]['url']['action']);
    }

    public function testNavigationItemMergedUnderConfig(): void
    {
        $items = QueueNavigationProvider::getNavigationItems($this->user);
        $this->assertSame(['Config'], $items[0]['mergePath']);
    }

    public function testNavigationItemHasActivePaths(): void
    {
        $items = QueueNavigationProvider::getNavigationItems($this->user);
        $this->assertArrayHasKey('activePaths', $items[0]);
        $this->assertContains('queue/*', $items[0]['activePaths']);
    }

    public function testNavigationAcceptsOptionalParams(): void
    {
        $items = QueueNavigationProvider::getNavigationItems($this->user, ['x' => 1]);
        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
    }
}
