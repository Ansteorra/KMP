<?php

/**
 * Public Gathering Content Element (Body Only)
 * 
 * Shared element for displaying public gathering information
 * Used in both the public landing page and the authenticated user's public view
 * 
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 * @var array $scheduleByDate Optional - schedule data grouped by date
 * @var int $durationDays Optional - event duration in days
 * @var \App\Model\Entity\Member|null $user Optional - current authenticated user
 * @var \App\Model\Entity\GatheringAttendance|null $userAttendance Optional - user's attendance record
 * @var array<\App\Model\Entity\GatheringAttendance> $kingdomAttendances Optional - kingdom-shared attendances
 */

use Cake\I18n\Date;
use Cake\I18n\DateTime;

// Check if user is authenticated
$isAuthenticated = isset($user) && $user !== null;
$kingdomAttendances = $kingdomAttendances ?? [];
$progressAttendances = $progressAttendances ?? [];

// Get current time in the gathering's timezone for accurate status
$gatheringTimezone = \App\KMP\TimezoneHelper::getGatheringTimezone($gathering, $this->getRequest()->getAttribute('identity'));
$nowInGatheringTz = \App\KMP\TimezoneHelper::toUserTimezone(DateTime::now(), null, $gatheringTimezone);
$startInGatheringTz = \App\KMP\TimezoneHelper::toUserTimezone($gathering->start_date, null, $gatheringTimezone);
$endInGatheringTz = \App\KMP\TimezoneHelper::toUserTimezone($gathering->end_date, null, $gatheringTimezone);

// Check if event is in the past or ongoing (using actual datetime with timezone)
$isPast = $endInGatheringTz < $nowInGatheringTz;
$isOngoing = $startInGatheringTz <= $nowInGatheringTz && $endInGatheringTz >= $nowInGatheringTz;

// Check if gathering is cancelled
$isCancelled = $gathering->is_cancelled ?? false;

// Check if user can attend (gathering hasn't ended and not cancelled)
// Allow editing existing attendance even if cancelled
$canAttend = !$isPast && $isAuthenticated;
$canCreateAttendance = $canAttend && !$isCancelled;
$canEditAttendance = $canAttend && !empty($userAttendance);

// Calculate duration if not provided (date-only comparison for day count)
if (!isset($durationDays)) {
    $startDate = Date::parse($gathering->start_date->format('Y-m-d'));
    $endDate = Date::parse($gathering->end_date->format('Y-m-d'));
    $durationDays = $startDate->diffInDays($endDate) + 1;
}

// Initialize schedule if not provided
if (!isset($scheduleByDate)) {
    $scheduleByDate = [];
}
?>

<div class="gathering-public-surface" data-controller="public-gathering">
<?php if ($isCancelled): ?>
<!-- Cancelled Banner -->
<div class="alert alert-danger text-center py-4 mb-0" role="alert" style="border-radius: 0; border: none; background: linear-gradient(135deg, #dc3545 0%, #8B0000 100%); color: white;">
    <h2 class="mb-2">
        <kmp-icon name="alert-triangle"  aria-hidden="true"></kmp-icon>
        <?= __('THIS EVENT HAS BEEN CANCELLED') ?>
    </h2>
    <?php if (!empty($gathering->cancellation_reason)): ?>
        <p class="mb-0" style="font-size: 1.1rem;"><?= h($gathering->cancellation_reason) ?></p>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Hero Banner with Medieval Aesthetic -->
