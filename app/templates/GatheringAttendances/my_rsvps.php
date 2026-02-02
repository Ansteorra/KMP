<?php

/**
 * My RSVPs Mobile View Template
 * 
 * Shows the current user's RSVPs in a mobile-optimized tabbed interface.
 * Upcoming events can be edited; past events are read-only and require online access.
 * 
 * @var \App\View\AppView $this
 * @var \Cake\ORM\ResultSet $upcomingAttendances
 * @var \Cake\ORM\ResultSet $pastAttendances
 * @var string $authCardUrl
 */

// Set mobile layout variables
$this->set('mobileTitle', 'My RSVPs');
$this->set('mobileSection', 'rsvps');
$this->set('mobileIcon', 'bi-calendar-check');
$this->set('mobileBackUrl', $authCardUrl);

$currentUser = $this->request->getAttribute('identity');
$userTimezone = \App\KMP\TimezoneHelper::getUserTimezone($currentUser);

$upcomingCount = $upcomingAttendances->count();
$pastCount = $pastAttendances->count();
?>

<div class="my-rsvps-container mx-3 mt-3" data-controller="my-rsvps" data-section="rsvps">
    <!-- Tabs Navigation -->
    <ul class="nav nav-tabs rsvp-tabs mb-3" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="upcoming-tab" data-bs-toggle="tab" data-bs-target="#upcoming-pane"
                type="button" role="tab" aria-controls="upcoming-pane" aria-selected="true">
                <i class="bi bi-calendar-event me-1"></i>Upcoming
                <span class="badge bg-primary ms-1" data-my-rsvps-target="upcomingCount"
                    <?= $upcomingCount == 0 ? 'hidden' : '' ?>><?= $upcomingCount ?></span>
            </button>
        </li>
        <li class="nav-item online-only" role="presentation">
            <button class="nav-link" id="past-tab" data-bs-toggle="tab" data-bs-target="#past-pane" type="button"
                role="tab" aria-controls="past-pane" aria-selected="false">
                <i class="bi bi-clock-history me-1"></i>Past
            </button>
        </li>
    </ul>

    <!-- Tabs Content -->
    <div class="tab-content">
        <!-- Upcoming RSVPs Tab -->
        <div class="tab-pane fade show active" id="upcoming-pane" role="tabpanel" aria-labelledby="upcoming-tab"
            tabindex="0">
            <?php if ($upcomingAttendances->isEmpty()): ?>
            <!-- Empty State -->
            <div class="card empty-state-card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-calendar-check d-block fs-1 mb-3" style="color: var(--section-rsvps);"></i>
                    <h3 class="h5 mb-2">No Upcoming RSVPs</h3>
                    <p class="text-muted mb-4">
                        You haven't RSVPed to any upcoming gatherings yet.
                    </p>
                    <a href="<?= $this->Url->build(['controller' => 'Gatherings', 'action' => 'mobileCalendar']) ?>"
                        class="btn btn-primary online-only-btn">
                        <i class="bi bi-calendar me-2"></i>Browse Calendar
                    </a>
                </div>
            </div>
            <?php else: ?>
            <!-- Upcoming RSVP List -->
            <div class="rsvp-list" data-my-rsvps-target="upcomingList">
                <?php foreach ($upcomingAttendances as $attendance):
                        $gathering = $attendance->gathering;
                        $startLocal = \App\KMP\TimezoneHelper::toUserTimezone($gathering->start_date, $userTimezone);
                        $endLocal = \App\KMP\TimezoneHelper::toUserTimezone($gathering->end_date, $userTimezone);
                        $typeColor = $gathering->gathering_type ? $gathering->gathering_type->color : '#6c757d';
                    ?>
                <div class="mobile-event-card <?= $gathering->cancelled_at ? 'cancelled' : 'attending' ?>" data-end-date="<?= $endLocal->format('Y-m-d') ?>">
                    <?php if ($gathering->cancelled_at): ?>
                    <div class="mobile-event-cancelled-banner">
                        <i class="bi bi-x-circle-fill me-2"></i>CANCELLED
                    </div>
                    <?php endif; ?>
                    <div class="mobile-event-header">
                        <div class="mobile-event-info">
                            <?php if ($gathering->gathering_type): ?>
                            <span class="mobile-event-type-badge mb-1" style="background-color: <?= h($typeColor) ?>; color: white;">
                                <?= h($gathering->gathering_type->name) ?>
                            </span>
                            <?php endif; ?>
                            <a href="<?= $this->Url->build(['controller' => 'Gatherings', 'action' => 'view', $gathering->public_id]) ?>"
                                class="mobile-event-name <?= $gathering->cancelled_at ? 'cancelled' : '' ?> text-decoration-none">
                                <?= h($gathering->name) ?>
                            </a>
                            <div class="mobile-event-meta">
                                <span><i class="bi bi-calendar3"></i> <?= $startLocal->format('D, M j') ?></span>
                                <span><i class="bi bi-clock"></i> <?= $startLocal->format('g:i A') ?></span>
                            </div>
                            <div class="mobile-event-meta">
                                <?php if ($gathering->branch): ?>
                                <span><i class="bi bi-building"></i> <?= h($gathering->branch->name) ?></span>
                                <?php endif; ?>
                                <?php if ($gathering->location): ?>
                                <span><i class="bi bi-geo-alt"></i> <?= h($gathering->location) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($attendance->is_shared): ?>
                            <div class="mobile-event-meta">
                                <span><i class="bi bi-share"></i> Shared: <?= h($attendance->sharing_description) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!$gathering->cancelled_at): ?>
                            <div class="mobile-event-actions-row mt-2" data-my-rsvps-target="actionButtons">
                                <?php if ($gathering->public_page_enabled): ?>
                                <a href="<?= $this->Url->build(['controller' => 'Gatherings', 'action' => 'publicLanding', $gathering->public_id, '?' => ['from' => 'mobile']]) ?>"
                                    class="btn btn-sm btn-outline-secondary online-only-btn">
                                    <i class="bi bi-info-circle me-1"></i>Details
                                </a>
                                <?php endif; ?>
                                <button type="button" class="btn btn-sm btn-outline-success online-only-btn"
                                    data-action="click->my-rsvps#editRsvp" data-gathering-id="<?= h($gathering->id) ?>"
                                    data-attendance-id="<?= h($attendance->id) ?>">
                                    <i class="bi bi-pencil me-1"></i>Edit
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="mobile-event-actions">
                            <?php if (!$gathering->cancelled_at): ?>
                            <i class="bi bi-check-circle-fill text-success"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Past RSVPs Tab (Online Only) -->
        <div class="tab-pane fade online-only" id="past-pane" role="tabpanel" aria-labelledby="past-tab" tabindex="0">
            <!-- Past RSVP List -->
            <div class="rsvp-list" data-my-rsvps-target="pastList">
                <?php if ($pastAttendances->isEmpty()): ?>
                <!-- Empty State (will be replaced if events are moved from upcoming) -->
                <div class="card empty-state-card" data-my-rsvps-target="pastEmptyState">
                    <div class="card-body text-center py-4">
                        <i class="bi bi-clock-history d-block fs-1 mb-3 text-muted"></i>
                        <h3 class="h5 mb-2">No Past RSVPs</h3>
                        <p class="text-muted mb-0">
                            No past gatherings in the last 90 days.
                        </p>
                    </div>
                </div>
                <?php else: ?>
                <?php foreach ($pastAttendances as $attendance):
                        $gathering = $attendance->gathering;
                        $startLocal = \App\KMP\TimezoneHelper::toUserTimezone($gathering->start_date, $userTimezone);
                        $endLocal = \App\KMP\TimezoneHelper::toUserTimezone($gathering->end_date, $userTimezone);
                        $typeColor = $gathering->gathering_type ? $gathering->gathering_type->color : '#6c757d';
                    ?>
                <div class="mobile-event-card past <?= $gathering->cancelled_at ? 'cancelled' : '' ?>">
                    <?php if ($gathering->cancelled_at): ?>
                    <div class="mobile-event-cancelled-banner">
                        <i class="bi bi-x-circle-fill me-2"></i>CANCELLED
                    </div>
                    <?php endif; ?>
                    <div class="mobile-event-header">
                        <div class="mobile-event-info">
                            <?php if ($gathering->gathering_type): ?>
                            <span class="mobile-event-type-badge mb-1" style="background-color: <?= h($typeColor) ?>; color: white;">
                                <?= h($gathering->gathering_type->name) ?>
                            </span>
                            <?php endif; ?>
                            <a href="<?= $this->Url->build(['controller' => 'Gatherings', 'action' => 'view', $gathering->public_id]) ?>"
                                class="mobile-event-name <?= $gathering->cancelled_at ? 'cancelled' : '' ?> text-decoration-none">
                                <?= h($gathering->name) ?>
                            </a>
                            <div class="mobile-event-meta">
                                <span><i class="bi bi-calendar3"></i> <?= $startLocal->format('D, M j, Y') ?></span>
                            </div>
                            <div class="mobile-event-meta">
                                <?php if ($gathering->branch): ?>
                                <span><i class="bi bi-building"></i> <?= h($gathering->branch->name) ?></span>
                                <?php endif; ?>
                                <?php if ($gathering->location): ?>
                                <span><i class="bi bi-geo-alt"></i> <?= h($gathering->location) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($attendance->is_shared): ?>
                            <div class="mobile-event-meta">
                                <span><i class="bi bi-share"></i> Shared: <?= h($attendance->sharing_description) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="mobile-event-actions">
                            <?php if (!$gathering->cancelled_at): ?>
                            <i class="bi bi-check-circle text-muted"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Link to Calendar -->
    <div class="text-center mt-4 mb-5">
        <a href="<?= $this->Url->build(['controller' => 'Gatherings', 'action' => 'mobileCalendar']) ?>"
            class="btn btn-outline-secondary online-only-btn">
            <i class="bi bi-calendar me-2"></i>View Calendar
        </a>
    </div>

    <!-- RSVP Modal -->
    <div class="modal fade" id="rsvpModal" tabindex="-1" aria-labelledby="rsvpModalLabel" aria-hidden="true"
        data-my-rsvps-target="modal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rsvpModalLabel">Edit RSVP</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" data-my-rsvps-target="modalBody">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.my-rsvps-container {
    padding-bottom: 80px;
}

