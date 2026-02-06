<?php

/**
 * Calendar Week View Element
 *
 * Displays gatherings for the current week in a timeline format.
 *
 * @var \App\View\AppView $this
 * @var \Cake\ORM\ResultSet $gatherings
 * @var \DateTime $startDate
 * @var bool $canAddGathering
 */

use Cake\I18n\DateTime;

// Get current user for timezone conversion
$currentUser = $this->getRequest()->getAttribute('identity');
$userTimezone = \App\KMP\TimezoneHelper::getUserTimezone($currentUser);

// Calculate current week
// Clone to avoid mutating the original $startDate
$weekStart = clone $startDate;
$dayOfWeek = (int)$weekStart->format('w');
if ($dayOfWeek > 0) {
    $weekStart = $weekStart->modify("-{$dayOfWeek} days");
}

// Clone weekStart to create weekEnd
$weekEnd = (clone $weekStart)->modify('+6 days');

// Group gatherings by date - convert to user's timezone for proper day assignment
$gatheringsByDate = [];
foreach ($gatherings as $gathering) {
    // Convert UTC dates to user's timezone using the core TimezoneHelper
    $startInUserTz = \App\KMP\TimezoneHelper::toUserTimezone($gathering->start_date, $currentUser);
    $endInUserTz = \App\KMP\TimezoneHelper::toUserTimezone($gathering->end_date, $currentUser);

    $start = new DateTime($startInUserTz->format('Y-m-d'));
    $end = new DateTime($endInUserTz->format('Y-m-d'));

    $current = $start;
    $maxDays = 365; // Safety limit
    $dayCount = 0;
    while ($current <= $end && $current <= $weekEnd && $dayCount < $maxDays) {
        if ($current >= $weekStart) {
            $dateKey = $current->format('Y-m-d');
            if (!isset($gatheringsByDate[$dateKey])) {
                $gatheringsByDate[$dateKey] = [];
            }
            $gatheringsByDate[$dateKey][] = $gathering;
        }
        $current = $current->modify('+1 day');
        $dayCount++;
    }
}

// Get current date in user's timezone
$today = new DateTime('now', new \DateTimeZone($userTimezone));
$today->setTime(0, 0, 0);
$canAddGathering = $canAddGathering ?? false;
?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            Week of <?= $this->Timezone->format($weekStart, null, 'F j') ?> -
            <?= $this->Timezone->format($weekEnd, null, 'F j, Y') ?>
        </h5>
    </div>
    <div class="card-body">
        <?php
        $current = clone $weekStart;
        $maxDays = 7; // One week
        $dayCount = 0;

        while ($current <= $weekEnd && $dayCount < $maxDays):
            $dateKey = $current->format('Y-m-d');
            $isToday = ($current->format('Y-m-d') == $today->format('Y-m-d'));
        ?>
            <div class="mb-4">
                <h6 class="border-bottom pb-2 d-flex align-items-center <?= $isToday ? 'text-primary fw-bold' : '' ?>">
                    <span><?= $this->Timezone->format($current, null, 'l, F j') ?></span>
                    <?php if ($isToday): ?>
                        <span class="badge bg-primary ms-2">Today</span>
                    <?php endif; ?>
                    <?php if ($canAddGathering): ?>
                        <a href="<?= $this->Url->build(['action' => 'add', '?' => ['start_date' => $current->format('Y-m-d') . 'T09:00']]) ?>"
                           class="ms-auto text-success" title="<?= __('Add gathering on this date') ?>"
                           data-turbo-frame="_top">
                            <i class="bi bi-plus-circle"></i>
                        </a>
                    <?php endif; ?>
                </h6>

                <?php if (isset($gatheringsByDate[$dateKey]) && count($gatheringsByDate[$dateKey]) > 0): ?>
                    <div class="list-group">
                        <?php foreach ($gatheringsByDate[$dateKey] as $gathering): ?>
                            <?php
                            $isAttending = !empty($gathering->gathering_attendances);
                            $hasLocation = !empty($gathering->location);
                            $bgColor = $gathering->gathering_type->color ?? '#0d6efd';
                            $isCancelled = $gathering->cancelled_at !== null;
                            ?>
                            <div class="list-group-item <?= $isCancelled ? 'bg-light' : '' ?>" style="border-left: 4px solid <?= h($bgColor) ?>;">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1 <?= $isCancelled ? 'text-decoration-line-through text-muted' : '' ?>">
                                            <?= $this->Html->link(
                                                h($gathering->name),
                                                ['action' => 'view', $gathering->public_id],
                                                ['data-turbo-frame' => '_top']
                                            ) ?>
                                            <?php if ($isCancelled): ?>
                                                <span class="badge bg-danger ms-2"><?= __('CANCELLED') ?></span>
                                            <?php elseif ($isAttending): ?>
                                                <span class="badge bg-success ms-2">
                                                    <i class="bi bi-check-circle"></i>
                                                </span>
                                            <?php endif; ?>
                                        </h6>
                                        <p class="mb-1 small">
                                            <i class="bi bi-geo-alt"></i> <?= h($gathering->branch->name) ?>
                                            <?php if ($hasLocation): ?>
                                                - <?= h($gathering->location) ?>
                                            <?php endif; ?>
                                        </p>
                                        <?php if (!empty($gathering->gathering_activities)): ?>
                                            <p class="mb-0 small text-muted">
                                                <i class="bi bi-activity"></i>
                                                <?php
                                                $activityNames = array_map(
                                                    fn($a) => h($a->name),
                                                    $gathering->gathering_activities
                                                );
                                                echo implode(', ', $activityNames);
                                                ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <span class="badge bg-secondary">
                                            <?= h($gathering->gathering_type->name) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted ms-3"><em>No gatherings scheduled</em></p>
                <?php endif; ?>
            </div>
        <?php
            $current = $current->modify('+1 day');
            $dayCount++;
        endwhile;
        ?>
    </div>
</div>