<div class="hero-banner fade-in" <?php if ($isCancelled): ?>style="opacity: 0.7;"<?php endif; ?>>
    <div class="hero-banner-ornament hero-banner-ornament-left" aria-hidden="true">⚜</div>
    <div class="hero-banner-ornament hero-banner-ornament-right" aria-hidden="true">⚜</div>

    <div class="hero-banner-content">
        <div class="event-type-badge">
            <?= h($gathering->gathering_type->name) ?>
        </div>

        <h1 class="event-title"><?= h($gathering->name) ?></h1>

        <div class="event-quick-meta">
            <span class="meta-item">
                <kmp-icon name="calendar-month"  aria-hidden="true"></kmp-icon>
                <?php if ($gathering->is_multi_day): ?>
                    <?= $this->Timezone->format($gathering->start_date, 'M d, Y g:i A', false, null, $gathering) ?>
                    - <?= $this->Timezone->format($gathering->end_date, 'M d, Y g:i A', false, null, $gathering) ?>
                <?php else: ?>
                    <?= $this->Timezone->format($gathering->start_date, 'M d, Y', false, null, $gathering) ?><br>
                    <small>
                        <?= $this->Timezone->format($gathering->start_date, 'g:i A', false, null, $gathering) ?>
                        - <?= $this->Timezone->format($gathering->end_date, 'g:i A', false, null, $gathering) ?>
                    </small>
                <?php endif; ?>
            </span>

            <?php if (!empty($gathering->timezone)): ?>
                <span class="meta-item">
                    <kmp-icon name="clock"  aria-hidden="true"></kmp-icon>
                    <?= h($gathering->timezone) ?>
                    (<?= $this->Timezone->getAbbreviation($gathering->start_date, $gathering->timezone) ?>)
                </span>
            <?php endif; ?>

            <?php if ($gathering->location): ?>
                <span class="meta-item">
                    <kmp-icon name="map-pin"  aria-hidden="true"></kmp-icon>
                    <?= h($gathering->location) ?>
                </span>
            <?php endif; ?>

            <span class="meta-item">
                <kmp-icon name="building"  aria-hidden="true"></kmp-icon>
                <?= h($gathering->branch->name) ?>
            </span>

            <?php // The public page supersedes the Event Website; only show the
                  // external site link when the public page is not enabled
                  // (i.e. the authenticated view_public context) ?>
            <?php if (!$gathering->public_page_enabled && !empty($gathering->website_url)): ?>
                <span class="meta-item">
                    <kmp-icon name="link"  aria-hidden="true"></kmp-icon>
                    <a href="<?= h($gathering->website_url) ?>" target="_blank" rel="noopener" class="event-website-link">
                        <?= __('Event Website') ?>
                    </a>
                </span>
            <?php endif; ?>
        </div>

        <?php if (!empty($progressAttendances)): ?>
            <div class="royal-progress-banner" role="note" aria-label="<?= __('Royal Progress') ?>">
                <span class="royal-progress-icon" aria-hidden="true">&#x1F451;</span>
                <span class="royal-progress-text">
                    <strong><?= __('Royal Progress:') ?></strong>
                    <?= h(implode(', ', array_map(
                        fn($attendance) => $attendance->progress_title . ' (' . ($attendance->member->sca_name ?? '') . ')',
                        $progressAttendances,
                    ))) ?>
                </span>
            </div>
        <?php endif; ?>

        <!-- Pre-Registration / Calendar Download Buttons (only for current/future events) -->
        <?php if (!$isPast): ?>
            <div class="mt-3 d-flex flex-wrap gap-2 justify-content-center">
                <?php if ($gathering->is_preregistration_open): ?>
                    <a href="<?= h($gathering->preregister_url) ?>" target="_blank" rel="noopener"
                        class="btn btn-warning btn-lg fw-bold"
                        title="<?= h(__('Pre-register and pay for this event (external site)')) ?>">
                        <kmp-icon name="ticket"  aria-hidden="true"></kmp-icon> <?= __('Pre-Register') ?>
                    </a>
                <?php endif; ?>
                <?= $this->Html->link(
                    '<kmp-icon name="calendar-plus"  aria-hidden="true"></kmp-icon> ' . __('Add to Calendar'),
                    ['controller' => 'Gatherings', 'action' => 'downloadCalendar', $gathering->public_id],
                    [
                        'class' => 'btn btn-outline-light btn-lg',
                        'escape' => false,
                        'title' => __('Download calendar file (.ics) for Outlook, Google Calendar, iOS, etc.')
                    ]
                ) ?>
            </div>
            <?php if ($gathering->is_preregistration_open && $gathering->preregister_closes_on !== null): ?>
                <p class="mt-2 mb-0 small text-white-50">
                    <kmp-icon name="history"  aria-hidden="true"></kmp-icon>
                    <?= __('Pre-registration open until {0}', h($gathering->preregister_closes_on->format('F j, Y'))) ?>
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>


