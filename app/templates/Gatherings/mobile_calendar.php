<?php
/**
 * Mobile Calendar View Template
 * 
 * Touch-optimized calendar for viewing gatherings on mobile devices.
 * Features swipe navigation, event indicators, and day expansion.
 * 
 * @var \App\View\AppView $this
 * @var int $defaultYear
 * @var int $defaultMonth
 * @var string $authCardUrl
 */

// Set mobile layout variables
$this->set('mobileTitle', 'Calendar');
$this->set('mobileBackUrl', $authCardUrl);
$this->set('mobileHeaderColor', '#198754'); // Green for calendar

$rsvpUrl = $this->Url->build(['controller' => 'GatheringAttendances', 'action' => 'mobileRsvp']);
$unrsvpUrl = $this->Url->build(['controller' => 'GatheringAttendances', 'action' => 'mobileUnrsvp']);
$updateRsvpUrl = $this->Url->build(['controller' => 'GatheringAttendances', 'action' => 'mobileUpdateRsvp']);
?>

<div class="mobile-calendar-container" 
     data-controller="mobile-calendar"
     data-mobile-calendar-year-value="<?= $defaultYear ?>"
     data-mobile-calendar-month-value="<?= $defaultMonth ?>"
     data-mobile-calendar-data-url-value="<?= $this->Url->build(['action' => 'mobileCalendarData']) ?>"
     data-mobile-calendar-rsvp-url-value="<?= h($rsvpUrl) ?>"
     data-mobile-calendar-unrsvp-url-value="<?= h($unrsvpUrl) ?>"
     data-mobile-calendar-update-rsvp-url-value="<?= h($updateRsvpUrl) ?>"
     role="application"
     aria-label="Calendar - Swipe left or right to change months">
    
    <!-- Calendar Header -->
    <div class="mobile-calendar-header card mx-3 mt-3 mb-2">
        <div class="card-body py-2 px-3">
            <div class="d-flex justify-content-between align-items-center">
                <button type="button" 
                        class="btn btn-outline-secondary btn-sm"
                        data-action="click->mobile-calendar#previousMonth"
                        aria-label="Go to previous month">
                    <i class="bi bi-chevron-left" aria-hidden="true"></i>
                </button>
                
                <h2 class="mb-0 fs-5" 
                    data-mobile-calendar-target="monthTitle"
                    aria-live="polite"
                    aria-atomic="true">
                    Loading...
                </h2>
                
                <button type="button" 
                        class="btn btn-outline-secondary btn-sm"
                        data-action="click->mobile-calendar#nextMonth"
                        aria-label="Go to next month">
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                </button>
            </div>
            
            <div class="text-center mt-2">
                <button type="button" 
                        class="btn btn-link btn-sm text-decoration-none"
                        data-action="click->mobile-calendar#goToToday"
                        aria-label="Go to today's date">
                    Today
                </button>
            </div>
        </div>
    </div>
    
    <!-- Loading State -->
    <div class="mobile-calendar-loading text-center py-5" 
         data-mobile-calendar-target="loading"
         role="status"
         aria-label="Loading calendar">
        <div class="spinner-border text-success" aria-hidden="true">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2 text-muted" aria-hidden="true">Loading calendar...</p>
    </div>
    
    <!-- Error State -->
    <div class="mobile-calendar-error mx-3" data-mobile-calendar-target="error" hidden>
        <div class="alert alert-warning d-flex align-items-center">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <span data-mobile-calendar-target="errorMessage">Unable to load calendar</span>
        </div>
        <button type="button" 
                class="btn btn-outline-warning w-100"
                data-action="click->mobile-calendar#reload"
                aria-label="Retry loading calendar">
            <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i> Retry
        </button>
    </div>
    
    <!-- Calendar Grid -->
    <div class="mobile-calendar-grid mx-3" 
         data-mobile-calendar-target="grid" 
         role="grid"
         aria-label="Calendar"
         hidden>
        <!-- Week Day Headers -->
        <div class="mobile-calendar-weekdays d-flex justify-content-around text-center mb-2" role="row">
            <div class="mobile-calendar-weekday text-muted" role="columnheader" abbr="Sunday">S</div>
            <div class="mobile-calendar-weekday text-muted" role="columnheader" abbr="Monday">M</div>
            <div class="mobile-calendar-weekday text-muted" role="columnheader" abbr="Tuesday">T</div>
            <div class="mobile-calendar-weekday text-muted" role="columnheader" abbr="Wednesday">W</div>
            <div class="mobile-calendar-weekday text-muted" role="columnheader" abbr="Thursday">T</div>
            <div class="mobile-calendar-weekday text-muted" role="columnheader" abbr="Friday">F</div>
            <div class="mobile-calendar-weekday text-muted" role="columnheader" abbr="Saturday">S</div>
        </div>
        
        <!-- Calendar Weeks (populated by JavaScript) -->
        <div class="mobile-calendar-weeks" data-mobile-calendar-target="weeks" role="rowgroup">
            <!-- Weeks will be rendered here -->
        </div>
    </div>
    
    <!-- Selected Day Events -->
    <div class="mobile-calendar-events mx-3 mt-3" 
         data-mobile-calendar-target="events" 
         role="region"
         aria-live="polite"
         aria-label="Events for selected day"
         hidden>
        <div class="card">
            <div class="card-header bg-success text-white py-2">
                <h3 class="mb-0 fs-6" data-mobile-calendar-target="selectedDate">
                    Events
                </h3>
            </div>
            <div class="card-body p-0" data-mobile-calendar-target="eventList">
                <!-- Events will be rendered here -->
            </div>
        </div>
    </div>
    
    <!-- Empty Day Message -->
    <div class="mobile-calendar-empty mx-3 mt-3" data-mobile-calendar-target="emptyDay" hidden>
        <div class="alert alert-light text-center mb-0">
            <i class="bi bi-calendar-x text-muted d-block fs-1 mb-2"></i>
            <p class="mb-0 text-muted">No events on this day</p>
        </div>
    </div>
