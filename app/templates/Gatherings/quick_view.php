<?php

/**
 * Gathering Quick View Template
 *
 * Displays gathering details in a modal for the calendar view.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 * @var \App\Model\Entity\GatheringAttendance|null $userAttendance
 */

use Cake\I18n\DateTime;
use Cake\I18n\Date;

$today = Date::now();
$endDate = Date::parse($gathering->end_date->format('Y-m-d'));
$isPastEvent = $endDate < $today; // Event has ended
$isCancelled = $gathering->is_cancelled ?? false;
$showAttendanceControls = isset($canAttend)
    ? (bool)$canAttend && !$isCancelled
    : !$isPastEvent && !$isCancelled;
?>

<turbo-frame id="gatheringQuickView">
    <div class="gathering-quick-view">
        <?php if ($isCancelled): ?>
        <!-- Cancelled Banner -->
        <div class="alert alert-danger mb-3" role="alert">
            <h5 class="alert-heading mb-1">
                <kmp-icon name="alert-triangle" aria-hidden="true" ></kmp-icon>
                <?= __('EVENT CANCELLED') ?>
            </h5>
            <?php if (!empty($gathering->cancellation_reason)): ?>
                <p class="mb-0 small"><?= h($gathering->cancellation_reason) ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Gathering Header -->
        <div class="mb-3">
            <div class="d-flex align-items-start justify-content-between">
                <div class="flex-grow-1">
                    <h4 class="mb-1 <?= $isCancelled ? 'text-decoration-line-through text-muted' : '' ?>"><?= h($gathering->name) ?></h4>
                    <span class="badge" style="background-color: <?= h($gathering->gathering_type->color) ?>;">
                        <?= h($gathering->gathering_type->name) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Date and Location -->
        <div class="row mb-3">
            <div class="col-md-6">
                <h6><kmp-icon name="calendar-event" aria-hidden="true" ></kmp-icon> Date & Time</h6>
                <?php if ($gathering->start_date->format('Y-m-d') === $gathering->end_date->format('Y-m-d')): ?>
                    <p>
                        <?= $this->Timezone->format($gathering->start_date, 'l, F j, Y', false, null, $gathering) ?><br>
                        <small class="text-muted">
                            <?= $this->Timezone->format($gathering->start_date, 'g:i A', false, null, $gathering) ?>
                            <kmp-icon name="arrow-right" aria-hidden="true" ></kmp-icon>
                            <?= $this->Timezone->format($gathering->end_date, 'g:i A', false, null, $gathering) ?>
                        </small>
                    </p>
                <?php else: ?>
                    <p>
                        <?= $this->Timezone->format($gathering->start_date, 'M j, Y g:i A', false, null, $gathering) ?>
                        <kmp-icon name="arrow-right" aria-hidden="true" ></kmp-icon>
                        <?= $this->Timezone->format($gathering->end_date, 'M j, Y g:i A', false, null, $gathering) ?>
                        <br>
                        <small class="text-muted">
                            <?php
                            $startDate = Date::parse($gathering->start_date->format('Y-m-d'));
                            $endDate = Date::parse($gathering->end_date->format('Y-m-d'));
                            ?>
                            <?= $startDate->diffInDays($endDate) + 1 ?> days
                        </small>
                    </p>
                <?php endif; ?>
                <?php if (!empty($gathering->timezone)): ?>
                    <small class="text-muted">
                        <kmp-icon name="clock" aria-hidden="true" ></kmp-icon> <?= h($gathering->timezone) ?>
                        (<?= $this->Timezone->getAbbreviation($gathering->start_date, $gathering->timezone) ?>)
                    </small>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <h6><kmp-icon name="map-pin" aria-hidden="true" ></kmp-icon> Location</h6>
                <p>
                    <strong><?= h($gathering->branch->name) ?></strong><br>
                    <?php if (!empty($gathering->location)): ?>
                        <small><?= h($gathering->location) ?></small>
                    <?php else: ?>
                        <small class="text-muted">Location TBD</small>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <!-- Location Map & Navigation (if location exists) -->
        <?php if (!empty($gathering->location)): ?>
            <div class="mb-3">
                <h6 class="text-muted mb-2">
                    <kmp-icon name="map-pin" aria-hidden="true" ></kmp-icon> <?= __('Location') ?>
                </h6>

                <?php if (!empty($gathering->latitude) && !empty($gathering->longitude)): ?>
                    <!-- Small Map Preview -->
                    <div class="mb-2 border rounded overflow-hidden" style="height: 200px; background-color: #e9ecef;">
                        <iframe width="100%" height="200" style="border:0" loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            src="https://www.google.com/maps/embed/v1/place?key=<?= h($this->KMP->getAppSetting('GoogleMaps.ApiKey', '')) ?>&q=<?= h($gathering->latitude) ?>,<?= h($gathering->longitude) ?>&zoom=14">
                        </iframe>
                    </div>
                <?php endif; ?>

                <!-- Navigation Buttons -->
                <h6 class="text-muted mb-2 mt-3">
                    <kmp-icon name="sign-right" aria-hidden="true" ></kmp-icon> <?= __('Navigate') ?>
                </h6>
                <div class="d-flex gap-2 flex-wrap">
                    <?php
                    // Use precise coordinates if available, otherwise fall back to address string
                    $mapQuery = (!empty($gathering->latitude) && !empty($gathering->longitude))
                        ? $gathering->latitude . ',' . $gathering->longitude
                        : urlencode($gathering->location);
                    ?>
                    <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $mapQuery ?>" target="_blank"
                        class="btn btn-primary btn-sm" data-turbo-frame="_top"
                        title="<?= __('Get directions in Google Maps') ?>">
                        <kmp-icon name="sign-right" aria-hidden="true" ></kmp-icon> <?= __('Get Directions') ?>
                    </a>

                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <kmp-icon name="external-link" aria-hidden="true" ></kmp-icon> <?= __('Open In...') ?>
                        </button>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item"
                                    href="https://www.google.com/maps/search/?api=1&query=<?= $mapQuery ?>" target="_blank"
                                    data-turbo-frame="_top">
                                    <kmp-icon name="brand-google" aria-hidden="true" ></kmp-icon> <?= __('Google Maps') ?>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item"
                                    href="https://maps.apple.com/?<?= (!empty($gathering->latitude) && !empty($gathering->longitude)) ? 'll=' . $gathering->latitude . ',' . $gathering->longitude . '&q=' . urlencode($gathering->location) : 'q=' . urlencode($gathering->location) ?>"
                                    target="_blank" data-turbo-frame="_top">
                                    <kmp-icon name="brand-apple" aria-hidden="true" ></kmp-icon> <?= __('Apple Maps') ?>
                                </a>
                            </li>
                        </ul>
                    </div>

                    <button type="button" class="btn btn-outline-secondary btn-sm"
                        data-controller="clipboard"
                        data-action="clipboard#copy"
                        data-clipboard-text-value="<?= h($gathering->location) ?>"
                        data-clipboard-success-message-value="<?= h(__('Address copied to clipboard!')) ?>"
                        title="<?= h(__('Copy address to clipboard')) ?>"><kmp-icon name="clipboard" aria-hidden="true"></kmp-icon>
                        <?= __(' Copy Address') ?>
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <!-- Description -->
        <!-- Description -->
        <?php if (!empty($gathering->description)): ?>
            <div class="mb-3">
                <h6 class="text-muted mb-2">
                    <kmp-icon name="file-text" aria-hidden="true" ></kmp-icon> <?= __('Description') ?>
                </h6>
                <div class="markdown-content">
                    <?= $this->Markdown->toHtml($gathering->description) ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Activities -->
        <?php if (!empty($gathering->gathering_activities)): ?>
            <div class="mb-3">
                <h6><kmp-icon name="activity" aria-hidden="true" ></kmp-icon> Activities</h6>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($gathering->gathering_activities as $activity): ?>
                        <?= $this->GatheringActivity->badge($activity) ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Attendance -->
        <?php if (!empty($gathering->gathering_attendances)): ?>
            <div class="mb-3">
                <h6><kmp-icon name="users" aria-hidden="true" ></kmp-icon>
                    Attendees (<?= count($gathering->gathering_attendances) ?>)</h6>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($gathering->gathering_attendances as $attendance): ?>
                        <?php if (isset($attendance->member)): ?>
                            <span class="badge bg-light text-dark border">
                                <?= h($attendance->member->sca_name) ?>
                            </span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if (count($gathering->gathering_attendances) >= 10): ?>
                        <span class="badge bg-light text-dark border">
                            <kmp-icon name="dots" aria-hidden="true" ></kmp-icon> more
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- User Attendance Status & Actions -->
        <div class="mb-3">
            <?php if ($isPastEvent): ?>
                <div class="alert alert-secondary" role="alert">
                    <kmp-icon name="info-circle" aria-hidden="true" class="me-2"></kmp-icon>
                    <?= __('This gathering has already ended.') ?>
                </div>
            <?php elseif ($isCancelled && !$userAttendance): ?>
                <div class="alert alert-warning" role="alert">
                    <kmp-icon name="alert-triangle" aria-hidden="true" class="me-2"></kmp-icon>
                    <?= __('This gathering has been cancelled. New attendance registrations are not accepted.') ?>
                </div>
            <?php elseif ($userAttendance): ?>
                <div class="alert <?= $isCancelled ? 'alert-warning' : 'alert-success' ?> d-flex align-items-center" role="alert">
                    <?= $this->Icon->render($isCancelled ? 'alert-triangle' : 'circle-check', ['class' => 'me-2']) ?>
                    <div class="flex-grow-1">
                        <?php if ($isCancelled): ?>
                            <strong><?= __('You were registered for this cancelled gathering.') ?></strong>
                        <?php else: ?>
                            <strong><?= __('You\'re attending this gathering!') ?></strong>
                        <?php endif; ?>
                        <?php if (!empty($userAttendance->notes)): ?>
                            <br><small><?= h($userAttendance->notes) ?></small>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-<?= $isCancelled ? 'secondary' : 'success' ?> ms-2"
                        data-action="click->gatherings-calendar#showAttendanceModal"
                        data-attendance-id="<?= $userAttendance->id ?>" data-gathering-id="<?= $gathering->id ?>"
                        data-attendance-action="edit" data-attendance-notes="<?= h($userAttendance->notes ?? '') ?>"
                        data-attendance-share-kingdom="<?= $userAttendance->share_with_kingdom ? '1' : '0' ?>">
                        <kmp-icon name="pencil" aria-hidden="true" ></kmp-icon> <?= __('Edit') ?>
                    </button>
                </div>
            <?php else: ?>
                <button type="button" class="btn btn-success w-100"
                    data-action="click->gatherings-calendar#showAttendanceModal" data-gathering-id="<?= $gathering->id ?>"
                    data-attendance-action="add">
                    <kmp-icon name="calendar-check" aria-hidden="true" ></kmp-icon> <?= __('Mark Your Attendance') ?>
                </button>
            <?php endif; ?>
        </div>

        <!-- Action Buttons -->
        <div class="d-grid gap-2 d-md-flex justify-content-md-start mt-4 pt-3 border-top">
            <?php if (!$isPastEvent && $gathering->is_preregistration_open): ?>
                <a href="<?= h($gathering->preregister_url) ?>" target="_blank" rel="noopener"
                    class="btn btn-warning fw-bold"
                    title="<?= h(__('Pre-register and pay for this event (external site)')) ?>">
                    <kmp-icon name="ticket" aria-hidden="true" ></kmp-icon> Pre-Register
                    <?php if ($gathering->preregister_closes_on !== null): ?>
                        <small>(<?= __('until {0}', h($gathering->preregister_closes_on->format('M j'))) ?>)</small>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
            <?php if (!$isPastEvent): ?>
                <?= $this->Html->link(
                    '<kmp-icon name="calendar-plus" aria-hidden="true" ></kmp-icon> Add to Calendar',
                    ['action' => 'downloadCalendar', $gathering->public_id],
                    ['class' => 'btn btn-outline-success', 'escape' => false, 'data-turbo-frame' => '_top']
                ) ?>
            <?php endif; ?>
            <?= $this->Html->link(
                '<kmp-icon name="eye" aria-hidden="true" ></kmp-icon> Full Details',
                ['action' => 'view', $gathering->public_id],
                [
                    'class' => 'btn btn-primary', 'escape' => false,
                    'data-turbo-frame' => '_top', 'data-turbo' => 'false',
                ],
            ) ?>
        </div>
    </div>

    <style>
        .gathering-quick-view h6 {
            color: #6c757d;
            font-size: 0.875rem;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .gathering-quick-view .badge {
            font-size: 0.75rem;
            padding: 0.35em 0.65em;
        }

        .gathering-quick-view .gap-2 {
            gap: 0.5rem !important;
        }

        .gathering-description {
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .gathering-description a {
            color: #0d6efd;
            text-decoration: underline;
        }

        .gathering-description h1,
        .gathering-description h2,
        .gathering-description h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-top: 1rem;
            margin-bottom: 0.5rem;
        }

        .gathering-description ul,
        .gathering-description ol {
            padding-left: 1.5rem;
        }
    </style>
</turbo-frame>