<!-- Event Information Cards -->
<div class="event-container">
    <div class="info-grid fade-in" style="animation-delay: 0.1s;">
        <?php
        // Separate stewards from other staff (only those marked to show on public page)
        $stewards = [];
        $otherStaff = [];
        if (!empty($gathering->gathering_staff)) {
            foreach ($gathering->gathering_staff as $staff) {
                // Skip staff who opted out of public display
                if (empty($staff->show_on_public_page)) {
                    continue;
                }

                if ($staff->is_steward) {
                    $stewards[] = $staff;
                } else {
                    $otherStaff[] = $staff;
                }
            }
        }

        // Sort other staff alphabetically by role
        if (!empty($otherStaff)) {
            usort($otherStaff, function ($a, $b) {
                return strcasecmp($a->role, $b->role);
            });
        }

        // Check if we have any staff to display
        $hasStaff = !empty($stewards) || !empty($otherStaff);

        // Display stewards first
        if (!empty($stewards)):
        ?>
            <div class="info-card-medieval">
                <div class="card-header-medieval">
                    <kmp-icon name="id-badge"  aria-hidden="true"></kmp-icon>
                    <span><?= count($stewards) > 1 ? __('Event Stewards') : __('Event Steward') ?></span>
                </div>
                <div class="card-body-medieval">
                    <?php foreach ($stewards as $steward): ?>
                        <div class="steward-entry">
                            <strong><?= h($steward->display_name) ?></strong>
                            <?php if (!empty($steward->email)): ?>
                                <div class="contact-detail">
                                    <kmp-icon name="mail"  aria-hidden="true"></kmp-icon>
                                    <a href="#" class="email-link"
                                        data-email="<?= base64_encode($steward->email) ?>"><?= __('Email') ?></a>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($steward->phone)): ?>
                                <div class="contact-detail">
                                    <kmp-icon name="phone"  aria-hidden="true"></kmp-icon>
                                    <a href="tel:<?= h($steward->phone) ?>"><?= h($steward->phone) ?></a>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($steward->contact_notes)): ?>
                                <div class="contact-note"><?= h($steward->contact_notes) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php
        // Display other staff if any
        if (!empty($otherStaff)):
        ?>
            <div class="info-card-medieval">
                <div class="card-header-medieval">
                    <kmp-icon name="users"  aria-hidden="true"></kmp-icon>
                    <span><?= __('Event Staff') ?></span>
                </div>
                <div class="card-body-medieval">
                    <?php foreach ($otherStaff as $staff): ?>
                        <div class="staff-entry">
                            <strong><?= h($staff->role) ?>:</strong> <?= h($staff->display_name) ?>
                            <?php if (!empty($staff->email) || !empty($staff->phone)): ?>
                                <div class="staff-contacts">
                                    <?php if (!empty($staff->email)): ?>
                                        <a href="#" class="email-link" data-email="<?= base64_encode($staff->email) ?>"
                                            aria-label="<?= h(__('Email {0}', $staff->display_name)) ?>">
                                            <kmp-icon name="mail"  aria-hidden="true"></kmp-icon>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($staff->phone)): ?>
                                        <a href="tel:<?= h($staff->phone) ?>"
                                            aria-label="<?= h(__('Call {0}', $staff->display_name)) ?>">
                                            <kmp-icon name="phone"  aria-hidden="true"></kmp-icon>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($staff->contact_notes)): ?>
                                <div class="contact-note"><?= h($staff->contact_notes) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php
        // Fallback: If no staff configured, show creator
        if (!$hasStaff && $gathering->creator):
        ?>
            <div class="info-card-medieval">
                <div class="card-header-medieval">
                    <kmp-icon name="id-badge"  aria-hidden="true"></kmp-icon>
                    <span><?= __('Gathering Creator') ?></span>
                </div>
                <div class="card-body-medieval">
                    <p><?= h($gathering->creator->sca_name) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Event Status Card -->
        <?php if ($isOngoing): ?>
            <div class="info-card-medieval status-happening">
                <div class="card-header-medieval">
                    <kmp-icon name="broadcast"  aria-hidden="true"></kmp-icon>
                    <span><?= __('Event Status') ?></span>
                </div>
                <div class="card-body-medieval">
                    <div class="status-badge-active"><?= __('Happening Now!') ?></div>
                </div>
            </div>
        <?php elseif ($isPast): ?>
            <div class="info-card-medieval status-past">
                <div class="card-header-medieval">
                    <kmp-icon name="circle-check"  aria-hidden="true"></kmp-icon>
                    <span><?= __('Event Status') ?></span>
                </div>
                <div class="card-body-medieval">
                    <p><?= __('Event Completed') ?></p>
                </div>
            </div>
        <?php else: ?>
            <?php
            // Calculate time until event starts (using actual datetime with timezone)
            $interval = $nowInGatheringTz->diff($startInGatheringTz);
            $daysUntil = $interval->days;
            $hoursUntil = $interval->h;

            if ($daysUntil >= 0):
            ?>
                <div class="info-card-medieval status-upcoming">
                    <div class="card-header-medieval">
                        <kmp-icon name="hourglass"  aria-hidden="true"></kmp-icon>
                        <span><?= __('Starts In') ?></span>
                    </div>
                    <div class="card-body-medieval">
                        <div class="countdown-display">
                            <?php if ($daysUntil == 0 && $hoursUntil < 24): ?>
                                <?php if ($hoursUntil == 0): ?>
                                    <?= __('Starting soon!') ?>
                                <?php elseif ($hoursUntil == 1): ?>
                                    <?= __('1 hour') ?>
                                <?php else: ?>
                                    <?= $hoursUntil ?> <?= __('hours') ?>
                                <?php endif; ?>
                            <?php elseif ($daysUntil == 1): ?>
                                <?= __('Tomorrow') ?>
                            <?php else: ?>
                                <?= $daysUntil ?> <?= __('days') ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>


