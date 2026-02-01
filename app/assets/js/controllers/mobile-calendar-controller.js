import MobileControllerBase from "./mobile-controller-base.js";
import offlineQueueService from "../services/offline-queue-service.js";

/**
 * Mobile Calendar Controller
 * 
 * Touch-optimized calendar for viewing gatherings on mobile devices.
 * Extends MobileControllerBase for offline handling and retry logic.
 * 
 * Features:
 * - Swipe navigation between months
 * - Touch-friendly day selection
 * - Event indicators (dots) on days with gatherings
 * - Expandable day details
 * - RSVP with offline queue support
 * - Pull-to-refresh support
 */
class MobileCalendarController extends MobileControllerBase {
    static targets = [
        "monthTitle", "loading", "error", "errorMessage", 
        "grid", "weeks", "events", "eventList", 
        "selectedDate", "emptyDay", "rsvpSheet", "rsvpContent"
    ]
    
    static values = {
        year: Number,
        month: Number,
        dataUrl: String,
        rsvpUrl: String,
        unrsvpUrl: String,
        updateRsvpUrl: String
    }

    initialize() {
        super.initialize();
        this.calendarData = null;
        this.selectedDate = null;
        this.touchStartX = 0;
        this.touchStartY = 0;
    }

    /**
     * Called after base class connect
     */
    onConnect() {
        console.log("Mobile Calendar connected");
        
        // Set up swipe handlers
        this._handleTouchStart = this.bindHandler('touchStart', this.handleTouchStart);
        this._handleTouchEnd = this.bindHandler('touchEnd', this.handleTouchEnd);
        
        this.element.addEventListener('touchstart', this._handleTouchStart, { passive: true });
        this.element.addEventListener('touchend', this._handleTouchEnd, { passive: true });
        
        // Set up pull-to-refresh
        this.setupPullToRefresh();
        
        // Load initial data
        this.loadCalendarData();
    }

    /**
     * Called after base class disconnect
     */
    onDisconnect() {
        this.element.removeEventListener('touchstart', this._handleTouchStart);
        this.element.removeEventListener('touchend', this._handleTouchEnd);
        
        // Clean up pull-to-refresh
        if (this._handleScroll) {
            window.removeEventListener('scroll', this._handleScroll);
        }
    }

    /**
     * Set up pull-to-refresh functionality
     */
    setupPullToRefresh() {
        this.pullStartY = 0;
        this.isPulling = false;
        this.pullThreshold = 80;
        
        // Create pull indicator element
        this.pullIndicator = document.createElement('div');
        this.pullIndicator.className = 'pull-to-refresh-indicator';
        this.pullIndicator.innerHTML = `
            <div class="pull-spinner">
                <i class="bi bi-arrow-down-circle"></i>
            </div>
            <span class="pull-text">Pull to refresh</span>
        `;
        this.element.insertBefore(this.pullIndicator, this.element.firstChild);
    }

    /**
     * Handle touch move for pull-to-refresh
     */
    handleTouchMove(event) {
        if (!this.isPulling) return;
        
        const touchY = event.touches[0].clientY;
        const pullDistance = touchY - this.pullStartY;
        
        // Only activate if at top of page and pulling down
        if (window.scrollY === 0 && pullDistance > 0) {
            event.preventDefault();
            
            const progress = Math.min(pullDistance / this.pullThreshold, 1);
            this.pullIndicator.style.height = `${Math.min(pullDistance, 80)}px`;
            this.pullIndicator.style.opacity = progress;
            
            if (pullDistance >= this.pullThreshold) {
                this.pullIndicator.querySelector('.pull-text').textContent = 'Release to refresh';
                this.pullIndicator.classList.add('ready');
            } else {
                this.pullIndicator.querySelector('.pull-text').textContent = 'Pull to refresh';
                this.pullIndicator.classList.remove('ready');
            }
        }
    }

    /**
     * Called when connection state changes
     */
    onConnectionStateChanged(isOnline) {
        if (isOnline && !this.calendarData) {
            // Retry loading when coming back online
            this.loadCalendarData();
        }
    }

