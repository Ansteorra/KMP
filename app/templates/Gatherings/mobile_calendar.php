<?php

/**
 * Mobile Events View Template
 * 
 * Touch-optimized weekly event list for viewing gatherings on mobile devices.
 * Features filtering, quick month navigation, and RSVP functionality.
 * 
 * @var \App\View\AppView $this
 * @var int $defaultYear
 * @var int $defaultMonth
 * @var string $authCardUrl
 */

// Set mobile layout variables
$this->set('mobileTitle', 'Events');
$this->set('mobileSection', 'events');
$this->set('mobileIcon', 'bi-calendar-event');
$this->set('mobileBackUrl', $authCardUrl);

$rsvpUrl = $this->Url->build(['controller' => 'GatheringAttendances', 'action' => 'mobileRsvp']);
$unrsvpUrl = $this->Url->build(['controller' => 'GatheringAttendances', 'action' => 'mobileUnrsvp']);
$updateRsvpUrl = $this->Url->build(['controller' => 'GatheringAttendances', 'action' => 'mobileUpdateRsvp']);
?>

<div class="mobile-events-container" data-controller="mobile-calendar" data-section="events"
    data-mobile-calendar-year-value="<?= $defaultYear ?>" data-mobile-calendar-month-value="<?= $defaultMonth ?>"
    data-mobile-calendar-data-url-value="<?= $this->Url->build(['action' => 'mobileCalendarData']) ?>"
    data-mobile-calendar-rsvp-url-value="<?= h($rsvpUrl) ?>"
    data-mobile-calendar-unrsvp-url-value="<?= h($unrsvpUrl) ?>"
    data-mobile-calendar-update-rsvp-url-value="<?= h($updateRsvpUrl) ?>" role="application" aria-label="Events list">

    <!-- Month Quick Navigation -->
    <div class="mobile-events-nav mx-3 mb-3">
        <div class="d-flex align-items-center justify-content-between">
            <button type="button" class="btn btn-sm btn-outline-secondary"
                data-action="click->mobile-calendar#previousMonth" aria-label="Previous month">
                <i class="bi bi-chevron-left"></i>
            </button>

            <div class="d-flex align-items-center gap-2">
                <select class="form-select form-select-sm" data-mobile-calendar-target="monthSelect"
                    data-action="change->mobile-calendar#jumpToMonth" style="width: auto;">
                    <option value="1">January</option>
                    <option value="2">February</option>
                    <option value="3">March</option>
                    <option value="4">April</option>
                    <option value="5">May</option>
                    <option value="6">June</option>
                    <option value="7">July</option>
                    <option value="8">August</option>
                    <option value="9">September</option>
                    <option value="10">October</option>
                    <option value="11">November</option>
                    <option value="12">December</option>
                </select>

                <select class="form-select form-select-sm" data-mobile-calendar-target="yearSelect"
                    data-action="change->mobile-calendar#jumpToMonth" style="width: auto;">
                </select>
            </div>

            <button type="button" class="btn btn-sm btn-outline-secondary"
                data-action="click->mobile-calendar#nextMonth" aria-label="Next month">
                <i class="bi bi-chevron-right"></i>
            </button>
        </div>

        <div class="text-center mt-2">
            <button type="button" class="btn btn-link btn-sm text-decoration-none p-0"
                data-action="click->mobile-calendar#goToToday">
                <i class="bi bi-calendar-event me-1"></i>Jump to Today
            </button>
        </div>
    </div>

    <!-- Search and Filter Header -->
    <div class="mobile-events-search mx-3 mt-3 mb-2">
        <div class="input-group">
            <span class="input-group-text bg-white border-end-0">
                <i class="bi bi-search text-muted"></i>
            </span>
            <input type="text" class="form-control border-start-0" placeholder="Search events..."
                data-mobile-calendar-target="searchInput" data-action="input->mobile-calendar#handleSearch">
            <button type="button" class="btn btn-outline-secondary" data-action="click->mobile-calendar#toggleFilters"
                data-mobile-calendar-target="filterToggle" aria-label="Toggle filters">
                <i class="bi bi-funnel"></i>
            </button>
        </div>
    </div>

    <!-- Filter Panel (collapsible) -->
    <div class="mobile-events-filters mx-3 mb-2" data-mobile-calendar-target="filterPanel" hidden>
        <div class="card">
            <div class="card-body py-2">
                <!-- Event Type Filter -->
                <div class="mb-2">
                    <label class="form-label small mb-1">Event Type</label>
                    <select class="form-select form-select-sm" data-mobile-calendar-target="typeFilter"
                        data-action="change->mobile-calendar#applyFilters">
                        <option value="">All Types</option>
                    </select>
                </div>

                <!-- Activity Type Filter -->
                <div class="mb-2">
                    <label class="form-label small mb-1">Activity</label>
                    <select class="form-select form-select-sm" data-mobile-calendar-target="activityFilter"
                        data-action="change->mobile-calendar#applyFilters">
                        <option value="">All Activities</option>
                    </select>
                </div>

                <!-- Branch Filter -->
                <div class="mb-2">
                    <label class="form-label small mb-1">Branch</label>
                    <select class="form-select form-select-sm" data-mobile-calendar-target="branchFilter"
                        data-action="change->mobile-calendar#applyFilters">
                        <option value="">All Branches</option>
                    </select>
                </div>

                <!-- My RSVPs Only -->
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="myRsvpsOnly"
                        data-mobile-calendar-target="rsvpFilter" data-action="change->mobile-calendar#applyFilters">
                    <label class="form-check-label small" for="myRsvpsOnly">
                        My RSVPs only
                    </label>
                </div>

                <div class="mt-2 text-end">
                    <button type="button" class="btn btn-sm btn-link text-decoration-none"
                        data-action="click->mobile-calendar#clearFilters">
                        Clear filters
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending RSVPs Banner (shown when offline RSVPs are queued) -->
    <div class="mobile-pending-rsvps mx-3 mb-2" data-mobile-calendar-target="pendingBanner" hidden>
        <div class="alert alert-info d-flex align-items-center justify-content-between py-2 mb-0">
            <div>
                <i class="bi bi-cloud-arrow-up me-2"></i>
                <span data-mobile-calendar-target="pendingCount">0</span> RSVP(s) pending sync
            </div>
            <button type="button" class="btn btn-sm btn-info" data-action="click->mobile-calendar#syncPendingRsvps"
                data-mobile-calendar-target="syncBtn">
                <i class="bi bi-arrow-repeat"></i> Sync
            </button>
        </div>
    </div>

    <!-- Loading State -->
    <div class="mobile-events-loading text-center py-5" data-mobile-calendar-target="loading" role="status">
        <div class="spinner-border text-success"></div>
        <p class="mt-2 text-muted">Loading events...</p>
    </div>

    <!-- Error State -->
    <div class="mobile-events-error mx-3" data-mobile-calendar-target="error" hidden>
        <div class="alert alert-warning d-flex align-items-center">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <span data-mobile-calendar-target="errorMessage">Unable to load events</span>
        </div>
        <button type="button" class="btn btn-outline-warning w-100" data-action="click->mobile-calendar#reload">
            <i class="bi bi-arrow-clockwise me-1"></i> Retry
        </button>
    </div>

    <!-- Events List -->
    <div class="mobile-events-list mx-3" data-mobile-calendar-target="eventList" hidden>
        <!-- Events grouped by week will be rendered here -->
    </div>

    <!-- No Events Message -->
    <div class="mobile-events-empty mx-3" data-mobile-calendar-target="emptyState" hidden>
        <div class="alert alert-light text-center">
            <i class="bi bi-calendar-x text-muted d-block fs-1 mb-2"></i>
            <p class="mb-1">No events found</p>
            <small class="text-muted" data-mobile-calendar-target="emptyMessage">
                Try adjusting your filters or selecting a different month.
            </small>
        </div>
    </div>

    <!-- Results Count -->
    <div class="mobile-events-count mx-3 mb-2" data-mobile-calendar-target="resultsCount" hidden>
        <small class="text-muted"></small>
    </div>