<!-- Event Description -->
<?php if ($gathering->description): ?>
    <div class="event-container">
        <div class="section-medieval fade-in">
            <h2 class="section-title-medieval">
                <span class="title-ornament">❖</span>
                <?= __('About This Event') ?>
                <span class="title-ornament">❖</span>
            </h2>
            <div class="content-scroll">
                <?= $this->Markdown->toHtml($gathering->description) ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Event Schedule -->
<?php if (!empty($scheduleByDate)): ?>
    <div class="event-container">
        <div class="section-medieval fade-in" style="animation-delay: 0.15s;">
            <h2 class="section-title-medieval">
                <span class="title-ornament">❖</span>
                <?= __('Event Schedule') ?>
                <span class="title-ornament">❖</span>
            </h2>

            <?php foreach ($scheduleByDate as $date => $activities): ?>
                <div class="schedule-day-medieval">
                    <div class="schedule-day-header">
                        <?php
                        // Dates are pre-grouped in gathering timezone by controller.
                        $dateObj = \Cake\I18n\Date::parse($date);
                        echo $dateObj->format('l, F j, Y');
                        ?>
                    </div>

                    <div class="schedule-events">
                        <?php foreach ($activities as $activity): ?>
                            <div class="schedule-event-item">
                                <div class="schedule-time-badge">
                                    <kmp-icon name="clock"  aria-hidden="true"></kmp-icon>
                                    <?= $this->Timezone->format($activity->start_datetime, 'g:i A', false, null, $gathering) ?>
                                    <?php if ($activity->end_datetime): ?>
                                        - <?= $this->Timezone->format($activity->end_datetime, 'g:i A', false, null, $gathering) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="schedule-event-content">
                                    <h4><?= h($activity->display_title) ?></h4>
                                    <?php if ($activity->display_description): ?>
                                        <div class="schedule-event-desc">
                                            <?= $this->Markdown->toHtml($activity->display_description) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($activity->gathering_activity): ?>
                                        <span class="activity-tag">
                                            <?= $this->GatheringActivity->badge($activity->gathering_activity) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php elseif (!empty($gathering->gathering_activities)): ?>
    <!-- Activities List (when no schedule exists) -->
    <div class="event-container">
        <div class="section-medieval fade-in" style="animation-delay: 0.15s;">
            <h2 class="section-title-medieval">
                <span class="title-ornament">❖</span>
                <?= __('Planned Activities') ?>
                <span class="title-ornament">❖</span>
            </h2>

            <div class="activities-compact">
                <?php foreach ($gathering->gathering_activities as $i => $activity): ?>
                    <div class="activity-item-compact">
                        <kmp-icon name="shield-check" class="activity-icon-compact" aria-hidden="true"></kmp-icon>
                        <div class="activity-details-compact">
                            <h4><?= $this->GatheringActivity->badge($activity) ?></h4>
                            <?php
                            // Show custom description if available, otherwise show default description
                            $displayDescription = !empty($activity->_joinData->custom_description)
                                ? $activity->_joinData->custom_description
                                : $activity->description;
                            if ($displayDescription):
                            ?>
                                <p><?= nl2br(h($displayDescription)) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Location & Directions -->
