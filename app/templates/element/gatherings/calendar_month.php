<?php
declare(strict_types=1);

/**
 * Calendar Month View Element
 *
 * Displays gatherings in a traditional month calendar grid.
 *
 * @var \App\View\AppView $this
 * @var \Cake\ORM\ResultSet $gatherings
 * @var \DateTime $calendarStart
 * @var \DateTime $calendarEnd
 * @var \DateTime $startDate
 * @var \DateTime $endDate
 * @var string $todayDate
 * @var bool $canAddGathering
 */

use Cake\I18n\DateTime;

// Get current user for timezone conversion
$currentUser = $this->getRequest()->getAttribute('identity');

// Group gatherings by date for efficient lookup
// Convert gathering dates to gathering's timezone for correct multi-day detection and day assignment
$gatheringsByDate = [];
foreach ($gatherings as $gathering) {
    // Convert UTC dates to gathering's timezone using the core TimezoneHelper
    $startInUserTz = \App\KMP\TimezoneHelper::toUserTimezone($gathering->start_date, null, null, $gathering);
    $endInUserTz = \App\KMP\TimezoneHelper::toUserTimezone($gathering->end_date, null, null, $gathering);

    // Skip if timezone conversion failed
    if ($startInUserTz === null || $endInUserTz === null) {
        continue;
    }

    $start = new DateTime($startInUserTz->format('Y-m-d'));
    $end = new DateTime($endInUserTz->format('Y-m-d'));

    // Add gathering to each day it spans
    $current = $start;
    $maxDays = 365; // Safety limit
    $dayCount = 0;
    while ($current <= $end && $dayCount < $maxDays) {
        $dateKey = $current->format('Y-m-d');
        if (!isset($gatheringsByDate[$dateKey])) {
            $gatheringsByDate[$dateKey] = [];
        }
        $gatheringsByDate[$dateKey][] = $gathering;
        $current = $current->modify('+1 day');
        $dayCount++;
    }
}

// Get current date in user's timezone
$userTimezone = \App\KMP\TimezoneHelper::getUserTimezone($currentUser);
$today = new DateTime($todayDate ?? 'now', new \DateTimeZone($userTimezone));
$today->setTime(0, 0, 0);
$canAddGathering = $canAddGathering ?? false;
?>

