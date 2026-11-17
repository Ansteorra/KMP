<?php
declare(strict_types=1);

namespace App\Services;

use App\KMP\AnnoSocietatis;
use App\KMP\TimezoneHelper;
use App\Model\Entity\Gathering;
use App\Model\Entity\GatheringActivity;
use Cake\I18n\DateTime;
use Cake\ORM\Query\SelectQuery;

/** Builds public calendar rows in event-local dates without changing event activity assignments. */
class PublicCalendarService
{
    public const SUMMARY_ACTIVITY_LIMIT = 4;
    public const ARCHIVE_PAGE_SIZE = 50;

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
     * Select one archive page using bounded batches before loading activities or rendering rows.
     * Local date matching stays identical to display, including legacy timezone fallbacks.
     *
     * @param \Cake\ORM\Query\SelectQuery $query Published events with non-date filters applied
     * @param int $page One-based page
     * @param \Cake\I18n\DateTime|null $from First local date
     * @param \Cake\I18n\DateTime|null $until Last local date
     * @param int|null $selectedMonth Optional month across years
     * @return array{ids: list<int>, hasNext: bool}
     */
    public function archivePage(
        SelectQuery $query,
        int $page,
        ?DateTime $from,
        ?DateTime $until,
        ?int $selectedMonth,
    ): array {
        $scan = (clone $query)->contain([], true)
            ->select(['Gatherings.id', 'Gatherings.start_date', 'Gatherings.end_date', 'Gatherings.timezone'], true)
            ->orderBy(['Gatherings.start_date' => 'ASC', 'Gatherings.id' => 'ASC'], true)->limit(100);
        $skip = (max(1, $page) - 1) * self::ARCHIVE_PAGE_SIZE;
        $ids = [];
        do {
            $batch = (clone $scan)->all()->toList();
            foreach ($batch as $gathering) {
                [$start, $end] = $this->localDays($gathering);
                if (!$this->matchesDates($start, $end, $from, $until, $selectedMonth)) {
                    continue;
                }
                if ($skip > 0) {
                    $skip--;
                    continue;
                }
                if (count($ids) === self::ARCHIVE_PAGE_SIZE) {
                    return ['ids' => $ids, 'hasNext' => true];
                }
                $ids[] = (int)$gathering->id;
            }
            $batchCount = count($batch);
            if ($batch !== []) {
                $last = $batch[array_key_last($batch)];
                $scan->where(['OR' => [
                    ['Gatherings.start_date >' => $last->start_date],
                    ['Gatherings.start_date' => $last->start_date, 'Gatherings.id >' => $last->id],
                ]], [], true);
                // Keep all original publication/search predicates when advancing the batch cursor.
                $scan->andWhere($query->clause('where'));
            }
        } while ($batchCount === 100);

        return ['ids' => $ids, 'hasNext' => false];
    }