<?php if ($gathering->location): ?>
    <div class="event-container">
        <div class="section-medieval fade-in" style="animation-delay: 0.2s;">
            <h2 class="section-title-medieval">
                <span class="title-ornament">❖</span>
                <?= __('Location & Directions') ?>
                <span class="title-ornament">❖</span>
            </h2>

            <div class="location-display">
                <div class="location-icon">
                    <kmp-icon name="map-pin"  aria-hidden="true"></kmp-icon>
                </div>
                <p class="location-address"><?= nl2br(h($gathering->location)) ?></p>
            </div>

            <?php if ($gathering->latitude !== null && $gathering->longitude !== null): ?>
                <?php
                // Use precise coordinates for navigation
                $mapQuery = $gathering->latitude . ',' . $gathering->longitude;
                $mapQueryEncoded = urlencode($mapQuery);
                $locationEncoded = urlencode($gathering->location);
                ?>

                <!-- Navigation Buttons -->
                <div class="location-actions">
                    <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $mapQuery ?>" target="_blank"
                        class="btn-medieval btn-medieval-primary">
                        <kmp-icon name="sign-right"  aria-hidden="true"></kmp-icon>
                        <?= __('Get Directions') ?>
                    </a>

                    <div class="dropdown">
                        <button type="button" class="btn-medieval btn-medieval-secondary" data-bs-toggle="dropdown"
                            aria-expanded="false">
                            <kmp-icon name="external-link"  aria-hidden="true"></kmp-icon> <?= __('Open In...') ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-medieval">
                            <li>
                                <a class="dropdown-item" href="https://www.google.com/maps/search/?api=1&query=<?= $mapQuery ?>"
                                    target="_blank">
                                    <kmp-icon name="brand-google"  aria-hidden="true"></kmp-icon> Google Maps
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item"
                                    href="https://maps.apple.com/?ll=<?= $mapQuery ?>&q=<?= $locationEncoded ?>"
                                    target="_blank">
                                    <kmp-icon name="brand-apple"  aria-hidden="true"></kmp-icon> Apple Maps
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="https://www.waze.com/ul?ll=<?= $mapQuery ?>&navigate=yes"
                                    target="_blank">
                                    <kmp-icon name="map-pin"  aria-hidden="true"></kmp-icon> Waze
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Google Maps Embed -->
                <div class="map-container">
                    <iframe width="100%" height="350" style="border:0; border-radius: 0.5rem;" loading="lazy" allowfullscreen
                        referrerpolicy="no-referrer-when-downgrade"
                        src="https://www.google.com/maps/embed/v1/place?key=<?= h($this->KMP->getAppSetting('GoogleMaps.ApiKey', '')) ?>&q=<?= urlencode($gathering->location) ?>&center=<?= h($gathering->latitude) ?>,<?= h($gathering->longitude) ?>&zoom=15">
                    </iframe>
                </div>
            <?php else: ?>
                <!-- Fallback for locations without coordinates -->
                <div class="location-actions">
                    <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($gathering->location) ?>"
                        target="_blank" class="btn-medieval btn-medieval-primary">
                        <kmp-icon name="map"  aria-hidden="true"></kmp-icon>
                        <?= __('Search in Google Maps') ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($isAuthenticated && $gathering->public_page_enabled && !empty($kingdomAttendances)): ?>
    <div class="event-container">
        <div class="section-medieval fade-in" style="animation-delay: 0.22s;">
            <h2 class="section-title-medieval">
                <span class="title-ornament">❖</span>
                <?= __('Attendees Sharing with the Kingdom') ?> (<?= count($kingdomAttendances) ?>)
                <span class="title-ornament">❖</span>
            </h2>
            <div class="content-scroll">
                <?php foreach ($kingdomAttendances as $attendance): ?>
                    <?php if (isset($attendance->member)): ?>
                        <div class="staff-entry">
                            <strong><?= h($attendance->member->sca_name) ?></strong>
                            <?php if (!empty($attendance->public_note)): ?>
                                <div class="contact-note"><?= h($attendance->public_note) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>


