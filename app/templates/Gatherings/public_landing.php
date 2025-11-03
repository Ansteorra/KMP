<?php

/**
 * Public Landing Page for Gathering
 * 
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 * @var array $scheduleByDate
 * @var int $durationDays
 */

use Cake\I18n\Date;

// Set page title
$this->assign('title', h($gathering->name));

// Check if event is in the past
$isPast = $gathering->end_date < Date::now();
$isOngoing = $gathering->start_date <= Date::now() && $gathering->end_date >= Date::now();
?>

<!-- Hero Section -->
<div class="hero fade-in">
    <div class="hero-content">
        <div class="event-badge">
            <i class="bi bi-calendar-event"></i>
            <?= h($gathering->gathering_type->name) ?>
        </div>

        <h1><?= h($gathering->name) ?></h1>

        <div class="hero-meta">
            <div class="hero-meta-item">
                <i class="bi bi-calendar3"></i>
                <div>
                    <?php if ($gathering->is_multi_day): ?>
                    <div><?= $gathering->start_date->format('M d') ?> - <?= $gathering->end_date->format('M d, Y') ?>
                    </div>
                    <small style="opacity: 0.9;"><?= $durationDays ?> days</small>
                    <?php else: ?>
                    <?= $gathering->start_date->format('F d, Y') ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($gathering->location): ?>
            <div class="hero-meta-item">
                <i class="bi bi-geo-alt"></i>
                <div><?= h($gathering->location) ?></div>
            </div>
            <?php endif; ?>

            <div class="hero-meta-item">
                <i class="bi bi-building"></i>
                <div><?= h($gathering->branch->name) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Info Section -->