    /**
     * @param iterable<\App\Model\Entity\Gathering> $gatherings Published, filtered events
     * @param \Cake\I18n\DateTime|null $from First visible day; null searches all history
     * @param \Cake\I18n\DateTime|null $until Last visible day; null includes all future events
     * @param int|null $selectedMonth Month across any matching years
     * @param bool $includeEmptyWeekends False for pages that contain only part of the matching events
     * @return array<string, array<string, mixed>>
     */
    public function build(
        iterable $gatherings,
        ?DateTime $from,
        ?DateTime $until,
        ?int $selectedMonth = null,
        bool $includeEmptyWeekends = true,
    ): array {
        $buckets = [];
        $weekends = [];
        foreach ($gatherings as $gathering) {
            [$start, $end] = $this->localDays($gathering);
            if (!$this->matchesDates($start, $end, $from, $until, $selectedMonth)) {
                continue;
            }
            $activities = self::publicActivities($gathering->gathering_activities ?? []);
            $weekday = (int)$start->format('w');
            $event = [
                'gathering' => $gathering,
                'start' => $start,
                'firstWeekend' => $start->addDays($weekday === 0 ? -2 : 5 - $weekday),
                'end' => $end,
                'days' => (int)$start->diff($end)->days + 1,
                'activities' => $activities,
                'summaryActivities' => array_slice($activities, 0, self::SUMMARY_ACTIVITY_LIMIT),
                'overflow' => max(0, count($activities) - self::SUMMARY_ACTIVITY_LIMIT),
            ];
            $visibleStart = $from === null ? $start : max($start, new DateTime($from->format('Y-m-d'), 'UTC'));
            $visibleEnd = $until === null ? $end : min($end, new DateTime($until->format('Y-m-d'), 'UTC'));
            $weekday = (int)$visibleStart->format('w');
            $firstSaturday = $visibleStart->addDays($weekday === 0 ? -1 : 6 - $weekday);
            $weekday = (int)$visibleEnd->format('w');
            $lastSaturday = $visibleEnd->addDays($weekday === 5 ? 1 : ($weekday === 6 ? 0 : -$weekday - 1));
            $represented = false;
            // Index each event directly into its overlapping weekends, never scan empty archive years.
            for ($saturday = $firstSaturday; $saturday <= $lastSaturday; $saturday = $saturday->addWeeks(1)) {
                $key = $saturday->format('Y-m');
                if (
                    ($from !== null && $key < $from->format('Y-m'))
                    || ($until !== null && $key > $until->format('Y-m'))
                    || ($selectedMonth !== null && ((int)$saturday->format('n') !== $selectedMonth
                        || $end < $saturday->startOfMonth() || $start > $saturday->endOfMonth()))
                ) {
                    continue;
                }
                $friday = $saturday->subDays(1);
                $continuing = $friday > $event['firstWeekend'];
                $buckets[$key][] = $this->eventRow(
                    $event,
                    $continuing ? $friday : $start,
                    $saturday->addDays(1),
                    $key,
                    $continuing,
                );
                $weekends[$key][$saturday->format('Y-m-d')] = true;
                $represented = true;
            }
            if ($represented) {
                continue;
            }
            // Weekday-only events, or a weekday portion left by an explicit date filter.
            for ($month = $visibleStart->startOfMonth(); $month <= $visibleEnd; $month = $month->addMonths(1)) {
                if ($selectedMonth !== null && (int)$month->format('n') !== $selectedMonth) {
                    continue;
                }
                $key = $month->format('Y-m');
                $date = max($visibleStart, $month);
                $buckets[$key][] = $this->eventRow($event, $date, $end, $key, $date > $start);
            }
        }
        if ($includeEmptyWeekends && $from !== null && $until !== null) {
            for ($month = $from->startOfMonth(); $month <= $until; $month = $month->addMonths(1)) {
                if ($selectedMonth === null || (int)$month->format('n') === $selectedMonth) {
                    $buckets[$month->format('Y-m')] ??= [];
                }
            }
        }
        ksort($buckets);
        $months = [];
        foreach ($buckets as $key => $rows) {
            $month = new DateTime($key . '-01', 'UTC');
            $count = count(array_unique(array_map(static fn($row) => $row['event']['gathering']->id, $rows)));
            if ($includeEmptyWeekends) {
                $saturday = $month->addDays((6 - (int)$month->format('w') + 7) % 7);
                $monthEnd = $month->endOfMonth();
                for (; $saturday <= $monthEnd; $saturday = $saturday->addWeeks(1)) {
                    $friday = $saturday->subDays(1);
                    $sunday = $saturday->addDays(1);
                    if (
                        !isset($weekends[$key][$saturday->format('Y-m-d')])
                        && ($from === null || $sunday->format('Y-m-d') >= $from->format('Y-m-d'))
                        && ($until === null || $friday->format('Y-m-d') <= $until->format('Y-m-d'))
                    ) {
                        $rows[] = ['start' => $friday, 'end' => $sunday, 'empty' => true];
                    }
                }
            }
            usort($rows, static fn($a, $b): int => $a['start'] <=> $b['start']
                ?: (($a['event']['gathering']->id ?? 0) <=> ($b['event']['gathering']->id ?? 0)));
            $months[$key] = [
                'label' => $month->format('F Y') . AnnoSocietatis::suffix($month),
                'rows' => $rows,
                'count' => $count,
            ];
        }

        return $months;
    }

    /**
     * @param \App\Model\Entity\Gathering $gathering Event to localize
     * @return array{?\Cake\I18n\DateTime, ?\Cake\I18n\DateTime} Date-only UTC values representing event-local calendar days
     */
    private function localDays(Gathering $gathering): array
    {
        $start = TimezoneHelper::toUserTimezone($gathering->start_date, null, null, $gathering);
        $end = TimezoneHelper::toUserTimezone($gathering->end_date, null, null, $gathering);

        return [
            $start === null ? null : new DateTime($start->format('Y-m-d'), 'UTC'),
            $end === null ? null : new DateTime($end->format('Y-m-d'), 'UTC'),
        ];
    }

    /**
     * @param \Cake\I18n\DateTime|null $start Event-local start day
     * @param \Cake\I18n\DateTime|null $end Event-local end day
     * @param \Cake\I18n\DateTime|null $from First requested day
     * @param \Cake\I18n\DateTime|null $until Last requested day
     * @param int|null $selectedMonth Month across years
     * @return bool Whether the event overlaps the requested local dates
     */
    private function matchesDates(
        ?DateTime $start,
        ?DateTime $end,
        ?DateTime $from,
        ?DateTime $until,
        ?int $selectedMonth,
    ): bool {
        if (
            !$start || !$end || ($from !== null && $end->format('Y-m-d') < $from->format('Y-m-d'))
            || ($until !== null && $start->format('Y-m-d') > $until->format('Y-m-d'))
        ) {
            return false;
        }
        if ($selectedMonth === null) {
            return true;
        }
        $start = $from === null ? $start : max($start, new DateTime($from->format('Y-m-d'), 'UTC'));
        $end = $until === null ? $end : min($end, new DateTime($until->format('Y-m-d'), 'UTC'));
        $month = $start->startOfYear()->addMonths($selectedMonth - 1);
        if ($month->endOfMonth() < $start) {
            $month = $month->addYears(1);
        }

        return $month <= $end;
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
        $rowEnd = $continuing ? min($end, $event['end']) : $event['end'];

        return [
            'start' => $start,
            'end' => $rowEnd,
            'event' => $event,
            'empty' => false,
            'continuing' => $continuing,
            'key' => $event['gathering']->id . '-' . $month . '-' . $start->format('Ymd'),
        ];
    }
}
