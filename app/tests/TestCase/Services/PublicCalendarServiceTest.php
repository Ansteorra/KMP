<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Model\Entity\Gathering;
use App\Model\Entity\GatheringActivity;
use App\Services\PublicCalendarService;
use App\Test\TestCase\BaseTestCase;
use Cake\I18n\DateTime;

class PublicCalendarServiceTest extends BaseTestCase
{
    public function testFilterGroupsPrioritizeProgressAndCirclesAndRetainActivityDisplayOrder(): void
    {
        $activities = [];
        $fixtures = [
            ['First arts', 'Arts & Sciences', 0],
            ['Laurel', 'Order Circle', 20],
            ['Pelican', 'Order Circle', 10],
            ['Crown reception', 'Royal Progress', 100],
            ['Archery', 'Martial', 1],
            ['Administrative Gate', 'Business', 0],
        ];
        foreach ($fixtures as $id => [$name, $type, $order]) {
            $activities[] = new GatheringActivity([
                'id' => $id + 1, 'name' => $name, 'activity_type' => $type, 'display_order' => $order,
            ]);
        }
        $groups = PublicCalendarService::groupActivityOptions($activities);
        $this->assertSame(['Royal Progress', 'Order Circle', 'Martial', 'Arts & Sciences'], array_keys($groups));
        $this->assertSame([3 => 'Pelican', 2 => 'Laurel'], $groups['Order Circle']);
        $this->assertSame([4 => 'Crown reception'], $groups['Royal Progress']);
        $this->assertSame(['Royal Progress' => [], 'Order Circle' => []], PublicCalendarService::groupActivityOptions([]));
    }

    public function testBusinessActivitiesDoNotConsumePublicSummarySlotsOrOverflow(): void
    {
        $activities = [];
        foreach (['Gate', 'Archery', 'Court', 'Feast', 'Class'] as $id => $name) {
            $activities[] = new GatheringActivity([
                'id' => $id, 'name' => $name, 'display_order' => $id,
                'activity_type' => $name === 'Gate' ? 'Business' : 'General',
            ]);
        }
        $event = $this->event('2027-01-08 18:00:00', '2027-01-10 18:00:00');
        $event->set('gathering_activities', $activities);
        $months = (new PublicCalendarService())->build([$event], new DateTime('2027-01-01'), new DateTime('2027-01-31'));
        $rows = array_values(array_filter($months['2027-01']['rows'], static fn($r) => !$r['empty']));
        $this->assertSame(['Archery', 'Court', 'Feast', 'Class'], array_column($rows[0]['event']['activities'], 'name'));
        $this->assertCount(4, $rows[0]['event']['summaryActivities']);
        $this->assertSame(0, $rows[0]['event']['overflow']);
        $this->assertCount(5, $event->gathering_activities);
    }

    public function testOrdersActivitiesBeforeTruncatingAndRetainsAllInDetails(): void
    {
        $activities = [];
        foreach (['Zulu', 'Beta', 'Alpha', 'Delta', 'Echo', 'First'] as $id => $name) {
            $activities[] = new GatheringActivity([
                'id' => $id, 'name' => $name, 'display_order' => $name === 'First' ? 1 : 100,
            ]);
        }
        $event = $this->event('2027-01-08 18:00:00', '2027-01-10 18:00:00');
        $event->set('gathering_activities', $activities);
        $months = (new PublicCalendarService())->build([$event], new DateTime('2027-01-01'), new DateTime('2027-01-31'));
        $rows = array_values(array_filter($months['2027-01']['rows'], static fn($r) => !$r['empty']));
        $this->assertSame(['First', 'Alpha', 'Beta', 'Delta'], array_column($rows[0]['event']['summaryActivities'], 'name'));
        $this->assertSame(2, $rows[0]['event']['overflow']);
        $this->assertCount(6, $rows[0]['event']['activities']);
        $this->assertSame(['First', 'Alpha', 'Beta', 'Delta', 'Echo', 'Zulu'], array_column($event->gathering_activities, 'name'));
    }

    public function testRepeatsCrossMonthEventAndKeepsEmptyWeekendsAndWeekdayEvents(): void
    {
        $long = $this->event('2027-01-29 18:00:00', '2027-02-07 18:00:00');
        $weekday = $this->event('2027-02-16 18:00:00', '2027-02-16 20:00:00');
        $weekday->set('id', 2);
        $months = (new PublicCalendarService())->build([$long, $weekday], new DateTime('2027-01-01'), new DateTime('2027-02-28'));
        $january = array_values(array_filter($months['2027-01']['rows'], static fn($r) => !$r['empty']));
        $february = array_values(array_filter($months['2027-02']['rows'], static fn($r) => !$r['empty']));
        $this->assertCount(1, $january);
        $this->assertCount(2, $february);
        $this->assertFalse($january[0]['continuing']);
        $this->assertTrue($february[0]['continuing']);
        $this->assertSame(10, $february[0]['event']['days']);
        $this->assertNotSame($january[0]['key'], $february[0]['key']);
        $this->assertSame(4, count(array_filter($months['2027-01']['rows'], static fn($r) => $r['empty'])));
        $this->assertSame('2027-02-16', $february[1]['start']->format('Y-m-d'));
    }

    public function testUsesEventLocalDatesAndIncludesWeekendCrossingMonthStart(): void
    {
        // UTC May 1 is still Friday April 30 in Chicago; Saturday belongs to May.
        $event = $this->event('2027-05-01 00:00:00', '2027-05-03 00:00:00');
        $months = (new PublicCalendarService())->build([$event], new DateTime('2027-05-01'), new DateTime('2027-05-31'));
        $rows = array_values(array_filter($months['2027-05']['rows'], static fn($r) => !$r['empty']));
        $this->assertCount(1, $rows);
        $this->assertSame('2027-04-30', $rows[0]['start']->format('Y-m-d'));
        $this->assertSame(3, $rows[0]['event']['days']);
    }

    public function testFirstWeekendKeepsTheActualMidweekStartDate(): void
    {
        $event = $this->event('2027-01-04 18:00:00', '2027-01-10 18:00:00');
        $months = (new PublicCalendarService())->build([$event], new DateTime('2027-01-01'), new DateTime('2027-01-31'));
        $rows = array_values(array_filter($months['2027-01']['rows'], static fn($r) => !$r['empty']));
        $this->assertCount(1, $rows);
        $this->assertSame('2027-01-04', $rows[0]['start']->format('Y-m-d'));
        $this->assertFalse($rows[0]['continuing']);
    }

    private function event(string $start, string $end): Gathering
    {
        $event = new Gathering([
            'start_date' => new DateTime($start, 'UTC'), 'end_date' => new DateTime($end, 'UTC'),
            'timezone' => 'America/Chicago', 'gathering_activities' => [],
        ]);
        $event->set('id', 1);

        return $event;
    }
}