<!-- Call to Action -->
<?php if (!$isPast): ?>
    <div class="event-container">
        <div class="cta-medieval fade-in" style="animation-delay: 0.25s;">
            <div class="cta-ornament-top">⚔ ⚔ ⚔</div>

            <h2><?= __('Ready to Join Us?') ?></h2>
            <p>
                <?php if ($isOngoing): ?>
                    <?= __('This event is happening now! Come join the festivities.') ?>
                <?php else: ?>
                    <?= __('Mark your calendar and prepare for an amazing experience!') ?>
                <?php endif; ?>
            </p>

            <div class="cta-buttons">
                <?php if ($isAuthenticated && ($canCreateAttendance || $canEditAttendance)): ?>
                    <!-- Authenticated user - show Attend/Update button -->
                    <button type="button" class="btn-medieval btn-medieval-cta" data-bs-toggle="modal"
                        data-bs-target="#attendGatheringModal">
                        <kmp-icon name="calendar-check"  aria-hidden="true"></kmp-icon>
                        <?= $userAttendance ? __('Update Attendance') : __('Attend This Gathering') ?>
                    </button>
                <?php elseif (!$isAuthenticated): ?>
                    <!-- Non-authenticated user - show login link -->
                    <a href="<?= $this->Url->build(['controller' => 'Gatherings', 'action' => 'view', $gathering->public_id]) ?>"
                        class="btn-medieval btn-medieval-cta">
                        <kmp-icon name="login"  aria-hidden="true"></kmp-icon>
                        <?= __('Login to AMP to RSVP') ?>
                    </a>
                <?php endif; ?>

                <?php if ($gathering->latitude !== null && $gathering->longitude !== null): ?>
                    <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $gathering->latitude ?>,<?= $gathering->longitude ?>"
                        target="_blank" class="btn-medieval btn-medieval-outline">
                        <kmp-icon name="sign-right"  aria-hidden="true"></kmp-icon>
                        <?= __('Get Directions') ?>
                    </a>
                <?php elseif ($gathering->location): ?>
                    <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($gathering->location) ?>"
                        target="_blank" class="btn-medieval btn-medieval-outline">
                        <kmp-icon name="map"  aria-hidden="true"></kmp-icon>
                        <?= __('Find Location') ?>
                    </a>
                <?php endif; ?>
            </div>

            <div class="cta-ornament-bottom">⚔ ⚔ ⚔</div>
        </div>
    </div>
<?php endif; ?>

<?php
// Include Attend Gathering Modal for authenticated users
// Show modal if user can create new attendance OR edit existing attendance
if ($isAuthenticated && ($canCreateAttendance || $canEditAttendance)):
    echo $this->element('gatherings/attendGatheringModal', [
        'gathering' => $gathering,
        'userAttendance' => $userAttendance ?? null,
        'user' => $user,
        'modalId' => 'attendGatheringModal',
        'progressOfficers' => $progressOfficers ?? [],
    ]);
endif;
?>
</div>
