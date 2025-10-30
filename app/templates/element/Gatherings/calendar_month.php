<?php

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
 */

use Cake\I18n\DateTime;

// Group gatherings by date for efficient lookup
$gatheringsByDate = [];
foreach ($gatherings as $gathering) {
    $start = new DateTime($gathering->start_date->format('Y-m-d'));
    $end = new DateTime($gathering->end_date->format('Y-m-d'));

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

$today = new DateTime();
$today->setTime(0, 0, 0);
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
                <div class="<?= implode(' ', $dayClasses) ?>">
                    <div class="calendar-day-number">
                        <?= $current->format('j') ?>
                    </div>

                    <?php if (isset($gatheringsByDate[$dateKey])): ?>
                        <?php foreach ($gatheringsByDate[$dateKey] as $gathering): ?>
                            <?php
                            $isAttending = !empty($gathering->gathering_attendances);
                            $isMultiDay = !$gathering->start_date->equals($gathering->end_date);
                            $hasLocation = !empty($gathering->location);
                            $itemClasses = ['gathering-item'];

                            if ($isMultiDay) {
                                $itemClasses[] = 'multi-day';
                            }
                            if ($isAttending) {
                                $itemClasses[] = 'attending';
                            }

                            // Use gathering type color if available
                            $bgColor = $gathering->gathering_type->color ?? '#0d6efd';

                            $gatheringContent = '<div class="fw-bold text-truncate">' . h($gathering->name) . '</div>';
                            $gatheringContent .= '<div class="text-muted small text-truncate">' . h($gathering->branch->name) . '</div>';
                            $gatheringContent .= '<div class="gathering-badges">';
                            if ($isAttending) {
                                $gatheringContent .= '<span class="badge bg-success" title="I\'m attending"><i class="bi bi-check-circle"></i></span>';
                            }
                            if ($hasLocation) {
                                $gatheringContent .= '<span class="badge bg-info" title="Has location"><i class="bi bi-geo-alt"></i></span>';
                            }
                            if ($isMultiDay) {
                                $gatheringContent .= '<span class="badge bg-warning text-dark" title="Multi-day event"><i class="bi bi-calendar-range"></i></span>';
                            }
                            if (count($gathering->gathering_activities) > 0) {
                                $gatheringContent .= '<span class="badge bg-secondary" title="' . count($gathering->gathering_activities) . ' activities"><i class="bi bi-activity"></i> ' . count($gathering->gathering_activities) . '</span>';
                            }
                            $gatheringContent .= '</div>';
                            ?>
                            <?= $this->Html->link(
                                $gatheringContent,
                                ['action' => 'quick-view', $gathering->id],
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
                </div>
            <?php
                $current = $current->modify('+1 day');
                $dayCount++;
            endwhile;
            ?>
        </div>
    </div>
</div>