<div class="card">
    <div class="card-body p-0">
        <div class="calendar-grid">
            <!-- Day Headers -->
            <?php
            $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            foreach ($dayNames as $dayName):
            ?>
            <div class="calendar-day-header">
                <?= h($dayName) ?>
            </div>
            <?php endforeach; ?>

            <!-- Calendar Days -->
            <?php
            $current = clone $calendarStart;
            $maxDays = 42; // Maximum 6 weeks of 7 days
            $dayCount = 0;

            while ($current <= $calendarEnd && $dayCount < $maxDays):
                $dateKey = $current->format('Y-m-d');
                $isCurrentMonth = ($current->format('m') == $startDate->format('m'));
                $isToday = ($current->format('Y-m-d') == $today->format('Y-m-d'));
                $dayClasses = ['calendar-day'];

                if (!$isCurrentMonth) {
                    $dayClasses[] = 'other-month';
                }
                if ($isToday) {
                    $dayClasses[] = 'today';
                }
            ?>
            <div class="<?= implode(' ', $dayClasses) ?>"
                <?= $isToday
                    ? 'id="gatherings-calendar-today" data-calendar-date="' . h($dateKey)
                        . '" aria-current="date" tabindex="-1" aria-label="'
                        . h(__('Today, {0}', $today->format('F j, Y'))) . '"'
                    : '' ?>>
                <div class="calendar-day-number">
                    <?= $this->Timezone->format($current, null, 'j') ?>
                </div>

                <?php if (isset($gatheringsByDate[$dateKey])): ?>
                <?php foreach ($gatheringsByDate[$dateKey] as $gathering): ?>
                <?php
                            // Convert gathering dates to gathering's timezone for correct multi-day detection
                            $startInUserTz = \App\KMP\TimezoneHelper::toUserTimezone($gathering->start_date, null, null, $gathering);
                            $endInUserTz = \App\KMP\TimezoneHelper::toUserTimezone($gathering->end_date, null, null, $gathering);

                            // Skip if timezone conversion failed
                            if ($startInUserTz === null || $endInUserTz === null) {
                                continue;
                            }

                            $isAttending = !empty($gathering->gathering_attendances);
                            // Compare dates in the event's timezone, not UTC
                            $isMultiDay = $startInUserTz->format('Y-m-d') !== $endInUserTz->format('Y-m-d');
                            $hasLocation = !empty($gathering->location);
                            $isCancelled = $gathering->is_cancelled ?? false;
                            $itemClasses = ['gathering-item'];

                            if ($isMultiDay) {
                                $itemClasses[] = 'multi-day';
                            }
                            if ($isAttending) {
                                $itemClasses[] = 'attending';
                            }
                            if ($isCancelled) {
                                $itemClasses[] = 'cancelled';
                            }

                            // Use gathering type color if available, grey for cancelled
                            $bgColor = $isCancelled ? '#6c757d' : ($gathering->gathering_type->color ?? '#0d6efd');

                            $gatheringContent = '';
                            if ($isCancelled) {
                                $gatheringContent .= '<div class="badge bg-danger mb-1 w-100"><kmp-icon name="circle-x"  aria-hidden="true"></kmp-icon> CANCELLED</div>';
                            }
                            $gatheringContent .= '<div class="fw-bold' . ($isCancelled ? ' text-decoration-line-through' : '') . '">' . h($gathering->name) . '</div>';
                            $gatheringContent .= '<div class="text-muted small text-truncate">' . h($gathering->branch->name) . '</div>';
                            $gatheringContent .= '<div class="gathering-badges">';
                            if ($isAttending) {
                                $gatheringContent .= '<span class="badge bg-success" title="I\'m attending" aria-label="I\'m attending"><kmp-icon name="circle-check"  aria-hidden="true"></kmp-icon></span>';
                            }
                            if ($hasLocation) {
                                $gatheringContent .= '<span class="badge bg-info text-dark" title="Has location" aria-label="Has location"><kmp-icon name="map-pin"  aria-hidden="true"></kmp-icon></span>';
                            }
                            if ($isMultiDay) {
                                $gatheringContent .= '<span class="badge bg-warning text-dark" title="Multi-day event" aria-label="Multi-day event"><kmp-icon name="calendar-week"  aria-hidden="true"></kmp-icon></span>';
                            }
                            foreach ($gathering->gathering_activities as $activity) {
                                $gatheringContent .= $this->GatheringActivity->badge($activity, true);
                            }
                            $gatheringContent .= '</div>';
                            ?>
                <?= $this->Html->link(
                                $gatheringContent,
                                ['action' => 'quick-view', $gathering->public_id],
                                [
                                    'escape' => false,
                                    'class' => implode(' ', $itemClasses),
                                    'style' => "background-color: {$bgColor}22; border-left-color: {$bgColor}; display: block; text-decoration: none; color: inherit;",
                                    'title' => h($gathering->name),
                                    'data-turbo-frame' => 'gatheringQuickView',
                                    'data-action' => 'click->gatherings-calendar#showQuickView'
                                ]
                            ) ?>
                <?php endforeach; ?>
                <?php endif; ?>

                <?php if ($canAddGathering): ?>
                <a href="<?= $this->Url->build(['action' => 'add', '?' => ['start_date' => $dateKey . 'T09:00']]) ?>"
                   class="calendar-day-add" title="<?= __('Add gathering on this date') ?>"
                   aria-label="<?= __('Add gathering on this date') ?>"
                   data-turbo-frame="_top">
                    <kmp-icon name="circle-plus"  aria-hidden="true"></kmp-icon>
                </a>
                <?php endif; ?>
            </div>
            <?php
                $current = $current->modify('+1 day');
                $dayCount++;
            endwhile;
            ?>
        </div>
    </div>
</div>
