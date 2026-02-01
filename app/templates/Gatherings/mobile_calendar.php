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
$this->set('mobileBackUrl', $authCardUrl);
$this->set('mobileHeaderColor', '#198754'); // Green for events

$rsvpUrl = $this->Url->build(['controller' => 'GatheringAttendances', 'action' => 'mobileRsvp']);
$unrsvpUrl = $this->Url->build(['controller' => 'GatheringAttendances', 'action' => 'mobileUnrsvp']);
$updateRsvpUrl = $this->Url->build(['controller' => 'GatheringAttendances', 'action' => 'mobileUpdateRsvp']);
?>

<div class="mobile-events-container" 
     data-controller="mobile-calendar"
     data-mobile-calendar-year-value="<?= $defaultYear ?>"
     data-mobile-calendar-month-value="<?= $defaultMonth ?>"
     data-mobile-calendar-data-url-value="<?= $this->Url->build(['action' => 'mobileCalendarData']) ?>"
     data-mobile-calendar-rsvp-url-value="<?= h($rsvpUrl) ?>"
     data-mobile-calendar-unrsvp-url-value="<?= h($unrsvpUrl) ?>"
     data-mobile-calendar-update-rsvp-url-value="<?= h($updateRsvpUrl) ?>"
     role="application"
     aria-label="Events list">
    
    <!-- Search and Filter Header -->
    <div class="mobile-events-search mx-3 mt-3 mb-2">
        <div class="input-group">
            <span class="input-group-text bg-white border-end-0">
                <i class="bi bi-search text-muted"></i>
            </span>
            <input type="text" 
                   class="form-control border-start-0" 
                   placeholder="Search events..."
                   data-mobile-calendar-target="searchInput"
                   data-action="input->mobile-calendar#handleSearch">
            <button type="button" 
                    class="btn btn-outline-secondary"
                    data-action="click->mobile-calendar#toggleFilters"
                    data-mobile-calendar-target="filterToggle"
                    aria-label="Toggle filters">
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
                    <select class="form-select form-select-sm" 
                            data-mobile-calendar-target="typeFilter"
                            data-action="change->mobile-calendar#applyFilters">
                        <option value="">All Types</option>
                    </select>
                </div>
                
                <!-- Branch Filter -->
                <div class="mb-2">
                    <label class="form-label small mb-1">Branch</label>
                    <select class="form-select form-select-sm" 
                            data-mobile-calendar-target="branchFilter"
                            data-action="change->mobile-calendar#applyFilters">
                        <option value="">All Branches</option>
                    </select>
                </div>
                
                <!-- My RSVPs Only -->
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" 
                           id="myRsvpsOnly"
                           data-mobile-calendar-target="rsvpFilter"
                           data-action="change->mobile-calendar#applyFilters">
                    <label class="form-check-label small" for="myRsvpsOnly">
                        My RSVPs only
                    </label>
                </div>
                
                <div class="mt-2 text-end">
                    <button type="button" 
                            class="btn btn-sm btn-link text-decoration-none"
                            data-action="click->mobile-calendar#clearFilters">
                        Clear filters
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Month Quick Navigation -->
    <div class="mobile-events-nav mx-3 mb-3">
        <div class="d-flex align-items-center justify-content-between">
            <button type="button" 
                    class="btn btn-sm btn-outline-secondary"
                    data-action="click->mobile-calendar#previousMonth"
                    aria-label="Previous month">
                <i class="bi bi-chevron-left"></i>
            </button>
            
            <div class="d-flex align-items-center gap-2">
                <select class="form-select form-select-sm" 
                        data-mobile-calendar-target="monthSelect"
                        data-action="change->mobile-calendar#jumpToMonth"
                        style="width: auto;">
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
                
                <select class="form-select form-select-sm" 
                        data-mobile-calendar-target="yearSelect"
                        data-action="change->mobile-calendar#jumpToMonth"
                        style="width: auto;">
                </select>
            </div>
            
            <button type="button" 
                    class="btn btn-sm btn-outline-secondary"
                    data-action="click->mobile-calendar#nextMonth"
                    aria-label="Next month">
                <i class="bi bi-chevron-right"></i>
            </button>
        </div>
        
        <div class="text-center mt-2">
            <button type="button" 
                    class="btn btn-link btn-sm text-decoration-none p-0"
                    data-action="click->mobile-calendar#goToToday">
                <i class="bi bi-calendar-event me-1"></i>Jump to Today
            </button>
        </div>
    </div>
    
    <!-- Loading State -->
    <div class="mobile-events-loading text-center py-5" 
         data-mobile-calendar-target="loading"
         role="status">
        <div class="spinner-border text-success"></div>
        <p class="mt-2 text-muted">Loading events...</p>
    </div>
    
    <!-- Error State -->
    <div class="mobile-events-error mx-3" data-mobile-calendar-target="error" hidden>
        <div class="alert alert-warning d-flex align-items-center">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <span data-mobile-calendar-target="errorMessage">Unable to load events</span>
        </div>
        <button type="button" 
                class="btn btn-outline-warning w-100"
                data-action="click->mobile-calendar#reload">
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
/* Mobile Events Styles */
.mobile-events-container {
    padding-bottom: 100px;
}

