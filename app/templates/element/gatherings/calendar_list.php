<?php

/**
 * Calendar List View Element
 *
 * Displays gatherings in a detailed list format.
 *
 * @var \App\View\AppView $this
 * @var \Cake\ORM\ResultSet $gatherings
 */

use Cake\I18n\DateTime;

// Get current user for timezone conversion
$currentUser = $this->getRequest()->getAttribute('identity');
$userTimezone = \App\KMP\TimezoneHelper::getUserTimezone($currentUser);

// Get current date in user's timezone
$today = new DateTime('now', new \DateTimeZone($userTimezone));
?>

<div class="card">
    <div class="card-body">
        <?php if ($gatherings->count() === 0): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i>
                No gatherings found for the selected period and filters.
            </div>
        <?php else: ?>
            <div class="list-group">
                <?php foreach ($gatherings as $gathering): ?>
                    <?php
                    // Convert gathering dates to gathering's timezone for display (prioritizes event location timezone)
                    // Pass null for member to use app default timezone fallback, matching Gathering::_getIsMultiDay logic
                    $startInUserTz = \App\KMP\TimezoneHelper::toUserTimezone($gathering->start_date, null, null, $gathering);
                    $endInUserTz = \App\KMP\TimezoneHelper::toUserTimezone($gathering->end_date, null, null, $gathering);

                    $isAttending = !empty($gathering->gathering_attendances);
                    // Compare dates in the event's timezone, not UTC
                    $isMultiDay = $startInUserTz->format('Y-m-d') !== $endInUserTz->format('Y-m-d');
                    $hasLocation = !empty($gathering->location);
                    $isPast = $endInUserTz < $today;
                    $bgColor = $gathering->gathering_type->color ?? '#0d6efd';
                    ?>
                    <div class="list-group-item list-group-item-action"
                        style="border-left: 4px solid <?= h($bgColor) ?>;">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="d-flex w-100 justify-content-between align-items-start">
                                    <h5 class="mb-1">
                                        <?= $this->Html->link(
                                            h($gathering->name),
                                            ['action' => 'view', $gathering->public_id],
                                            ['class' => 'text-decoration-none']
                                        ) ?>
                                        <?php if ($isAttending): ?>
                                            <span class="badge bg-success ms-2">
                                                <i class="bi bi-check-circle"></i> Attending
                                            </span>
                                        <?php endif; ?>
                                    </h5>
                                    <small class="text-muted">
                                        <?= h($gathering->gathering_type->name) ?>
                                    </small>
                                </div>

                                <p class="mb-1">
                                    <i class="bi bi-geo-alt"></i>
                                    <strong><?= h($gathering->branch->name) ?></strong>
                                    <?php if ($hasLocation): ?>
                                        <br>
                                        <small class="text-muted ms-3">
                                            <?= h($gathering->location) ?>
                                        </small>
                                    <?php endif; ?>
                                </p>

                                <p class="mb-1">
                                    <i class="bi bi-calendar-event"></i>
                                    <?php if ($isMultiDay): ?>
                                        <?= $this->Timezone->format($startInUserTz, null, 'M j, Y') ?>
                                        - <?= $this->Timezone->format($endInUserTz, null, 'M j, Y') ?>
                                        <span class="badge bg-warning text-dark ms-2">
                                            <?= $startInUserTz->diffInDays($endInUserTz) + 1 ?> days
                                        </span>
                                    <?php else: ?>
                                        <?= $this->Timezone->format($startInUserTz, null, 'l, F j, Y') ?>
                                    <?php endif; ?>
                                </p>

                                <?php if (!empty($gathering->gathering_activities)): ?>
                                    <p class="mb-1">
                                        <i class="bi bi-activity"></i>
                                        <small>
                                            <?php
                                            $activityNames = array_map(
                                                fn($a) => h($a->name),
                                                $gathering->gathering_activities
                                            );
                                            echo implode(', ', $activityNames);
                                            ?>
                                        </small>
                                    </p>
                                <?php endif; ?>

                                <?php if (!empty($gathering->description)): ?>
                                    <p class="mb-0 text-muted small">
                                        <?= $this->Text->truncate(
                                            h($gathering->description),
                                            200,
                                            ['ellipsis' => '...', 'exact' => false]
                                        ) ?>
                                    </p>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-4 text-end">
                                <div class="btn-group-vertical" role="group">
                                    <?= $this->Html->link(
                                        '<i class="bi bi-eye"></i> View Details',
                                        ['action' => 'view', $gathering->public_id],
                                        [
                                            'class' => 'btn btn-sm btn-outline-primary',
                                            'escape' => false,
                                            'data-turbo-frame' => '_top',
                                        ]
                                    ) ?>

                                    <?php if (!$isPast): ?>
                                        <?php
                                        $attendanceRecord = $isAttending
                                            ? ($gathering->gathering_attendances[0] ?? null)
                                            : null;
                                        ?>
                                        <button type="button"
                                            class="btn btn-sm <?= $isAttending ? 'btn-success' : 'btn-outline-success' ?>"
                                            data-action="click->gatherings-calendar#showAttendanceModal"
                                            data-gathering-id="<?= $gathering->id ?>"
                                            data-attendance-action="<?= $isAttending ? 'edit' : 'add' ?>"
                                            <?php if ($attendanceRecord): ?>
                                            data-attendance-id="<?= $attendanceRecord->id ?>"
                                            <?php endif; ?>>
                                            <i class="bi bi-calendar-check"></i>
                                            <?= $isAttending ? 'Update' : 'Mark' ?> Attendance
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($hasLocation): ?>
                                        <button type="button"
                                            class="btn btn-sm btn-outline-info"
                                            data-action="click->gatherings-calendar#showLocation"
                                            data-gathering-id="<?= $gathering->id ?>">
                                            <i class="bi bi-map"></i> View Map
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>