    /**
     * Load calendar data from server
     */
    async loadCalendarData() {
        this.showLoading();
        
        try {
            const url = `${this.dataUrlValue}?year=${this.yearValue}&month=${this.monthValue}`;
            const response = await this.fetchWithRetry(url);
            const result = await response.json();
            
            if (result.success) {
                this.calendarData = result.data;
                this.renderCalendar();
                this.showGrid();
            } else {
                this.showError('Failed to load calendar data');
            }
        } catch (error) {
            console.error('Error loading calendar:', error);
            this.showError(this.online ? 'Failed to load calendar' : 'You\'re offline');
        }
    }

    /**
     * Show loading state
     */
    showLoading() {
        this.loadingTarget.hidden = false;
        this.errorTarget.hidden = true;
        this.gridTarget.hidden = true;
        this.eventsTarget.hidden = true;
        this.emptyDayTarget.hidden = true;
    }

    /**
     * Show error state
     */
    showError(message) {
        this.loadingTarget.hidden = true;
        this.errorTarget.hidden = false;
        this.gridTarget.hidden = true;
        this.errorMessageTarget.textContent = message;
    }

    /**
     * Show calendar grid
     */
    showGrid() {
        this.loadingTarget.hidden = true;
        this.errorTarget.hidden = true;
        this.gridTarget.hidden = false;
    }

    /**
     * Reload calendar data (retry button action)
     */
    reload() {
        this.loadCalendarData();
    }

    /**
     * Render the calendar grid
     */
    renderCalendar() {
        if (!this.calendarData) return;
        
        // Update title
        this.monthTitleTarget.textContent = `${this.calendarData.month_name} ${this.calendarData.year}`;
        
        // Build weeks
        const weeksHtml = this.buildWeeksHtml();
        this.weeksTarget.innerHTML = weeksHtml;
    }

    /**
     * Build HTML for calendar weeks
     */
    buildWeeksHtml() {
        const startDate = new Date(this.calendarData.calendar_start + 'T00:00:00');
        const endDate = new Date(this.calendarData.calendar_end + 'T00:00:00');
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        const currentMonth = this.calendarData.month;
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                           'July', 'August', 'September', 'October', 'November', 'December'];
        
        // Group events by date
        const eventsByDate = {};
        this.calendarData.events.forEach(event => {
            const dateKey = event.start_date;
            if (!eventsByDate[dateKey]) {
                eventsByDate[dateKey] = [];
            }
            eventsByDate[dateKey].push(event);
            
            // For multi-day events, add to each day
            if (event.is_multi_day) {
                const start = new Date(event.start_date + 'T00:00:00');
                const end = new Date(event.end_date + 'T00:00:00');
                let current = new Date(start);
                current.setDate(current.getDate() + 1);
                
                while (current <= end) {
                    const key = this.formatDateKey(current);
                    if (!eventsByDate[key]) {
                        eventsByDate[key] = [];
                    }
                    if (!eventsByDate[key].find(e => e.id === event.id)) {
                        eventsByDate[key].push(event);
                    }
                    current.setDate(current.getDate() + 1);
                }
            }
        });
        
        let html = '';
        let currentDate = new Date(startDate);
        
        while (currentDate <= endDate) {
            html += '<div class="mobile-calendar-week" role="row">';
            
            for (let i = 0; i < 7; i++) {
                const dateKey = this.formatDateKey(currentDate);
                const dayEvents = eventsByDate[dateKey] || [];
                const isCurrentMonth = (currentDate.getMonth() + 1) === currentMonth;
                const isToday = currentDate.getTime() === today.getTime();
                
                const classes = ['mobile-calendar-day'];
                if (!isCurrentMonth) classes.push('other-month');
                if (isToday) classes.push('today');
                
                // Build accessible label
                const dayOfMonth = currentDate.getDate();
                const monthName = monthNames[currentDate.getMonth()];
                const year = currentDate.getFullYear();
                const eventCount = dayEvents.length;
                let ariaLabel = `${monthName} ${dayOfMonth}, ${year}`;
                if (isToday) ariaLabel += ', today';
                if (eventCount > 0) ariaLabel += `, ${eventCount} event${eventCount > 1 ? 's' : ''}`;
                
                html += `
                    <div class="${classes.join(' ')}" 
                         role="gridcell"
                         tabindex="${isToday ? '0' : '-1'}"
                         aria-label="${ariaLabel}"
                         aria-selected="false"
                         data-date="${dateKey}"
                         data-action="click->mobile-calendar#selectDay keydown->mobile-calendar#handleDayKeydown">
                        <span class="mobile-calendar-day-number" aria-hidden="true">${dayOfMonth}</span>
                        ${this.renderEventDots(dayEvents)}
                    </div>
                `;
                
                currentDate.setDate(currentDate.getDate() + 1);
            }
            
            html += '</div>';
        }
        