/* Search Bar */
.mobile-events-search .input-group {
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    border-radius: 8px;
    overflow: hidden;
}

.mobile-events-search .form-control:focus {
    box-shadow: none;
}

/* Filter Panel */
.mobile-events-filters .card {
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

/* Month Navigation */
.mobile-events-nav {
    background: var(--bs-light);
    border-radius: 8px;
    padding: 12px;
}

/* Event Cards */
.mobile-event-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    margin-bottom: 12px;
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
}

.mobile-event-card:active {
    transform: scale(0.98);
}

.mobile-event-card.cancelled {
    opacity: 0.6;
}

.mobile-event-card.attending {
    border-left: 4px solid var(--bs-success);
}

.mobile-event-header {
    padding: 12px 16px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
}

.mobile-event-info {
    flex: 1;
    min-width: 0;
}

.mobile-event-name {
    font-weight: 600;
    font-size: 16px;
    margin-bottom: 4px;
    color: var(--bs-dark);
}

.mobile-event-name.cancelled {
    text-decoration: line-through;
    color: var(--bs-secondary);
}

.mobile-event-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    font-size: 13px;
    color: var(--bs-secondary);
}

.mobile-event-meta i {
    width: 16px;
}

.mobile-event-activities {
    display: flex;
    align-items: flex-start;
    gap: 6px;
    font-size: 12px;
    color: var(--bs-primary);
    margin-top: 4px;
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
    color: var(--bs-primary);
    transition: color 0.2s;
}

.mobile-activity-link:hover {
    color: var(--bs-primary-emphasis);
    text-decoration: underline !important;
}

.mobile-event-actions {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
}

.mobile-event-type-badge {
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 12px;
    white-space: nowrap;
}

.mobile-event-rsvp-btn {
    font-size: 12px;
    padding: 4px 12px;
    border-radius: 16px;
}

/* Week Separator */
.mobile-week-header {
    background: var(--bs-success);
    color: white;
    padding: 8px 16px;
    border-radius: 8px;
    margin-bottom: 12px;
    font-weight: 600;
    font-size: 14px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.mobile-week-count {
    background: rgba(255,255,255,0.2);
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 12px;
}

/* Day Header */
.mobile-day-header {
    color: var(--bs-success);
    font-weight: 600;
    font-size: 13px;
    padding: 8px 0 4px;
    border-bottom: 1px solid var(--bs-border-color);
    margin-bottom: 8px;
}

.mobile-day-header .day-name {
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.mobile-day-header.today {
    color: var(--bs-primary);
}

.mobile-day-header.today::after {
    content: " • Today";
    font-weight: normal;
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
    background: linear-gradient(to bottom, var(--bs-success-bg-subtle), transparent);
}

.pull-spinner {
    font-size: 24px;
    color: var(--bs-success);
}

.pull-spinner .spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
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
    background: rgba(0,0,0,0.5);
}

.rsvp-sheet-panel {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: white;
    border-radius: 16px 16px 0 0;
    max-height: 80vh;
    overflow-y: auto;
    transform: translateY(100%);
    animation: slideUp 0.3s ease forwards;
}

@keyframes slideUp {
    to { transform: translateY(0); }
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
    height: 4px;
    background: var(--bs-border-color);
    border-radius: 2px;
}

.rsvp-sheet-body {
    padding: 0 20px 20px;
}

.rsvp-sheet-title {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 4px;
}

.rsvp-sheet-date {
    color: var(--bs-secondary);
    font-size: 14px;
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
    background: var(--bs-danger);
    border-radius: 50%;
}
</style>