/* Tabs Styling - Medieval Theme */
.rsvp-tabs {
    background: var(--mobile-card-bg, #fffef9);
    border-radius: 4px;
    padding: 4px;
    box-shadow: 0 2px 8px rgba(44, 24, 16, 0.06);
    border: 1px solid rgba(139, 105, 20, 0.1);
    display: flex;
    gap: 2px;
}

.rsvp-tabs .nav-item {
    flex: 1;
}

.rsvp-tabs .nav-link {
    border: none;
    border-radius: 4px;
    padding: 8px 10px;
    font-weight: 500;
    font-size: 14px;
    color: var(--mobile-text-secondary, #4a3728);
    background: transparent;
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    font-family: var(--font-display, 'Cinzel', serif);
}

.rsvp-tabs .nav-link:hover {
    color: var(--section-rsvps, #1e4976);
    background: rgba(30, 73, 118, 0.08);
}

.rsvp-tabs .nav-link.active {
    background: linear-gradient(180deg, var(--section-rsvps, #1e4976), color-mix(in srgb, var(--section-rsvps, #1e4976) 70%, black));
    color: var(--medieval-parchment, #f4efe4);
}

.rsvp-tabs .nav-link.active .badge {
    background: rgba(255, 255, 255, 0.25) !important;
    color: white;
}

.rsvp-tabs .nav-link .badge {
    font-size: 10px;
    padding: 2px 6px;
}

/* Event Cards - Match Mobile Calendar exactly */
.my-rsvps-container .mobile-event-card {
    background: var(--mobile-card-bg, #fffef9);
    border-radius: 4px;
    box-shadow: 0 2px 12px rgba(44, 24, 16, 0.08);
    margin-bottom: 10px;
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
    border: 1px solid rgba(139, 105, 20, 0.1);
    border-left: 5px solid var(--section-rsvps, #1e4976);
}

.my-rsvps-container .mobile-event-card:active {
    transform: scale(0.98);
}

.my-rsvps-container .mobile-event-card.attending {
    border-left-color: var(--mobile-success, #1e6f50);
}

.my-rsvps-container .mobile-event-card.past {
    border-left-color: var(--mobile-text-muted, #6b5c4f);
    opacity: 0.85;
}

.my-rsvps-container .mobile-event-card.cancelled {
    opacity: 0.8;
    border-left-color: var(--mobile-danger, #dc3545) !important;
}

/* Cancelled Event Banner */
.my-rsvps-container .mobile-event-cancelled-banner {
    background: linear-gradient(180deg, #dc3545, #a71d2a);
    color: white;
    padding: 8px 12px;
    font-weight: 700;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    font-family: var(--font-display, 'Cinzel', serif);
    display: flex;
    align-items: center;
    justify-content: center;
}

.my-rsvps-container .mobile-event-header {
    padding: 10px 12px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 10px;
}

.my-rsvps-container .mobile-event-info {
    flex: 1;
    min-width: 0;
}

.my-rsvps-container .mobile-event-name {
    font-weight: 600;
    font-size: 18px;
    margin-bottom: 3px;
    color: var(--mobile-text-primary, #2c1810);
    font-family: var(--font-display, 'Cinzel', serif);
    display: block;
}

.my-rsvps-container .mobile-event-name.cancelled {
    text-decoration: line-through;
    color: var(--mobile-text-muted, #6b5c4f);
}

.my-rsvps-container .mobile-event-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    font-size: 15px;
    color: var(--mobile-text-secondary, #4a3728);
    font-family: var(--font-body, 'Crimson Pro', Georgia, serif);
    margin-bottom: 2px;
}

.my-rsvps-container .mobile-event-meta i {
    width: 16px;
    color: var(--medieval-bronze, #8b6914);
}

.my-rsvps-container .mobile-event-actions {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 6px;
}

.my-rsvps-container .mobile-event-type-badge {
    font-size: 11px;
    padding: 3px 8px;
    border-radius: 4px;
    white-space: nowrap;
    font-weight: 600;
    font-family: var(--font-display, 'Cinzel', serif);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.my-rsvps-container .mobile-event-actions-row {
    display: flex;
    gap: 8px;
}

.my-rsvps-container .mobile-event-actions-row .btn {
    padding: 6px 10px;
    font-size: 13px;
}

/* Empty state styling */
.my-rsvps-container .empty-state-card {
    border-radius: 4px;
    border: 1px solid rgba(139, 105, 20, 0.1);
    box-shadow: 0 2px 8px rgba(44, 24, 16, 0.06);
    border-left: 5px solid var(--section-rsvps, #1e4976);
}

.my-rsvps-container .empty-state-card h3 {
    font-family: var(--font-display, 'Cinzel', serif);
}

/* Past tab empty state uses muted color */
#past-pane .empty-state-card {
    border-left-color: var(--mobile-text-muted, #6b5c4f);
}
</style>