        return html;
    }

    /**
     * Render event indicator dots
     */
    renderEventDots(events) {
        if (events.length === 0) return '';
        
        // Show up to 3 dots
        const dots = events.slice(0, 3).map(event => {
            let dotClass = 'mobile-calendar-event-dot';
            if (event.is_cancelled) {
                dotClass += ' cancelled';
            } else if (event.user_attending) {
                dotClass += ' attending';
            }
            return `<span class="${dotClass}" aria-hidden="true"></span>`;
        }).join('');
        
        return `<div class="mobile-calendar-event-dots" aria-hidden="true">${dots}</div>`;
    }

    /**
     * Format date as YYYY-MM-DD
     */
    formatDateKey(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    /**
     * Handle keyboard navigation for calendar days
     */
    handleDayKeydown(event) {
        const dayElement = event.currentTarget;
        const dateKey = dayElement.dataset.date;
        const currentDate = new Date(dateKey + 'T00:00:00');
        let newDate = null;
        
        switch (event.key) {
            case 'Enter':
            case ' ':
                event.preventDefault();
                this.selectDay(event);
                break;
            case 'ArrowRight':
                event.preventDefault();
                newDate = new Date(currentDate);
                newDate.setDate(newDate.getDate() + 1);
                break;
            case 'ArrowLeft':
                event.preventDefault();
                newDate = new Date(currentDate);
                newDate.setDate(newDate.getDate() - 1);
                break;
            case 'ArrowDown':
                event.preventDefault();
                newDate = new Date(currentDate);
                newDate.setDate(newDate.getDate() + 7);
                break;
            case 'ArrowUp':
                event.preventDefault();
                newDate = new Date(currentDate);
                newDate.setDate(newDate.getDate() - 7);
                break;
            case 'Home':
                event.preventDefault();
                // First day of week (Sunday)
                newDate = new Date(currentDate);
                newDate.setDate(newDate.getDate() - newDate.getDay());
                break;
            case 'End':
                event.preventDefault();
                // Last day of week (Saturday)
                newDate = new Date(currentDate);
                newDate.setDate(newDate.getDate() + (6 - newDate.getDay()));
                break;
        }
        
        if (newDate) {
            const newDateKey = this.formatDateKey(newDate);
            const newDayElement = this.weeksTarget.querySelector(`[data-date="${newDateKey}"]`);
            if (newDayElement) {
                newDayElement.focus();
            } else {
                // Navigate to new month if needed
                const newMonth = newDate.getMonth() + 1;
                const newYear = newDate.getFullYear();
                if (newMonth !== this.monthValue || newYear !== this.yearValue) {
                    this.monthValue = newMonth;
                    this.yearValue = newYear;
                    this.loadCalendarData().then(() => {
                        const targetElement = this.weeksTarget.querySelector(`[data-date="${newDateKey}"]`);
                        if (targetElement) targetElement.focus();
                    });
                }
            }
        }
    }

    /**
     * Handle day selection
     */
    selectDay(event) {
        const dayElement = event.currentTarget;
        const dateKey = dayElement.dataset.date;
        
        // Remove previous selection
        const prevSelected = this.weeksTarget.querySelector('.selected');
        if (prevSelected) {
            prevSelected.classList.remove('selected');
            prevSelected.setAttribute('aria-selected', 'false');
        }
        
        // Add selection to clicked day
        dayElement.classList.add('selected');
        dayElement.setAttribute('aria-selected', 'true');
        this.selectedDate = dateKey;
        
        // Show events for this day
        this.showDayEvents(dateKey);
    }

    /**
     * Show events for selected day
     */
    showDayEvents(dateKey) {
        const events = this.calendarData.events.filter(event => {
            // Check if event is on this day
            if (event.start_date === dateKey) return true;
            if (event.is_multi_day) {
                const start = new Date(event.start_date + 'T00:00:00');
                const end = new Date(event.end_date + 'T00:00:00');
                const check = new Date(dateKey + 'T00:00:00');
                return check >= start && check <= end;
            }
            return false;
        });
        
        // Format date for display
        const date = new Date(dateKey + 'T00:00:00');
        const options = { weekday: 'long', month: 'long', day: 'numeric' };
        this.selectedDateTarget.textContent = date.toLocaleDateString('en-US', options);
        
        if (events.length === 0) {
            this.eventsTarget.hidden = true;
            this.emptyDayTarget.hidden = false;
        } else {
            this.emptyDayTarget.hidden = true;
            this.eventsTarget.hidden = false;
            this.eventListTarget.innerHTML = this.renderEventList(events);
        }
    }

    /**
     * Render list of events for a day
     */
    renderEventList(events) {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        return events.map(event => {
            const typeStyle = event.type?.color ? `background-color: ${event.type.color}; color: white;` : '';
            const nameClass = event.is_cancelled ? 'mobile-event-name cancelled' : 'mobile-event-name';
            const rsvpBtnClass = event.user_attending 
                ? 'btn btn-outline-primary mobile-event-rsvp-btn' 
                : 'btn btn-success mobile-event-rsvp-btn';
            const rsvpBtnText = event.user_attending ? 'Edit RSVP' : 'RSVP';
            
            // Check if event is in the past (end_date < today)
            const eventEndDate = new Date(event.end_date + 'T23:59:59');
            const isPastEvent = eventEndDate < today;
            const showRsvpButton = !event.is_cancelled && !isPastEvent;
            
            return `
                <div class="mobile-event-item">
                    <div class="mobile-event-time">
                        <div class="time">${this.formatTime(event.start_time)}</div>
                    </div>
                    <div class="mobile-event-details">
                        <a href="/gatherings/view/${event.public_id}" class="${nameClass} text-decoration-none">${this.escapeHtml(event.name)}</a>
                        ${event.location ? `<div class="mobile-event-location"><i class="bi bi-geo-alt"></i> ${this.escapeHtml(event.location)}</div>` : ''}
                        ${event.branch ? `<div class="mobile-event-location"><i class="bi bi-building"></i> ${this.escapeHtml(event.branch)}</div>` : ''}
                    </div>
                    <div class="mobile-event-badge">
                        ${event.type ? `<span class="mobile-event-type badge" style="${typeStyle}">${this.escapeHtml(event.type.name)}</span>` : ''}
                        ${showRsvpButton ? `
                            <button type="button" 
                                    class="${rsvpBtnClass}"
                                    data-event-id="${event.id}"
                                    data-action="click->mobile-calendar#showRsvpSheet">
                                ${rsvpBtnText}
                            </button>
                        ` : ''}
                    </div>
                </div>
            `;
        }).join('');
    }

    /**
     * Format time for display (12-hour format)
     */
    formatTime(time) {
        if (!time) return '';
        const [hours, minutes] = time.split(':');
        const hour = parseInt(hours);
        const ampm = hour >= 12 ? 'PM' : 'AM';
        const hour12 = hour % 12 || 12;
        return `${hour12}:${minutes} ${ampm}`;
    }

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Navigate to previous month
     */
    previousMonth() {
        let newMonth = this.monthValue - 1;
        let newYear = this.yearValue;
        
        if (newMonth < 1) {
            newMonth = 12;
            newYear--;
        }
        
        this.monthValue = newMonth;
        this.yearValue = newYear;
        this.selectedDate = null;
        this.eventsTarget.hidden = true;
        this.emptyDayTarget.hidden = true;
        this.loadCalendarData();
    }

    /**
     * Navigate to next month
     */
    nextMonth() {
        let newMonth = this.monthValue + 1;
        let newYear = this.yearValue;
        
        if (newMonth > 12) {
            newMonth = 1;
            newYear++;
        }
        
        this.monthValue = newMonth;
        this.yearValue = newYear;
        this.selectedDate = null;
        this.eventsTarget.hidden = true;
        this.emptyDayTarget.hidden = true;
        this.loadCalendarData();
    }

    /**
     * Navigate to today
     */
    goToToday() {
        const today = new Date();
        this.yearValue = today.getFullYear();
        this.monthValue = today.getMonth() + 1;
        this.selectedDate = null;
        this.eventsTarget.hidden = true;
        this.emptyDayTarget.hidden = true;
        this.loadCalendarData();
    }

    /**
     * Reload calendar data
     */
    reload() {
        this.loadCalendarData();
    }

    /**
     * Handle touch start for swipe detection
     */
    handleTouchStart(event) {
        this.touchStartX = event.touches[0].clientX;
        this.touchStartY = event.touches[0].clientY;
        
        // Start pull-to-refresh tracking if at top of page
        if (window.scrollY === 0) {
            this.isPulling = true;
            this.pullStartY = event.touches[0].clientY;
        }
    }

    /**
     * Handle touch end for swipe detection
     */
    handleTouchEnd(event) {
        // Handle pull-to-refresh
        if (this.isPulling && this.pullIndicator) {
            const touchEndY = event.changedTouches[0].clientY;
            const pullDistance = touchEndY - this.pullStartY;
            
            if (pullDistance >= this.pullThreshold) {
                // Trigger refresh
                this.pullIndicator.querySelector('.pull-text').textContent = 'Refreshing...';
                this.pullIndicator.querySelector('.pull-spinner i').className = 'bi bi-arrow-clockwise spin';
                this.loadCalendarData().then(() => {
                    this.resetPullIndicator();
                });
            } else {
                this.resetPullIndicator();
            }
            
            this.isPulling = false;
        }
        
        if (!this.touchStartX || !this.touchStartY) return;
        
        const touchEndX = event.changedTouches[0].clientX;
        const touchEndY = event.changedTouches[0].clientY;
        
        const diffX = this.touchStartX - touchEndX;
        const diffY = this.touchStartY - touchEndY;
        
        // Only handle horizontal swipes (ignore vertical scrolling)
        if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 50) {
            if (diffX > 0) {
                // Swipe left - next month
                this.nextMonth();
            } else {
                // Swipe right - previous month
                this.previousMonth();
            }
        }
        
        this.touchStartX = 0;
        this.touchStartY = 0;
    }

    /**
     * Reset pull indicator to hidden state
     */
    resetPullIndicator() {
        if (!this.pullIndicator) return;
        
        this.pullIndicator.style.height = '0';
        this.pullIndicator.style.opacity = '0';
        this.pullIndicator.classList.remove('ready');
        this.pullIndicator.querySelector('.pull-text').textContent = 'Pull to refresh';
        this.pullIndicator.querySelector('.pull-spinner i').className = 'bi bi-arrow-down-circle';
    }

    // ==================== RSVP Methods ====================

    /**
     * Show RSVP bottom sheet for an event
     */
    showRsvpSheet(event) {
        event.preventDefault();
        event.stopPropagation();
        
        const button = event.currentTarget;
        const eventId = button.dataset.eventId;
        const eventData = this.calendarData.events.find(e => e.id == eventId);
        
        if (!eventData) return;
        
        this.currentRsvpEvent = eventData;
        this.renderRsvpSheet(eventData);
        this.openBottomSheet();
    }

    /**
     * Render RSVP bottom sheet content
     */
    renderRsvpSheet(event) {
        if (!this.hasRsvpContentTarget) return;
        
        const isAttending = event.user_attending;
        const typeStyle = event.type?.color ? `background-color: ${event.type.color}; color: white;` : '';
        
        this.rsvpContentTarget.innerHTML = `
            <div class="rsvp-sheet-header">
                <h3 class="rsvp-sheet-title">${this.escapeHtml(event.name)}</h3>
                ${event.type ? `<span class="badge" style="${typeStyle}">${this.escapeHtml(event.type.name)}</span>` : ''}
            </div>
            
            <div class="rsvp-sheet-details">
                <p class="mb-1"><i class="bi bi-calendar me-2"></i>${this.formatEventDate(event)}</p>
                ${event.location ? `<p class="mb-1"><i class="bi bi-geo-alt me-2"></i>${this.escapeHtml(event.location)}</p>` : ''}
                ${event.branch ? `<p class="mb-1"><i class="bi bi-building me-2"></i>${this.escapeHtml(event.branch)}</p>` : ''}
            </div>
            
            ${isAttending ? this.renderUnrsvpForm(event) : this.renderRsvpForm(event)}
        `;
    }

    /**
     * Render RSVP form for non-attending event
     */
    renderRsvpForm(event) {
        return `
            <form class="rsvp-form" data-action="submit->mobile-calendar#submitRsvp">
                <input type="hidden" name="gathering_id" value="${event.id}">
                
                <div class="rsvp-sharing-options mb-3">
                    <label class="form-label">Share your attendance with:</label>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="share_with_kingdom" id="share_kingdom" value="1">
                        <label class="form-check-label" for="share_kingdom">Kingdom</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="share_with_hosting_group" id="share_host" value="1">
                        <label class="form-check-label" for="share_host">Hosting Group</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="share_with_crown" id="share_crown" value="1">
                        <label class="form-check-label" for="share_crown">Crown</label>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label" for="public_note">Note (optional)</label>
                    <textarea class="form-control" name="public_note" id="public_note" rows="2" 
                              placeholder="Camping, daytripping, etc."></textarea>
                </div>
                
                <button type="submit" class="btn btn-success btn-lg w-100">
                    <i class="bi bi-check-circle me-2"></i>Confirm RSVP
                </button>
            </form>
        `;
    }

    /**
     * Render edit RSVP form for attending event
     */
    renderUnrsvpForm(event) {
        // Get current visibility settings from the event
        const shareKingdom = event.share_with_kingdom ? 'checked' : '';
        const shareHost = event.share_with_hosting_group ? 'checked' : '';
        const shareCrown = event.share_with_crown ? 'checked' : '';
        const publicNote = event.public_note || '';
        
        return `
            <div class="rsvp-attending-status alert alert-success mb-3">
                <i class="bi bi-check-circle-fill me-2"></i>
                You're registered for this event!
            </div>
            
            <form class="edit-rsvp-form" data-action="submit->mobile-calendar#submitUpdateRsvp">
                <input type="hidden" name="attendance_id" value="${event.attendance_id}">
                
                <div class="rsvp-sharing-options mb-3">
                    <label class="form-label">Share your attendance with:</label>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="share_with_kingdom" id="edit_share_kingdom" value="1" ${shareKingdom}>
                        <label class="form-check-label" for="edit_share_kingdom">Kingdom</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="share_with_hosting_group" id="edit_share_host" value="1" ${shareHost}>
                        <label class="form-check-label" for="edit_share_host">Hosting Group</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="share_with_crown" id="edit_share_crown" value="1" ${shareCrown}>
                        <label class="form-check-label" for="edit_share_crown">Crown</label>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label" for="edit_public_note">Note (optional)</label>
                    <textarea class="form-control" name="public_note" id="edit_public_note" rows="2" 
                              placeholder="Camping, daytripping, etc.">${this.escapeHtml(publicNote)}</textarea>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-lg flex-grow-1">
                        <i class="bi bi-check-circle me-2"></i>Update RSVP
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-lg" 
                            data-action="click->mobile-calendar#cancelRsvp"
                            data-attendance-id="${event.attendance_id}">
                        <i class="bi bi-x-circle"></i>
                    </button>
                </div>
            </form>
        `;
    }

    /**
     * Format event date for display
     */
    formatEventDate(event) {
        const start = new Date(event.start_date + 'T' + event.start_time);
        const options = { weekday: 'short', month: 'short', day: 'numeric' };
        let dateStr = start.toLocaleDateString('en-US', options);
        dateStr += ` at ${this.formatTime(event.start_time)}`;
        return dateStr;
    }

    /**
     * Open bottom sheet
     */
    openBottomSheet() {
        if (!this.hasRsvpSheetTarget) {
            this.createRsvpSheet();
        }
        
        this.rsvpSheetTarget.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    /**
     * Close bottom sheet
     */
    closeBottomSheet() {
        if (this.hasRsvpSheetTarget) {
            this.rsvpSheetTarget.classList.remove('open');
            document.body.style.overflow = '';
        }
    }

    /**
     * Create RSVP bottom sheet element
     */
    createRsvpSheet() {
        const sheet = document.createElement('div');
        sheet.className = 'mobile-rsvp-sheet';
        sheet.setAttribute('data-mobile-calendar-target', 'rsvpSheet');
        sheet.innerHTML = `
            <div class="rsvp-sheet-backdrop" data-action="click->mobile-calendar#closeBottomSheet"></div>
            <div class="rsvp-sheet-panel">
                <div class="rsvp-sheet-handle" data-action="click->mobile-calendar#closeBottomSheet">
                    <div class="handle-bar"></div>
                </div>
                <div class="rsvp-sheet-body" data-mobile-calendar-target="rsvpContent">
                    <!-- Content rendered dynamically -->
                </div>
            </div>
        `;
        this.element.appendChild(sheet);
    }

    /**
     * Submit RSVP form
     */
    async submitRsvp(event) {
        event.preventDefault();
        
        const form = event.currentTarget;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);
        
        // Convert checkbox values
        data.share_with_kingdom = data.share_with_kingdom === '1';
        data.share_with_hosting_group = data.share_with_hosting_group === '1';
        data.share_with_crown = data.share_with_crown === '1';
        
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
        
        if (!this.online) {
            // Queue for offline sync
            try {
                await offlineQueueService.queueAction(
                    'rsvp',
                    this.rsvpUrlValue,
                    'POST',
                    data,
                    { eventName: this.currentRsvpEvent?.name }
                );
                this.showToast('RSVP queued - will sync when online', 'warning');
                this.closeBottomSheet();
                
                // Optimistically update UI
                if (this.currentRsvpEvent) {
                    this.currentRsvpEvent.user_attending = true;
                    this.renderCalendar();
                }
            } catch (error) {
                this.showToast('Failed to queue RSVP', 'danger');
            }
            return;
        }
        
        try {
            const response = await this.fetchWithRetry(this.rsvpUrlValue, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCsrfToken()
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showToast('RSVP confirmed!', 'success');
                this.closeBottomSheet();
                // Reload calendar to show updated attendance
                this.loadCalendarData();
            } else {
                this.showToast(result.error || 'Failed to RSVP', 'danger');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Confirm RSVP';
            }
        } catch (error) {
            this.showToast('Network error - please try again', 'danger');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Confirm RSVP';
        }
    }

    /**
     * Submit un-RSVP form
     */
    async submitUnrsvp(event) {
        event.preventDefault();
        
        const form = event.currentTarget;
        const attendanceId = form.querySelector('[name="attendance_id"]').value;
        
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Removing...';
        
        if (!this.online) {
            // Queue for offline sync
            try {
                await offlineQueueService.queueAction(
                    'unrsvp',
                    `${this.unrsvpUrlValue}/${attendanceId}`,
                    'DELETE',
                    {},
                    { eventName: this.currentRsvpEvent?.name }
                );
                this.showToast('Cancellation queued - will sync when online', 'warning');
                this.closeBottomSheet();
                
                // Optimistically update UI
                if (this.currentRsvpEvent) {
                    this.currentRsvpEvent.user_attending = false;
                    this.currentRsvpEvent.attendance_id = null;
                    this.renderCalendar();
                }
            } catch (error) {
                this.showToast('Failed to queue cancellation', 'danger');
            }
            return;
        }
        
        try {
            const response = await this.fetchWithRetry(`${this.unrsvpUrlValue}/${attendanceId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-Token': this.getCsrfToken()
                }
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showToast('RSVP cancelled', 'success');
                this.closeBottomSheet();
                this.loadCalendarData();
            } else {
                this.showToast(result.error || 'Failed to cancel RSVP', 'danger');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-x-circle me-2"></i>Cancel My RSVP';
            }
        } catch (error) {
            this.showToast('Network error - please try again', 'danger');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-x-circle me-2"></i>Cancel My RSVP';
        }
    }

    /**
     * Submit update RSVP form (edit visibility settings)
     */
    async submitUpdateRsvp(event) {
        event.preventDefault();
        
        const form = event.currentTarget;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);
        
        // Convert checkbox values
        data.share_with_kingdom = data.share_with_kingdom === '1';
        data.share_with_hosting_group = data.share_with_hosting_group === '1';
        data.share_with_crown = data.share_with_crown === '1';
        
        const attendanceId = data.attendance_id;
        delete data.attendance_id;
        
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Updating...';
        
        if (!this.online) {
            // Queue for offline sync
            try {
                await offlineQueueService.queueAction(
                    'update-rsvp',
                    `${this.updateRsvpUrlValue}/${attendanceId}`,
                    'PATCH',
                    data,
                    { eventName: this.currentRsvpEvent?.name }
                );
                this.showToast('Update queued - will sync when online', 'warning');
                this.closeBottomSheet();
            } catch (error) {
                this.showToast('Failed to queue update', 'danger');
            }
            return;
        }
        
        try {
            const response = await this.fetchWithRetry(`${this.updateRsvpUrlValue}/${attendanceId}`, {
                method: 'PATCH',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCsrfToken()
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showToast('RSVP updated!', 'success');
                this.closeBottomSheet();
                this.loadCalendarData();
            } else {
                this.showToast(result.error || 'Failed to update RSVP', 'danger');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Update RSVP';
            }
        } catch (error) {
            this.showToast('Network error - please try again', 'danger');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Update RSVP';
        }
    }

    /**
     * Cancel RSVP (from edit form cancel button)
     */
    async cancelRsvp(event) {
        event.preventDefault();
        
        const button = event.currentTarget;
        const attendanceId = button.dataset.attendanceId;
        
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        
        if (!this.online) {
            try {
                await offlineQueueService.queueAction(
                    'unrsvp',
                    `${this.unrsvpUrlValue}/${attendanceId}`,
                    'DELETE',
                    {},
                    { eventName: this.currentRsvpEvent?.name }
                );
                this.showToast('Cancellation queued - will sync when online', 'warning');
                this.closeBottomSheet();
                
                if (this.currentRsvpEvent) {
                    this.currentRsvpEvent.user_attending = false;
                    this.currentRsvpEvent.attendance_id = null;
                    this.renderCalendar();
                }
            } catch (error) {
                this.showToast('Failed to queue cancellation', 'danger');
                button.disabled = false;
                button.innerHTML = '<i class="bi bi-x-circle"></i>';
            }
            return;
        }
        
        try {
            const response = await this.fetchWithRetry(`${this.unrsvpUrlValue}/${attendanceId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-Token': this.getCsrfToken()
                }
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showToast('RSVP cancelled', 'success');
                this.closeBottomSheet();
                this.loadCalendarData();
            } else {
                this.showToast(result.error || 'Failed to cancel RSVP', 'danger');
                button.disabled = false;
                button.innerHTML = '<i class="bi bi-x-circle"></i>';
            }
        } catch (error) {
            this.showToast('Network error - please try again', 'danger');
            button.disabled = false;
            button.innerHTML = '<i class="bi bi-x-circle"></i>';
        }
    }

    /**
     * Get CSRF token from meta tag
     */
    getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) {
            return meta.getAttribute('content');
        }
        // Fallback to csrfToken meta name (alternative format)
        const metaAlt = document.querySelector('meta[name="csrfToken"]');
        if (metaAlt) {
            return metaAlt.getAttribute('content');
        }
        return '';
    }

    /**
     * Show toast notification
     */
    showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `mobile-toast alert alert-${type} position-fixed bottom-0 start-50 translate-middle-x mb-3`;
        toast.style.zIndex = '9999';
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(-50%) translateY(20px)';
        toast.style.transition = 'opacity 0.3s, transform 0.3s';
        toast.innerHTML = `<i class="bi bi-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : 'info-circle'} me-2"></i>${message}`;
        document.body.appendChild(toast);
        
        // Animate in
        requestAnimationFrame(() => {
            toast.style.opacity = '1';
            toast.style.transform = 'translateX(-50%) translateY(0)';
        });
        
        // Animate out and remove
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(-50%) translateY(20px)';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
}

// Register controller
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["mobile-calendar"] = MobileCalendarController;

export default MobileCalendarController;