</div>

<style>
/* Mobile Calendar Styles */
.mobile-calendar-container {
    padding-bottom: 80px; /* Space for potential bottom nav */
}

.mobile-calendar-weekdays {
    font-weight: 600;
    font-size: 12px;
    padding: 8px 0;
    background-color: rgba(255, 255, 255, 0.8);
    border-radius: 8px;
}

.mobile-calendar-weekday {
    width: 14.28%;
    flex: 0 0 14.28%;
}

.mobile-calendar-weeks {
    background-color: rgba(255, 255, 255, 0.9);
    border-radius: 12px;
    overflow: hidden;
}

.mobile-calendar-week {
    display: flex;
    justify-content: space-around;
}

.mobile-calendar-day {
    width: 14.28%;
    flex: 0 0 14.28%;
    aspect-ratio: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    position: relative;
    border: 1px solid transparent;
    transition: all 0.2s ease;
}

.mobile-calendar-day:active {
    background-color: rgba(25, 135, 84, 0.1);
}

.mobile-calendar-day.other-month {
    opacity: 0.4;
}

.mobile-calendar-day.today {
    background-color: rgba(25, 135, 84, 0.15);
    border-radius: 8px;
}

.mobile-calendar-day.today .mobile-calendar-day-number {
    font-weight: 700;
    color: #198754;
}

.mobile-calendar-day.selected {
    background-color: #198754;
    border-radius: 8px;
}

.mobile-calendar-day.selected .mobile-calendar-day-number {
    color: white;
    font-weight: 700;
}

.mobile-calendar-day.selected .mobile-calendar-event-dot {
    background-color: white !important;
}

.mobile-calendar-day-number {
    font-size: 14px;
    font-weight: 500;
    line-height: 1;
}

.mobile-calendar-event-dots {
    display: flex;
    gap: 2px;
    margin-top: 4px;
    height: 6px;
}

.mobile-calendar-event-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background-color: #198754;
}

.mobile-calendar-event-dot.attending {
    background-color: #0d6efd;
}

.mobile-calendar-event-dot.cancelled {
    background-color: #dc3545;
}

/* Event List Styles */
.mobile-event-item {
    padding: 12px 16px;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    text-decoration: none;
    color: inherit;
    transition: background-color 0.2s;
}

.mobile-event-item:last-child {
    border-bottom: none;
}

.mobile-event-item:active {
    background-color: #f8f9fa;
}

.mobile-event-time {
    min-width: 50px;
    font-size: 12px;
    color: #6c757d;
    text-align: center;
}

.mobile-event-time .time {
    font-weight: 600;
}

.mobile-event-details {
    flex: 1;
}

.mobile-event-name {
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 2px;
}

.mobile-event-name.cancelled {
    text-decoration: line-through;
    color: #dc3545;
}

.mobile-event-location {
    font-size: 12px;
    color: #6c757d;
}

.mobile-event-badge {
    display: flex;
    flex-direction: column;
    gap: 4px;
    align-items: flex-end;
}

.mobile-event-type {
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 4px;
}

.mobile-event-attending {
    font-size: 10px;
    color: #0d6efd;
}

/* Pull to refresh hint */
.mobile-calendar-pull-hint {
    text-align: center;
    padding: 8px;
    color: #6c757d;
    font-size: 12px;
}

/* Bottom Sheet Styles */
.mobile-rsvp-sheet {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 9998;
    visibility: hidden;
    opacity: 0;
    transition: visibility 0s 0.3s, opacity 0.3s;
}

.mobile-rsvp-sheet.open {
    visibility: visible;
    opacity: 1;
    transition: visibility 0s, opacity 0.3s;
}

.rsvp-sheet-backdrop {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
}

.rsvp-sheet-panel {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: white;
    border-radius: 20px 20px 0 0;
    max-height: 80vh;
    overflow-y: auto;
    transform: translateY(100%);
    transition: transform 0.3s ease-out;
}

.mobile-rsvp-sheet.open .rsvp-sheet-panel {
    transform: translateY(0);
}

.rsvp-sheet-handle {
    padding: 12px;
    text-align: center;
    cursor: pointer;
}

.handle-bar {
    width: 40px;
    height: 4px;
    background: #dee2e6;
    border-radius: 2px;
    margin: 0 auto;
}

.rsvp-sheet-body {
    padding: 0 20px 30px;
}

.rsvp-sheet-header {
    margin-bottom: 16px;
}

.rsvp-sheet-title {
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 8px;
}

.rsvp-sheet-details {
    color: #6c757d;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid #e9ecef;
}

.rsvp-sheet-details p {
    margin-bottom: 4px;
}

.rsvp-sharing-options .form-check {
    margin-bottom: 8px;
}

.rsvp-attending-status {
    display: flex;
    align-items: center;
}

/* Toast notifications */
.mobile-toast {
    max-width: 90%;
    animation: slideUp 0.3s ease-out;
}

@keyframes slideUp {
    from {
        transform: translate(-50%, 100%);
        opacity: 0;
    }
    to {
        transform: translate(-50%, 0);
        opacity: 1;
    }
}

/* RSVP button in event list */
.mobile-event-rsvp-btn {
    padding: 4px 8px;
    font-size: 11px;
    white-space: nowrap;
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
    transition: transform 0.3s;
}

.pull-to-refresh-indicator.ready .pull-spinner {
    transform: rotate(180deg);
}

.pull-spinner .spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.pull-text {
    font-size: 12px;
    color: var(--bs-secondary);
    margin-top: 4px;
}
</style>