<div class="container">
    <div class="quick-info fade-in" style="animation-delay: 0.1s;">
        <?php
        // Separate stewards from other staff (only those marked to show on public page)
        $stewards = [];
        $otherStaff = [];
        if (!empty($gathering->gathering_staff)) {
            foreach ($gathering->gathering_staff as $staff) {
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
        <div class="info-card">
            <div class="info-icon">
                <i class="bi bi-person-badge"></i>
            </div>
            <div class="info-content">
                <h3><?= count($stewards) > 1 ? __('Event Stewards') : __('Event Steward') ?></h3>
                <?php foreach ($stewards as $steward): ?>
                <p>
                    <strong><?= h($steward->display_name) ?></strong>
                    <?php if (!empty($steward->email)): ?>
                    <br><small><i class="bi bi-envelope"></i> <a href="#" class="email-link"
                            data-email="<?= base64_encode($steward->email) ?>"><?= __('Email') ?></a></small>
                    <?php endif; ?>
                    <?php if (!empty($steward->phone)): ?>
                    <br><small><i class="bi bi-telephone"></i> <a
                            href="tel:<?= h($steward->phone) ?>"><?= h($steward->phone) ?></a></small>
                    <?php endif; ?>
                    <?php if (!empty($steward->contact_notes)): ?>
                    <br><small><i class="bi bi-info-circle"></i> <?= h($steward->contact_notes) ?></small>
                    <?php endif; ?>
                </p>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php
        // Display other staff if any
        if (!empty($otherStaff)):
        ?>
        <div class="info-card">
            <div class="info-icon">
                <i class="bi bi-people"></i>
            </div>
            <div class="info-content">
                <h3><?= __('Event Staff') ?></h3>
                <?php foreach ($otherStaff as $staff): ?>
                <p>
                    <strong><?= h($staff->role) ?>:</strong> <?= h($staff->display_name) ?>
                    <?php if (!empty($staff->email)): ?>
                    <br><small><i class="bi bi-envelope"></i> <a href="#" class="email-link"
                            data-email="<?= base64_encode($staff->email) ?>"><?= __('Email') ?></a></small>
                    <?php endif; ?>
                    <?php if (!empty($staff->phone)): ?>
                    <br><small><i class="bi bi-telephone"></i> <a
                            href="tel:<?= h($staff->phone) ?>"><?= h($staff->phone) ?></a></small>
                    <?php endif; ?>
                    <?php if (!empty($staff->contact_notes)): ?>
                    <br><small><i class="bi bi-info-circle"></i> <?= h($staff->contact_notes) ?></small>
                    <?php endif; ?>
                </p>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php
        // Fallback: If no staff configured, show creator
        if (!$hasStaff && $gathering->creator):
        ?>
        <div class="info-card">
            <div class="info-icon">
                <i class="bi bi-person-badge"></i>
            </div>
            <div class="info-content">
                <h3><?= __('Gathering Creator') ?></h3>
                <p><?= h($gathering->creator->sca_name) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($isOngoing): ?>
        <div class="info-card" style="border-left: 4px solid var(--color-success);">
            <div class="info-icon" style="background: linear-gradient(135deg, var(--color-success), #059669);">
                <i class="bi bi-broadcast"></i>
            </div>
            <div class="info-content">
                <h3>Event Status</h3>
                <p style="color: var(--color-success);">Happening Now!</p>
            </div>
        </div>
        <?php elseif ($isPast): ?>
        <div class="info-card">
            <div class="info-icon"
                style="background: linear-gradient(135deg, var(--color-gray-500), var(--color-gray-600));">
                <i class="bi bi-check-circle"></i>
            </div>
            <div class="info-content">
                <h3>Event Status</h3>
                <p>Event Completed</p>
            </div>
        </div>
        <?php else: ?>
        <?php
            $daysUntil = \Cake\I18n\Date::now()->diffInDays($gathering->start_date, false);
            if ($daysUntil >= 0):
            ?>
        <div class="info-card">
            <div class="info-icon">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div class="info-content">
                <h3>Starts In</h3>
                <p>
                    <?php if ($daysUntil == 0): ?>
                    Today!
                    <?php elseif ($daysUntil == 1): ?>
                    Tomorrow
                    <?php else: ?>
                    <?= $daysUntil ?> days
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <?php if (!empty($gathering->gathering_activities)): ?>
        <div class="info-card">
            <div class="info-icon">
                <i class="bi bi-list-check"></i>
            </div>
            <div class="info-content">
                <h3>Activities</h3>
                <p><?= count($gathering->gathering_activities) ?> Planned</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Description Section -->
<?php if ($gathering->description): ?>
<section class="section">
    <div class="container">
        <div class="section-header fade-in">
            <h2 class="section-title">About This Event</h2>
        </div>
        <div class="description-content fade-in" style="animation-delay: 0.1s;">
            <?= $this->Markdown->toHtml($gathering->description) ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Schedule Section -->
<?php if (!empty($scheduleByDate)): ?>
<section class="section" style="background: var(--color-white);">
    <div class="container">
        <div class="section-header fade-in">
            <h2 class="section-title">Event Schedule</h2>
            <p class="section-subtitle">Check out what's happening and when</p>
        </div>

        <?php foreach ($scheduleByDate as $date => $activities): ?>
        <div class="schedule-day fade-in"
            style="animation-delay: <?= array_search($date, array_keys($scheduleByDate)) * 0.1 ?>s;">
            <div class="schedule-day-header">
                <?php
                        $dateObj = \Cake\I18n\DateTime::parse($date);
                        echo $dateObj->format('l, F j, Y');
                        ?>
            </div>

            <div class="schedule-timeline">
                <?php foreach ($activities as $activity): ?>
                <div class="schedule-item">
                    <div class="schedule-time">
                        <?= $activity->start_datetime->format('g:i A') ?>
                        <?php if ($activity->end_datetime): ?>
                        - <?= $activity->end_datetime->format('g:i A') ?>
                        <?php endif; ?>
                    </div>
                    <div class="schedule-title">
                        <?= h($activity->display_title) ?>
                    </div>
                    <?php if ($activity->display_description): ?>
                    <div class="schedule-description">
                        <?= $this->Markdown->toHtml($activity->display_description) ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($activity->gathering_activity): ?>
                    <div class="schedule-activity-tag">
                        <i class="bi bi-tag-fill"></i> <?= h($activity->gathering_activity->name) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php elseif (!empty($gathering->gathering_activities)): ?>
<!-- Activities Section (shown when no schedule exists) -->
<section class="section" style="background: var(--color-white);">
    <div class="container">
        <div class="section-header fade-in">
            <h2 class="section-title">Planned Activities</h2>
            <p class="section-subtitle">Discover what you can participate in</p>
        </div>

        <div class="activities-grid">
            <?php foreach ($gathering->gathering_activities as $i => $activity): ?>
            <div class="activity-card fade-in" style="animation-delay: <?= $i * 0.05 ?>s;">
                <div class="activity-name">
                    <i class="bi bi-check-circle-fill"
                        style="color: var(--color-success); margin-right: var(--space-xs);"></i>
                    <?= h($activity->name) ?>
                </div>
                <?php
                        // Show custom description if available, otherwise show default description
                        $displayDescription = !empty($activity->_joinData->custom_description)
                            ? $activity->_joinData->custom_description
                            : $activity->description;
                        if ($displayDescription):
                        ?>
                <div class="activity-description">
                    <?= $displayDescription ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Location Section -->
<?php if ($gathering->location): ?>
<section class="section" style="background: var(--color-white);">
    <div class="container">
        <div class="section-header fade-in">
            <h2 class="section-title">Location & Directions</h2>
            <p class="section-subtitle">Find your way to the event</p>
        </div>

        <div class="location-address fade-in" style="animation-delay: 0.1s;">
            <i class="bi bi-geo-alt-fill"></i>
            <p><?= nl2br(h($gathering->location)) ?></p>

            <?php if ($gathering->latitude && $gathering->longitude): ?>
            <?php
                    // Use precise coordinates for navigation
                    $mapQuery = $gathering->latitude . ',' . $gathering->longitude;
                    $mapQueryEncoded = urlencode($mapQuery);
                    $locationEncoded = urlencode($gathering->location);
                    ?>

            <!-- Navigation Buttons -->
            <div
                style="margin-top: var(--space-xl); display: flex; gap: var(--space-md); flex-wrap: wrap; justify-content: center;">
                <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $mapQuery ?>" target="_blank"
                    class="btn btn-primary" style="font-size: 1.125rem;">
                    <i class="bi bi-signpost-2-fill"></i>
                    Get Directions
                </a>

                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline" data-bs-toggle="dropdown" aria-expanded="false"
                        style="background: rgba(255, 255, 255, 0.1); border: 2px solid var(--color-gray-400); color: var(--color-gray-800);">
                        <i class="bi bi-box-arrow-up-right"></i> Open In...
                    </button>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item"
                                href="https://www.google.com/maps/search/?api=1&query=<?= $mapQuery ?>" target="_blank">
                                <i class="bi bi-google text-primary"></i> Google Maps
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item"
                                href="https://maps.apple.com/?ll=<?= $mapQuery ?>&q=<?= $locationEncoded ?>"
                                target="_blank">
                                <i class="bi bi-apple text-dark"></i> Apple Maps
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="https://www.waze.com/ul?ll=<?= $mapQuery ?>&navigate=yes"
                                target="_blank">
                                <i class="bi bi-geo-alt-fill text-info"></i> Waze
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            <?php else: ?>
            <!-- Fallback for locations without coordinates -->
            <div style="margin-top: var(--space-lg);">
                <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($gathering->location) ?>"
                    target="_blank" class="btn btn-primary">
                    <i class="bi bi-map"></i>
                    Search in Google Maps
                </a>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($gathering->latitude && $gathering->longitude): ?>
        <!-- Google Maps Embed -->
        <div class="fade-in" style="animation-delay: 0.2s; margin-top: var(--space-lg);">
            <iframe width="100%" height="450"
                style="border:0; border-radius: var(--radius-xl); box-shadow: var(--shadow-lg);" loading="lazy"
                allowfullscreen referrerpolicy="no-referrer-when-downgrade"
                src="https://www.google.com/maps/embed/v1/place?key=<?= h($this->KMP->getAppSetting('GoogleMaps.ApiKey', '')) ?>&q=<?= urlencode($gathering->location) ?>&center=<?= h($gathering->latitude) ?>,<?= h($gathering->longitude) ?>&zoom=15">
            </iframe>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- CTA Section -->
<?php if (!$isPast): ?>
<section class="section">
    <div class="container">
        <div class="cta-section fade-in">
            <h2>Ready to Join Us?</h2>
            <p>
                <?php if ($isOngoing): ?>
                This event is happening now! Come join the festivities.
                <?php else: ?>
                Mark your calendar and prepare for an amazing experience!
                <?php endif; ?>
            </p>
            <div style="display: flex; gap: var(--space-md); justify-content: center; flex-wrap: wrap;">
                <a href="<?= $this->Url->build(['controller' => 'Gatherings', 'action' => 'view', $gathering->public_id]) ?>"
                    class="btn btn-primary">
                    <i class="bi bi-box-arrow-in-right"></i>
                    Login to AMP to RSVP if you want to.
                </a>
                <?php if ($gathering->latitude && $gathering->longitude): ?>
                <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $gathering->latitude ?>,<?= $gathering->longitude ?>"
                    target="_blank" class="btn btn-outline">
                    <i class="bi bi-signpost-2-fill"></i>
                    Get Directions
                </a>
                <?php elseif ($gathering->location): ?>
                <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($gathering->location) ?>"
                    target="_blank" class="btn btn-outline">
                    <i class="bi bi-map"></i>
                    Find Location
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <p>
            Hosted by <?= h($gathering->branch->name) ?>
            <?php if ($gathering->creator): ?>
            • Event Steward: <?= h($gathering->creator->sca_name) ?>
            <?php endif; ?>
        </p>
        <p style="margin-top: var(--space-md); font-size: 0.75rem; opacity: 0.7;">
            © <?= date('Y') ?> Kingdom Management Portal
        </p>
    </div>
</footer>

<!-- Email obfuscation script -->
<script>
(function() {
    // Decode and activate email links to prevent bot scraping
    document.addEventListener('DOMContentLoaded', function() {
        const emailLinks = document.querySelectorAll('.email-link');
        emailLinks.forEach(function(link) {
            const encodedEmail = link.getAttribute('data-email');
            if (encodedEmail) {
                // Decode the base64 encoded email
                const email = atob(encodedEmail);
                // Set the mailto href
                link.href = 'mailto:' + email;
                // Display the email address
                link.textContent = email;
                // Remove the data attribute to further obfuscate
                link.removeAttribute('data-email');
            }
        });
    });
})();
</script>