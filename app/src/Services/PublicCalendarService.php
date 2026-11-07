<?php
declare(strict_types=1);

namespace App\Services;

use App\KMP\TimezoneHelper;
use App\Model\Entity\GatheringActivity;
use Cake\I18n\DateTime;

/** Builds public calendar rows in event-local dates without changing event activity assignments. */
class PublicCalendarService
{
    public const SUMMARY_ACTIVITY_LIMIT = 4;

    /**
     * @param array<\App\Model\Entity\GatheringActivity> $activities Activities to order without mutating assignments
     * @return array<\App\Model\Entity\GatheringActivity>
     */
    public static function orderActivities(array $activities): array
    {
        return GatheringActivityOrderingService::sort($activities);
    }

    /**
     * @param array<\App\Model\Entity\GatheringActivity> $activities Assigned catalogue activities
     * @return array<\App\Model\Entity\GatheringActivity> Ordered public activities, excluding administrative work
     */
    public static function publicActivities(array $activities): array
    {
        return self::orderActivities(array_values(array_filter(
            $activities,
            static fn($activity): bool => $activity->activity_type !== 'Business',
        )));
    }

    /**
     * @param array<\App\Model\Entity\GatheringScheduledActivity> $activities Chronological schedule entries
     * @return array<\App\Model\Entity\GatheringScheduledActivity> Public entries retaining their time order
     */
    public static function publicScheduledActivities(array $activities): array
    {
        return array_values(array_filter(
            $activities,
            static fn($activity): bool => $activity->gathering_activity?->activity_type !== 'Business',
        ));
    }

    /**
     * @param array<\App\Model\Entity\GatheringActivity> $activities Published calendar choices
     * @return array<string, array<int, string>> Priority types first, then populated types in catalogue order
     */
    public static function groupActivityOptions(array $activities): array
    {
        $priority = ['Royal Progress', GatheringActivity::TYPE_ORDER_CIRCLE];
        $groups = array_fill_keys(array_unique([...$priority, ...GatheringActivity::ACTIVITY_TYPES]), []);
        foreach (self::publicActivities($activities) as $activity) {
            $type = $activity->activity_type ?? GatheringActivity::TYPE_GENERAL;
            if (!array_key_exists($type, $groups)) {
                $type = GatheringActivity::TYPE_GENERAL;
            }
            $groups[$type][$activity->id] = $activity->name;
        }

        return array_filter(
            $groups,
            static fn($options, $type): bool => $options !== [] || in_array($type, $priority, true),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * @param iterable<\App\Model\Entity\Gathering> $gatherings Published, filtered events
     * @param \Cake\I18n\DateTime $from First visible day
     * @param \Cake\I18n\DateTime $until Last visible day
     * @return array<string, array<string, mixed>>
     */
    public function build(iterable $gatherings, DateTime $from, DateTime $until): array
    {
        $events = [];
        foreach ($gatherings as $gathering) {
            $start = TimezoneHelper::toUserTimezone($gathering->start_date, null, null, $gathering);
            $end = TimezoneHelper::toUserTimezone($gathering->end_date, null, null, $gathering);
            if (
                !$start || !$end || $end->format('Y-m-d') < $from->format('Y-m-d')
                || $start->format('Y-m-d') > $until->format('Y-m-d')
            ) {
                continue;
            }
            $activities = self::publicActivities($gathering->gathering_activities ?? []);
            $startDay = new DateTime($start->format('Y-m-d'), 'UTC');
            $endDay = new DateTime($end->format('Y-m-d'), 'UTC');
            $weekday = (int)$startDay->format('w');
            $firstWeekend = $startDay->addDays($weekday === 0 ? -2 : 5 - $weekday);
            $events[] = [
                'gathering' => $gathering,
                'start' => $startDay,
                'firstWeekend' => $firstWeekend,
                'end' => $endDay,
                'days' => (int)$startDay->diff($endDay)->days + 1,
                'activities' => $activities,
                'summaryActivities' => array_slice($activities, 0, self::SUMMARY_ACTIVITY_LIMIT),
                'overflow' => max(0, count($activities) - self::SUMMARY_ACTIVITY_LIMIT),
            ];
        }
        $months = [];
        for ($month = new DateTime($from->format('Y-m-01'), 'UTC'); $month <= $until; $month = $month->addMonths(1)) {
            $key = $month->format('Y-m');
            $monthEnd = $month->endOfMonth()->startOfDay();
            $visibleStart = max($month->format('Y-m-d'), $from->format('Y-m-d'));
            $visibleEnd = min($monthEnd->format('Y-m-d'), $until->format('Y-m-d'));
            $rows = [];
            $represented = [];
            $count = [];
            // Each weekend belongs to the month containing its Saturday, including month-boundary Fridays.
            $saturday = $month->addDays((6 - (int)$month->format('w') + 7) % 7);
            for (; $saturday <= $monthEnd; $saturday = $saturday->addWeeks(1)) {
                $friday = $saturday->subDays(1);
                $sunday = $saturday->addDays(1);
                if ($sunday->format('Y-m-d') < $visibleStart || $friday->format('Y-m-d') > $visibleEnd) {
                    continue;
                }
                $matches = [];
                foreach ($events as $event) {
                    if ($event['start'] > $sunday || $event['end'] < $friday) {
                        continue;
                    }
                    $id = $event['gathering']->id;
                    $continuing = $friday > $event['firstWeekend'];
                    $date = $continuing ? $friday : $event['start'];
                    $matches[] = $this->eventRow($event, $date, $sunday, $key, $continuing);
                    $represented[$id] = true;
                    $count[$id] = true;
                }
                if ($matches === []) {
                    $rows[] = ['start' => $friday, 'end' => $sunday, 'empty' => true];
                } else {
                    array_push($rows, ...$matches);
                }
            }
            // Preserve weekday-only events and ongoing weekday portions at the edges of the visible range.
            foreach ($events as $event) {
                $id = $event['gathering']->id;
                if (
                    isset($represented[$id]) || $event['end']->format('Y-m-d') < $visibleStart
                    || $event['start']->format('Y-m-d') > $visibleEnd
                ) {
                    continue;
                }
                $date = new DateTime(max($event['start']->format('Y-m-d'), $visibleStart), 'UTC');
                $rows[] = $this->eventRow($event, $date, $event['end'], $key, $date > $event['start']);
                $count[$id] = true;
            }
            usort($rows, static fn($a, $b): int => $a['start'] <=> $b['start']
                ?: (($a['event']['gathering']->id ?? 0) <=> ($b['event']['gathering']->id ?? 0)));
            $months[$key] = ['label' => $month->format('F Y'), 'rows' => $rows, 'count' => count($count)];
        }

        return $months;
    }

    /**
     * @param array<string, mixed> $event Prepared event
     * @param \Cake\I18n\DateTime $start Occurrence start
     * @param \Cake\I18n\DateTime $end Weekend end
     * @param string $month Display month
     * @param bool $continuing Continues from a previous weekend
     * @return array<string, mixed>
     */
    private function eventRow(array $event, DateTime $start, DateTime $end, string $month, bool $continuing): array
    {
        return [
            'start' => $start,
            'end' => $continuing ? min($end, $event['end']) : $event['end'],
            'event' => $event,
            'empty' => false,
            'continuing' => $continuing,
            'key' => $event['gathering']->id . '-' . $month . '-' . $start->format('Ymd'),
        ];
    }
}