</div>

<style>
/* Mobile Events Styles - Medieval Theme, Accessibility-Focused */
.mobile-events-container {
    padding-bottom: 80px;
    margin-top: 6px;
}

/* Search Bar - Tighter */
.mobile-events-search .input-group {
    background: var(--mobile-card-bg, #fffef9);
    box-shadow: var(--mobile-card-shadow, 0 4px 20px rgba(44, 24, 16, 0.12));
    border-radius: 4px;
    overflow: hidden;
    border: 1px solid rgba(139, 105, 20, 0.15);
}

.mobile-events-search .input-group-text {
    background: transparent;
    border: none;
    padding-left: 12px;
    color: var(--medieval-bronze, #8b6914);
}

.mobile-events-search .form-control {
    border: none !important;
    padding: 10px;
    font-size: 17px;
    font-family: var(--font-body, 'Crimson Pro', Georgia, serif);
}

.mobile-events-search .form-control:focus {
    box-shadow: none !important;
}

.mobile-events-search .btn-outline-secondary {
    border: none;
    border-left: 1px solid rgba(139, 105, 20, 0.15);
    border-radius: 0;
    padding: 10px 14px;
    color: var(--medieval-bronze, #8b6914);
}

.mobile-events-search .btn-outline-secondary:hover {
    background: rgba(139, 105, 20, 0.08);
    color: var(--medieval-ink, #2c1810);
}

/* Filter Panel - Tighter */
.mobile-events-filters .card {
    border-radius: 4px;
    box-shadow: var(--mobile-card-shadow, 0 4px 20px rgba(44, 24, 16, 0.12));
    border: 1px solid rgba(139, 105, 20, 0.15);
    border-left: 5px solid var(--section-events);
}

.mobile-events-filters .card-body {
    padding: 10px 12px !important;
}

.mobile-events-filters .form-label {
    font-size: 14px;
    margin-bottom: 2px;
}

.mobile-events-filters .form-select-sm {
    font-size: 16px;
    padding: 6px 10px;
}

/* Month Navigation - Tighter */
.mobile-events-nav {
    background: var(--mobile-card-bg, #fffef9);
    border-radius: 4px;
    padding: 10px 12px;
    box-shadow: var(--mobile-card-shadow, 0 4px 20px rgba(44, 24, 16, 0.12));
    border: 1px solid rgba(139, 105, 20, 0.15);
    border-left: 5px solid var(--section-events);
}

/* Navigation Prev/Next Buttons */
.mobile-events-nav .btn-outline-secondary {
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 4px;
    padding: 8px 14px;
    font-weight: 600;
    background: linear-gradient(180deg, var(--section-events, #1e6f50), color-mix(in srgb, var(--section-events, #1e6f50) 70%, black));
    color: var(--medieval-parchment, #f4efe4) !important;
    font-family: var(--font-display, 'Cinzel', serif);
    box-shadow: 
        inset 0 1px 0 rgba(255, 255, 255, 0.15),
        0 2px 4px rgba(0, 0, 0, 0.1);
}

.mobile-events-nav .btn-outline-secondary:hover {
    background: linear-gradient(180deg, color-mix(in srgb, var(--section-events, #1e6f50) 85%, black), color-mix(in srgb, var(--section-events, #1e6f50) 60%, black));
    border-color: rgba(0, 0, 0, 0.15);
}

.mobile-events-nav .btn-outline-secondary i {
    color: var(--medieval-parchment, #f4efe4) !important;
}

.mobile-events-nav .form-select-sm {
    font-size: 16px;
    padding: 6px 10px;
}

/* Jump to Today link */
.mobile-events-nav .btn-link {
    color: var(--section-events, #1e6f50);
    font-family: var(--font-display, 'Cinzel', serif);
    font-size: 15px;
    letter-spacing: 0.02em;
}

.mobile-events-nav .btn-link:hover {
    color: var(--medieval-bronze, #8b6914);
}

/* Event Cards - Tighter, larger text */
.mobile-event-card {
    background: var(--mobile-card-bg, #fffef9);
    border-radius: 4px;
    box-shadow: 0 2px 12px rgba(44, 24, 16, 0.08);
    margin-bottom: 10px;
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
    border: 1px solid rgba(139, 105, 20, 0.1);
    border-left: 5px solid var(--section-events);
}

.mobile-event-card:active {
    transform: scale(0.98);
}

.mobile-event-card.cancelled {
    opacity: 0.6;
}

.mobile-event-card.attending {
    border-left: 5px solid var(--mobile-success, #1e6f50);
}

.mobile-event-header {
    padding: 10px 12px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 10px;
}

.mobile-event-info {
    flex: 1;
    min-width: 0;
}

.mobile-event-name {
    font-weight: 600;
    font-size: 18px;
    margin-bottom: 3px;
    color: var(--mobile-text-primary, #2c1810);
    font-family: var(--font-display, 'Cinzel', serif);
}

.mobile-event-name.cancelled {
    text-decoration: line-through;
    color: var(--mobile-text-muted, #6b5c4f);
}

.mobile-event-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    font-size: 15px;
    color: var(--mobile-text-secondary, #4a3728);
    font-family: var(--font-body, 'Crimson Pro', Georgia, serif);
}

.mobile-event-meta i {
    width: 16px;
    color: var(--medieval-bronze, #8b6914);
}

.mobile-event-activities {
    display: flex;
    align-items: flex-start;
    gap: 6px;
    font-size: 14px;
    color: var(--section-events, #1e6f50);
    margin-top: 3px;
    flex-wrap: wrap;
}

.mobile-event-activities i {
    margin-top: 2px;
    flex-shrink: 0;
}

.mobile-event-activities .activities-list {
    flex: 1;
    line-height: 1.4;
}

.mobile-activity-link {
    color: var(--section-events, #1e6f50);
    transition: color 0.2s;
}

.mobile-activity-link:hover {
    color: var(--medieval-bronze, #8b6914);
    text-decoration: underline !important;
}

.mobile-event-actions {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 6px;
}

.mobile-event-type-badge {
    font-size: 11px;
    padding: 3px 8px;
    border-radius: 4px;
    white-space: nowrap;
    font-weight: 600;
    font-family: var(--font-display, 'Cinzel', serif);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.mobile-event-rsvp-btn {
    font-size: 14px;
    padding: 6px 12px;
    border-radius: 4px;
    font-weight: 600;
    font-family: var(--font-display, 'Cinzel', serif);
}

/* Week Separator - Larger text */
.mobile-week-header {
    background: linear-gradient(180deg, var(--section-events, #1e6f50), color-mix(in srgb, var(--section-events, #1e6f50) 70%, black));
    color: var(--medieval-parchment, #f4efe4);
    padding: 10px 12px;
    border-radius: 4px;
    margin-bottom: 10px;
    font-weight: 600;
    font-size: 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 
        0 3px 10px rgba(30, 111, 80, 0.25),
        inset 0 1px 0 rgba(255, 255, 255, 0.15);
    font-family: var(--font-display, 'Cinzel', serif);
    letter-spacing: 0.04em;
    text-transform: uppercase;
    border: 1px solid rgba(0, 0, 0, 0.1);
}

.mobile-week-count {
    background: rgba(255, 255, 255, 0.2);
    padding: 3px 10px;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 600;
    letter-spacing: 0.02em;
}

/* Day Header - Larger */
.mobile-day-header {
    color: var(--mobile-text-primary, #2c1810);
    font-weight: 600;
    font-size: 15px;
    padding: 8px 0 4px;
    border-bottom: 2px solid rgba(139, 105, 20, 0.2);
    margin-bottom: 8px;
    font-family: var(--font-display, 'Cinzel', serif);
    letter-spacing: 0.03em;
}

.mobile-day-header .day-name {
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

.mobile-day-header.today {
    color: var(--section-events, #1e6f50);
    border-bottom-color: var(--section-events, #1e6f50);
}

.mobile-day-header.today::after {
    content: " • Today";
    font-weight: normal;
    font-style: italic;
}

/* Pull to Refresh */
.pull-to-refresh-indicator {
    height: 0;
    opacity: 0;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    transition: height 0.2s, opacity 0.2s;
    background: linear-gradient(to bottom, rgba(30, 111, 80, 0.1), transparent);
}

.pull-spinner {
    font-size: 24px;
    color: var(--section-events, #1e6f50);
}

.pull-spinner .spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }

    to {
        transform: rotate(360deg);
    }
}

/* RSVP Bottom Sheet */
.mobile-rsvp-sheet {
    position: fixed;
    inset: 0;
    z-index: 1050;
    display: none;
}

.mobile-rsvp-sheet.open {
    display: block;
}

.rsvp-sheet-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(44, 24, 16, 0.7);
    backdrop-filter: blur(4px);
}

.rsvp-sheet-panel {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: var(--mobile-card-bg, #fffef9);
    border-radius: 4px 4px 0 0;
    max-height: 80vh;
    overflow-y: auto;
    transform: translateY(100%);
    animation: sheetSlideUp 0.3s cubic-bezier(0.4, 0, 0.2, 1) forwards;
    border-top: 2px solid var(--medieval-gold, #c9a227);
}

@keyframes sheetSlideUp {
    to {
        transform: translateY(0);
    }
}

.rsvp-sheet-handle {
    padding: 12px;
    text-align: center;
    cursor: pointer;
}

.rsvp-sheet-handle::before {
    content: '';
    display: inline-block;
    width: 40px;
    height: 5px;
    background: rgba(139, 105, 20, 0.3);
    border-radius: 3px;
}

.rsvp-sheet-body {
    padding: 0 20px 20px;
}

.rsvp-sheet-title {
    font-size: 22px;
    font-weight: 700;
    margin-bottom: 4px;
    color: var(--mobile-text-primary, #2c1810);
    font-family: var(--font-display, 'Cinzel', serif);
}

.rsvp-sheet-date {
    color: var(--mobile-text-secondary, #4a3728);
    font-size: 16px;
    font-family: var(--font-body, 'Crimson Pro', Georgia, serif);
}

/* Active filter indicator */
.filter-active {
    position: relative;
}

.filter-active::after {
    content: '';
    position: absolute;
    top: 4px;
    right: 4px;
    width: 8px;
    height: 8px;
    background: var(--mobile-danger, #8b2252);
    border-radius: 50%;
}

/* Results count and empty state - larger text */
.mobile-events-count small {
    font-size: 15px;
}

.mobile-events-empty .alert {
    font-size: 17px;
}

.mobile-events-empty .alert i {
    font-size: 2.5rem;
}
</style>