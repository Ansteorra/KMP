<?php

declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Services\CoreViewCellProvider;
use App\Services\ViewCellRegistry;
use App\Test\TestCase\BaseTestCase;

class CoreViewCellProviderTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
    }

    public function testGetViewCellsReturnsEmptyArrayForUnauthenticatedUser(): void
    {
        $cells = CoreViewCellProvider::getViewCells([]);
        $this->assertIsArray($cells);
        $this->assertEmpty($cells);
    }

    public function testGetViewCellsReturnsEmptyArrayForNullUser(): void
    {
        $cells = CoreViewCellProvider::getViewCells([], null);
        $this->assertIsArray($cells);
        $this->assertEmpty($cells);
    }

    public function testGetViewCellsReturnsItemsForAuthenticatedUser(): void
    {
        $member = $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);
        $cells = CoreViewCellProvider::getViewCells([], $member);

        $this->assertIsArray($cells);
        $this->assertNotEmpty($cells);
    }

    public function testCalendarCellIsRegistered(): void
    {
        $member = $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);
        $cells = CoreViewCellProvider::getViewCells([], $member);

        $calendarCells = array_filter($cells, fn($cell) => $cell['label'] === 'Calendar');
        $this->assertNotEmpty($calendarCells, 'Calendar mobile menu item should be registered');
    }

    public function testMyRsvpsCellIsRegistered(): void
    {
        $member = $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);
        $cells = CoreViewCellProvider::getViewCells([], $member);

        $rsvpCells = array_filter($cells, fn($cell) => $cell['label'] === 'My RSVPs');
        $this->assertNotEmpty($rsvpCells, 'My RSVPs mobile menu item should be registered');
    }

    public function testAllCellsAreMobileMenuType(): void
    {
        $member = $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);
        $cells = CoreViewCellProvider::getViewCells([], $member);

        foreach ($cells as $cell) {
            $this->assertEquals(
                ViewCellRegistry::PLUGIN_TYPE_MOBILE_MENU,
                $cell['type'],
                "Cell '{$cell['label']}' should be mobile_menu type"
            );
        }
    }

    public function testCellsHaveRequiredKeys(): void
    {
        $member = $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);
        $cells = CoreViewCellProvider::getViewCells([], $member);

        $requiredKeys = ['type', 'label', 'icon', 'url', 'order', 'color', 'authCallback'];

        foreach ($cells as $cell) {
            foreach ($requiredKeys as $key) {
                $this->assertArrayHasKey($key, $cell, "Cell '{$cell['label']}' missing key '$key'");
            }
        }
    }

    public function testCellsHaveValidUrls(): void
    {
        $member = $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);
        $cells = CoreViewCellProvider::getViewCells([], $member);

        foreach ($cells as $cell) {
            $this->assertIsArray($cell['url']);
            $this->assertArrayHasKey('controller', $cell['url']);
            $this->assertArrayHasKey('action', $cell['url']);
        }
    }

    public function testCalendarCellHasCorrectConfiguration(): void
    {
        $member = $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);
        $cells = CoreViewCellProvider::getViewCells([], $member);

        $calendar = array_values(array_filter($cells, fn($c) => $c['label'] === 'Calendar'))[0];

        $this->assertEquals('bi-calendar-event', $calendar['icon']);
        $this->assertEquals('Gatherings', $calendar['url']['controller']);
        $this->assertEquals('mobileCalendar', $calendar['url']['action']);
        $this->assertEquals('events', $calendar['color']);
        $this->assertEquals(35, $calendar['order']);
    }

    public function testMyRsvpsCellHasCorrectConfiguration(): void
    {
        $member = $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);
        $cells = CoreViewCellProvider::getViewCells([], $member);

        $rsvps = array_values(array_filter($cells, fn($c) => $c['label'] === 'My RSVPs'))[0];

        $this->assertEquals('bi-calendar-check', $rsvps['icon']);
        $this->assertEquals('GatheringAttendances', $rsvps['url']['controller']);
        $this->assertEquals('myRsvps', $rsvps['url']['action']);
        $this->assertEquals('rsvps', $rsvps['color']);
        $this->assertEquals(36, $rsvps['order']);
    }

    public function testAuthCallbacksAreCallable(): void
    {
        $member = $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);
        $cells = CoreViewCellProvider::getViewCells([], $member);

        foreach ($cells as $cell) {
            $this->assertIsCallable($cell['authCallback'], "Cell '{$cell['label']}' authCallback should be callable");
        }
    }

    public function testMyRsvpsAuthCallbackReturnsTrueForAuthenticatedUser(): void
    {
        $member = $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);
        $cells = CoreViewCellProvider::getViewCells([], $member);

        $rsvps = array_values(array_filter($cells, fn($c) => $c['label'] === 'My RSVPs'))[0];

        $result = ($rsvps['authCallback'])([], $member);
        $this->assertTrue($result, 'My RSVPs should be visible for authenticated users');
    }

    public function testMyRsvpsAuthCallbackReturnsFalseForNullUser(): void
    {
        $member = $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);
        $cells = CoreViewCellProvider::getViewCells([], $member);

        $rsvps = array_values(array_filter($cells, fn($c) => $c['label'] === 'My RSVPs'))[0];

        $result = ($rsvps['authCallback'])([], null);
        $this->assertFalse($result, 'My RSVPs should not be visible for null user');
    }

    public function testCellOrderIsNumeric(): void
    {
        $member = $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);
        $cells = CoreViewCellProvider::getViewCells([], $member);

        foreach ($cells as $cell) {
            $this->assertIsInt($cell['order']);
        }
    }

    public function testUrlParamsDoNotAffectCellRegistration(): void
    {
        $member = $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);

        $cellsEmpty = CoreViewCellProvider::getViewCells([], $member);
        $cellsWithParams = CoreViewCellProvider::getViewCells(
            ['controller' => 'Members', 'action' => 'view'],
            $member
        );

        $this->assertCount(count($cellsEmpty), $cellsWithParams);
    }
}
