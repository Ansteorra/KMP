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
$isPastEvent = $gathering->end_date < $today; // Event has ended
?>

<turbo-frame id="gatheringQuickView">
    <div class="gathering-quick-view">
        <!-- Gathering Header -->
        <div class="mb-3">
            <div class="d-flex align-items-start justify-content-between">
                <div class="flex-grow-1">
                    <h4 class="mb-1"><?= h($gathering->name) ?></h4>
                    <span class="badge" style="background-color: <?= h($gathering->gathering_type->color) ?>;">
                        <?= h($gathering->gathering_type->name) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Date and Location -->
        <div class="row mb-3">
            <div class="col-md-6">
                <h6><i class="bi bi-calendar-event"></i> Date</h6>
                <?php if ($gathering->start_date->equals($gathering->end_date)): ?>
                <p><?= $gathering->start_date->format('l, F j, Y') ?></p>
                <?php else: ?>
                <p>
                    <?= $gathering->start_date->format('M j, Y') ?>
                    <i class="bi bi-arrow-right"></i>
                    <?= $gathering->end_date->format('M j, Y') ?>
                    <br>
                    <small class="text-muted">
                        <?= $gathering->start_date->diffInDays($gathering->end_date) + 1 ?> days
                    </small>
                </p>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <h6><i class="bi bi-geo-alt"></i> Location</h6>
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
                <i class="bi bi-geo-alt-fill"></i> <?= __('Location') ?>
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
                <i class="bi bi-signpost-2-fill"></i> <?= __('Navigate') ?>
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
                    <i class="bi bi-signpost-2-fill"></i> <?= __('Get Directions') ?>
                </a>

                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-box-arrow-up-right"></i> <?= __('Open In...') ?>
                    </button>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item"
                                href="https://www.google.com/maps/search/?api=1&query=<?= $mapQuery ?>" target="_blank"
                                data-turbo-frame="_top">
                                <i class="bi bi-google"></i> <?= __('Google Maps') ?>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item"
                                href="https://maps.apple.com/?<?= (!empty($gathering->latitude) && !empty($gathering->longitude)) ? 'll=' . $gathering->latitude . ',' . $gathering->longitude . '&q=' . urlencode($gathering->location) : 'q=' . urlencode($gathering->location) ?>"
                                target="_blank" data-turbo-frame="_top">
                                <i class="bi bi-apple"></i> <?= __('Apple Maps') ?>
                            </a>
                        </li>
                    </ul>
                </div>

                <button type="button bi bi-clipboard" class="btn btn-outline-secondary btn-sm"
                    onclick="navigator.clipboard.writeText(<?= json_encode(h($gathering->location)) ?>).then(() => alert(<?= json_encode(__(' Address copied to clipboard!')) ?>))"
                    title="<?= h(__('Copy address to clipboard')) ?>">
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
                <i class="bi bi-file-text"></i> <?= __('Description') ?>
            </h6>
            <div class="markdown-content">
                <?= $this->Markdown->toHtml($gathering->description) ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Activities -->
        <?php if (!empty($gathering->gathering_activities)): ?>
        <div class="mb-3">
            <h6><i class="bi bi-activity"></i> Activities</h6>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($gathering->gathering_activities as $activity): ?>
                <span class="badge bg-secondary"><?= h($activity->name) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Attendance -->
        <?php if (!empty($gathering->gathering_attendances)): ?>
        <div class="mb-3">
            <h6><i class="bi bi-people"></i> Attendees (<?= count($gathering->gathering_attendances) ?>)</h6>
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
                    <i class="bi bi-three-dots"></i> more
                </span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- User Attendance Status & Actions -->
        <?php if (isset($canAttend) && $canAttend): ?>
        <div class="mb-3">
            <?php if ($userAttendance): ?>
            <div class="alert alert-success d-flex align-items-center" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <div class="flex-grow-1">
                    <strong>You're attending this gathering!</strong>
                    <?php if (!empty($userAttendance->notes)): ?>
                    <br><small><?= h($userAttendance->notes) ?></small>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn btn-sm btn-outline-success ms-2"
                    data-action="click->gatherings-calendar#showAttendanceModal"
                    data-attendance-id="<?= $userAttendance->id ?>" data-gathering-id="<?= $gathering->id ?>"
                    data-attendance-action="edit" data-attendance-notes="<?= h($userAttendance->notes ?? '') ?>"
                    data-attendance-is-public="<?= $userAttendance->is_public ? '1' : '0' ?>"
                    data-attendance-share-kingdom="<?= $userAttendance->share_with_kingdom ? '1' : '0' ?>">
                    <i class="bi bi-pencil"></i> Edit
                </button>
            </div>
            <?php else: ?>
            <button type="button" class="btn btn-success w-100"
                data-action="click->gatherings-calendar#showAttendanceModal" data-gathering-id="<?= $gathering->id ?>"
                data-attendance-action="add">
                <i class="bi bi-calendar-check"></i> Mark Your Attendance
            </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="d-grid gap-2 d-md-flex justify-content-md-start mt-4 pt-3 border-top">
            <?php if (!$isPastEvent): ?>
            <?= $this->Html->link(
                    '<i class="bi bi-calendar-plus"></i> Add to Calendar',
                    ['action' => 'downloadCalendar', $gathering->public_id],
                    ['class' => 'btn btn-outline-success', 'escape' => false, 'data-turbo-frame' => '_top']
                ) ?>
            <?php endif; ?>
            <?= $this->Html->link(
                '<i class="bi bi-eye"></i> Full Details',
                ['action' => 'view', $gathering->public_id],
                ['class' => 'btn btn-primary', 'escape' => false, 'data-turbo-frame' => '_top']
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