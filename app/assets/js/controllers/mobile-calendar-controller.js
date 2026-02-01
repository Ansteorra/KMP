import MobileControllerBase from "./mobile-controller-base.js";
import offlineQueueService from "../services/offline-queue-service.js";

/**
 * Mobile Events Controller
 * 
 * Touch-optimized event list for viewing gatherings on mobile devices.
 * Extends MobileControllerBase for offline handling and retry logic.
 * 
 * Features:
 * - Weekly grouped event list
 * - Search and filtering (type, branch, RSVP status)
 * - Quick month navigation
 * - RSVP with offline queue support
 * - Pull-to-refresh support
 */
class MobileCalendarController extends MobileControllerBase {
    static targets = [
        "loading", "error", "errorMessage", 
        "eventList", "emptyState", "emptyMessage", "resultsCount",
        "searchInput", "filterPanel", "filterToggle",
        "typeFilter", "branchFilter", "rsvpFilter",
        "monthSelect", "yearSelect",
        "rsvpSheet", "rsvpContent"
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
        this.filteredEvents = [];
        this.searchDebounce = null;
        this.filters = {
            search: '',
            type: '',
            branch: '',
            rsvpOnly: false
        };
    }

    /**
     * Called after base class connect
     */
    onConnect() {
        console.log("Mobile Events connected");
        
        // Set up swipe handlers for navigation
        this._handleTouchStart = this.bindHandler('touchStart', this.handleTouchStart);
        this._handleTouchEnd = this.bindHandler('touchEnd', this.handleTouchEnd);
        
        this.element.addEventListener('touchstart', this._handleTouchStart, { passive: true });
        this.element.addEventListener('touchend', this._handleTouchEnd, { passive: true });
        
        // Initialize year selector
        this.initYearSelector();
        
        // Set initial month/year in selectors
        this.updateNavigationSelectors();
        
        // Set up pull-to-refresh
        this.setupPullToRefresh();
        
        // Create bottom sheet
        this.createBottomSheet();
        
        // Load initial data
        this.loadCalendarData();
    }

    /**
     * Called after base class disconnect
     */
    onDisconnect() {
        this.element.removeEventListener('touchstart', this._handleTouchStart);
        this.element.removeEventListener('touchend', this._handleTouchEnd);
    }

    /**
     * Initialize year selector with range
     */
    initYearSelector() {
        if (!this.hasYearSelectTarget) return;
        
        const currentYear = new Date().getFullYear();
        const startYear = currentYear - 1;
        const endYear = currentYear + 2;
        
        let html = '';
        for (let year = startYear; year <= endYear; year++) {
            html += `<option value="${year}">${year}</option>`;
        }
        this.yearSelectTarget.innerHTML = html;
    }

    /**
     * Update navigation selectors to current values
     */
    updateNavigationSelectors() {
        if (this.hasMonthSelectTarget) {
            this.monthSelectTarget.value = this.monthValue;
        }
        if (this.hasYearSelectTarget) {
            this.yearSelectTarget.value = this.yearValue;
        }
    }

    /**
     * Set up pull-to-refresh
     */
    setupPullToRefresh() {
        this.pullStartY = 0;
        this.isPulling = false;
        this.pullThreshold = 80;
        
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
     * Called when connection state changes
     */
    onConnectionStateChanged(isOnline) {
        if (isOnline && !this.calendarData) {
            this.loadCalendarData();
        }
    }

    /**
     * Load calendar data from server
     */
    async loadCalendarData() {
        this.showLoading();
        
        const url = `${this.dataUrlValue}?year=${this.yearValue}&month=${this.monthValue}`;
        
        try {
            const response = await this.fetchWithRetry(url);
            const data = await response.json();
            
            if (data.success) {
                this.calendarData = data.data;
                this.populateFilters();
                this.applyFilters();
                this.showEventList();
            } else {
                this.showError('Failed to load events');
            }
        } catch (error) {
            console.error('Calendar load error:', error);
            this.showError(this.online ? 'Failed to load events' : 'You\'re offline');
        }
    }

    // ==================== Display States ====================

    showLoading() {
        this.loadingTarget.hidden = false;
        this.errorTarget.hidden = true;
        this.eventListTarget.hidden = true;
        this.emptyStateTarget.hidden = true;
        if (this.hasResultsCountTarget) this.resultsCountTarget.hidden = true;
    }

    showError(message) {
        this.loadingTarget.hidden = true;
        this.errorTarget.hidden = false;
        this.eventListTarget.hidden = true;
        this.errorMessageTarget.textContent = message;
    }

    showEventList() {
        this.loadingTarget.hidden = true;
        this.errorTarget.hidden = true;
        
        if (this.filteredEvents.length === 0) {
            this.eventListTarget.hidden = true;
            this.emptyStateTarget.hidden = false;
            if (this.hasResultsCountTarget) this.resultsCountTarget.hidden = true;
        } else {
            this.eventListTarget.hidden = false;
            this.emptyStateTarget.hidden = true;
            this.renderEventList();
        }
    }

    reload() {
        this.loadCalendarData();
    }

    // ==================== Navigation ====================

    previousMonth() {
        if (this.monthValue === 1) {
            this.monthValue = 12;
            this.yearValue--;
        } else {
            this.monthValue--;
        }
        this.updateNavigationSelectors();
        this.loadCalendarData();
    }

    nextMonth() {
        if (this.monthValue === 12) {
            this.monthValue = 1;
            this.yearValue++;
        } else {
            this.monthValue++;
        }
        this.updateNavigationSelectors();
        this.loadCalendarData();
    }

    goToToday() {
        const today = new Date();
        this.yearValue = today.getFullYear();
        this.monthValue = today.getMonth() + 1;
        this.updateNavigationSelectors();
        this.loadCalendarData();
    }

    jumpToMonth() {
        this.monthValue = parseInt(this.monthSelectTarget.value);
        this.yearValue = parseInt(this.yearSelectTarget.value);
        this.loadCalendarData();
    }

    // ==================== Filtering ====================

    /**
     * Populate filter dropdowns from event data
     */
    populateFilters() {
        if (!this.calendarData?.events) return;
        
        // Collect unique types and branches
        const types = new Map();
        const branches = new Map();
        
        this.calendarData.events.forEach(event => {
            if (event.type?.name) {
                types.set(event.type.name, event.type);
            }
            if (event.branch) {
                branches.set(event.branch, event.branch);
            }
        });
        
        // Populate type filter
        if (this.hasTypeFilterTarget) {
            let html = '<option value="">All Types</option>';
            types.forEach((type, name) => {
                html += `<option value="${this.escapeHtml(name)}">${this.escapeHtml(name)}</option>`;
            });
            this.typeFilterTarget.innerHTML = html;
            this.typeFilterTarget.value = this.filters.type;
        }
        
        // Populate branch filter
        if (this.hasBranchFilterTarget) {
            let html = '<option value="">All Branches</option>';
            branches.forEach((branch) => {
                html += `<option value="${this.escapeHtml(branch)}">${this.escapeHtml(branch)}</option>`;
            });
            this.branchFilterTarget.innerHTML = html;
            this.branchFilterTarget.value = this.filters.branch;
        }
    }

    /**
     * Toggle filter panel visibility
     */
    toggleFilters() {
        this.filterPanelTarget.hidden = !this.filterPanelTarget.hidden;
        
        // Update filter toggle button appearance
        if (this.hasFilterToggleTarget) {
            const hasActiveFilters = this.filters.type || this.filters.branch || this.filters.rsvpOnly;
            this.filterToggleTarget.classList.toggle('filter-active', hasActiveFilters);
        }
    }

    /**
     * Apply current filters
     */
    applyFilters() {
        if (!this.calendarData?.events) {
            this.filteredEvents = [];
            return;
        }
        
        // Update filter values from inputs
        if (this.hasSearchInputTarget) {
            this.filters.search = this.searchInputTarget.value.toLowerCase().trim();
        }
        if (this.hasTypeFilterTarget) {
            this.filters.type = this.typeFilterTarget.value;
        }
        if (this.hasBranchFilterTarget) {
            this.filters.branch = this.branchFilterTarget.value;
        }
        if (this.hasRsvpFilterTarget) {
            this.filters.rsvpOnly = this.rsvpFilterTarget.checked;
        }
        
        // Filter events
        this.filteredEvents = this.calendarData.events.filter(event => {
            // Search filter
            if (this.filters.search) {
                const searchText = `${event.name} ${event.location || ''} ${event.branch || ''}`.toLowerCase();
                if (!searchText.includes(this.filters.search)) {
                    return false;
                }
            }
            
            // Type filter
            if (this.filters.type && event.type?.name !== this.filters.type) {
                return false;
            }
            
            // Branch filter
            if (this.filters.branch && event.branch !== this.filters.branch) {
                return false;
            }
            
            // RSVP filter
            if (this.filters.rsvpOnly && !event.user_attending) {
                return false;
            }
            
            return true;
        });
        
        // Update filter indicator
        if (this.hasFilterToggleTarget) {
            const hasActiveFilters = this.filters.type || this.filters.branch || this.filters.rsvpOnly;
            this.filterToggleTarget.classList.toggle('filter-active', hasActiveFilters);
        }
        
        // Update results count
        this.updateResultsCount();
        
        // Re-render
        this.showEventList();
    }

    /**
     * Handle search input with debounce
     */
    handleSearch() {
        clearTimeout(this.searchDebounce);
        this.searchDebounce = setTimeout(() => {
            this.applyFilters();
        }, 300);
    }

    /**
     * Clear all filters
     */
    clearFilters() {
        if (this.hasSearchInputTarget) this.searchInputTarget.value = '';
        if (this.hasTypeFilterTarget) this.typeFilterTarget.value = '';
        if (this.hasBranchFilterTarget) this.branchFilterTarget.value = '';
        if (this.hasRsvpFilterTarget) this.rsvpFilterTarget.checked = false;
        
        this.filters = { search: '', type: '', branch: '', rsvpOnly: false };
        this.applyFilters();
    }

    /**
     * Update results count display
     */
    updateResultsCount() {
        if (!this.hasResultsCountTarget) return;
        
        const total = this.calendarData?.events?.length || 0;
        const filtered = this.filteredEvents.length;
        
        if (total !== filtered) {
            this.resultsCountTarget.innerHTML = `<small class="text-muted">Showing ${filtered} of ${total} events</small>`;
            this.resultsCountTarget.hidden = false;
        } else {
            this.resultsCountTarget.innerHTML = `<small class="text-muted">${total} events</small>`;
            this.resultsCountTarget.hidden = false;
        }
    }

    // ==================== Rendering ====================

    /**
     * Render the event list grouped by week
     */
    renderEventList() {
        if (this.filteredEvents.length === 0) return;
        
        // Group events by week
        const weeks = this.groupEventsByWeek(this.filteredEvents);
        
        let html = '';
        weeks.forEach((weekEvents, weekLabel) => {
            html += `
                <div class="mobile-week-section mb-3">
                    <div class="mobile-week-header">
                        <span>${weekLabel}</span>
                        <span class="mobile-week-count">${weekEvents.length} event${weekEvents.length !== 1 ? 's' : ''}</span>
                    </div>
                    ${this.renderWeekEvents(weekEvents)}
                </div>
            `;
        });
        
        this.eventListTarget.innerHTML = html;
    }

    /**
     * Group events by week
     */
    groupEventsByWeek(events) {
        const weeks = new Map();
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        // Sort events by date
        const sortedEvents = [...events].sort((a, b) => {
            return new Date(a.start_date) - new Date(b.start_date);
        });
        
        sortedEvents.forEach(event => {
            const eventDate = new Date(event.start_date + 'T00:00:00');
            const weekStart = this.getWeekStart(eventDate);
            const weekEnd = new Date(weekStart);
            weekEnd.setDate(weekEnd.getDate() + 6);
            
            // Format week label
            const startMonth = weekStart.toLocaleDateString('en-US', { month: 'short' });
            const endMonth = weekEnd.toLocaleDateString('en-US', { month: 'short' });
            const startDay = weekStart.getDate();
            const endDay = weekEnd.getDate();
            
            let weekLabel;
            if (startMonth === endMonth) {
                weekLabel = `${startMonth} ${startDay} - ${endDay}`;
            } else {
                weekLabel = `${startMonth} ${startDay} - ${endMonth} ${endDay}`;
            }
            
            // Check if this week contains today
            if (today >= weekStart && today <= weekEnd) {
                weekLabel = `This Week (${weekLabel})`;
            }
            
            if (!weeks.has(weekLabel)) {
                weeks.set(weekLabel, []);
            }
            weeks.get(weekLabel).push(event);
        });
        
        return weeks;
    }

    /**
     * Get the start of the week (Sunday)
     */
    getWeekStart(date) {
        const d = new Date(date);
        const day = d.getDay();
        d.setDate(d.getDate() - day);
        return d;
    }

    /**
     * Render events for a week
     */
    renderWeekEvents(events) {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        return events.map(event => {
            const eventDate = new Date(event.start_date + 'T00:00:00');
            const isPast = eventDate < today;
            const isToday = eventDate.getTime() === today.getTime();
            
            const cardClasses = ['mobile-event-card'];
            if (event.is_cancelled) cardClasses.push('cancelled');
            if (event.user_attending) cardClasses.push('attending');
            
            const typeStyle = event.type?.color 
                ? `background-color: ${event.type.color}; color: white;` 
                : 'background-color: var(--bs-secondary); color: white;';
            
            const showRsvpButton = !event.is_cancelled && !isPast;
            const rsvpBtnClass = event.user_attending 
                ? 'btn btn-outline-success mobile-event-rsvp-btn' 
                : 'btn btn-success mobile-event-rsvp-btn';
            const rsvpBtnText = event.user_attending ? 'Edit' : 'RSVP';
            
            // Format date
            const dateStr = eventDate.toLocaleDateString('en-US', { 
                weekday: 'short', 
                month: 'short', 
                day: 'numeric' 
            });
            
            // Render activities
            const activitiesHtml = this.renderActivities(event.activities);
            
            return `
                <div class="${cardClasses.join(' ')}">
                    <div class="mobile-event-header">
                        <div class="mobile-event-info">
                            <a href="/gatherings/view/${event.public_id}" 
                               class="mobile-event-name ${event.is_cancelled ? 'cancelled' : ''} text-decoration-none">
                                ${this.escapeHtml(event.name)}
                            </a>
                            <div class="mobile-event-meta">
                                <span><i class="bi bi-calendar3"></i> ${dateStr}${isToday ? ' <strong>(Today)</strong>' : ''}</span>
                                ${event.start_time ? `<span><i class="bi bi-clock"></i> ${this.formatTime(event.start_time)}</span>` : ''}
                            </div>
                            <div class="mobile-event-meta">
                                ${event.branch ? `<span><i class="bi bi-building"></i> ${this.escapeHtml(event.branch)}</span>` : ''}
                                ${event.location ? `<span><i class="bi bi-geo-alt"></i> ${this.escapeHtml(event.location)}</span>` : ''}
                            </div>
                            ${activitiesHtml}
                            ${event.public_page_enabled ? `
                                <a href="/gatherings/public-landing/${event.public_id}" 
                                   class="btn btn-sm btn-outline-secondary mt-2">
                                    <i class="bi bi-info-circle me-1"></i>View Details
                                </a>
                            ` : ''}
                        </div>
                        <div class="mobile-event-actions">
                            ${event.type ? `<span class="mobile-event-type-badge" style="${typeStyle}">${this.escapeHtml(event.type.name)}</span>` : ''}
                            ${showRsvpButton ? `
                                <button type="button" 
                                        class="${rsvpBtnClass}"
                                        data-event-id="${event.id}"
                                        data-action="click->mobile-calendar#showRsvpSheet">
                                    ${rsvpBtnText}
                                </button>
                            ` : ''}
                            ${event.user_attending ? '<i class="bi bi-check-circle-fill text-success"></i>' : ''}
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    /**
     * Render activities list for an event
     */
    renderActivities(activities) {
        if (!activities || activities.length === 0) return '';
        
        const activityNames = activities.map(a => this.escapeHtml(a.name)).join(', ');
        
        return `
            <div class="mobile-event-activities">
                <i class="bi bi-list-check"></i>
                <span>${activityNames}</span>
            </div>
        `;
    }

    /**
     * Format time for display
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
     * Escape HTML entities
     */
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ==================== Touch Handlers ====================

    handleTouchStart(event) {
        this.touchStartX = event.touches[0].clientX;
        this.touchStartY = event.touches[0].clientY;
        
        if (window.scrollY === 0) {
            this.isPulling = true;
            this.pullStartY = event.touches[0].clientY;
        }
    }

    handleTouchEnd(event) {
        // Pull to refresh
        if (this.isPulling && this.pullIndicator) {
            const touchEndY = event.changedTouches[0].clientY;
            const pullDistance = touchEndY - this.pullStartY;
            
            if (pullDistance >= this.pullThreshold) {
                this.pullIndicator.querySelector('.pull-text').textContent = 'Refreshing...';
                this.pullIndicator.querySelector('.pull-spinner i').className = 'bi bi-arrow-clockwise spin';
                this.loadCalendarData().then(() => this.resetPullIndicator());
            } else {
                this.resetPullIndicator();
            }
            this.isPulling = false;
        }
        
        // Swipe navigation
        if (!this.touchStartX || !this.touchStartY) return;
        
        const touchEndX = event.changedTouches[0].clientX;
        const diffX = this.touchStartX - touchEndX;
        const diffY = this.touchStartY - event.changedTouches[0].clientY;
        
        if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 50) {
            if (diffX > 0) {
                this.nextMonth();
            } else {
                this.previousMonth();
            }
        }
        
        this.touchStartX = 0;
        this.touchStartY = 0;
    }

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
     * Create RSVP bottom sheet
     */
    createBottomSheet() {
        if (this.element.querySelector('.mobile-rsvp-sheet')) return;
        
        const sheet = document.createElement('div');
        sheet.className = 'mobile-rsvp-sheet';
        sheet.setAttribute('data-mobile-calendar-target', 'rsvpSheet');
        sheet.innerHTML = `
            <div class="rsvp-sheet-backdrop" data-action="click->mobile-calendar#closeBottomSheet"></div>
            <div class="rsvp-sheet-panel">
                <div class="rsvp-sheet-handle" data-action="click->mobile-calendar#closeBottomSheet">
                    <span></span>
                </div>
                <div class="rsvp-sheet-body" data-mobile-calendar-target="rsvpContent"></div>
            </div>
        `;
        this.element.appendChild(sheet);
    }

    showRsvpSheet(event) {
        event.preventDefault();
        event.stopPropagation();
        
        const button = event.currentTarget;
        const eventId = button.dataset.eventId;
        const eventData = this.filteredEvents.find(e => e.id == eventId) 
            || this.calendarData?.events?.find(e => e.id == eventId);
        
        if (!eventData) return;
        
        this.currentRsvpEvent = eventData;
        this.renderRsvpSheet(eventData);
        this.openBottomSheet();
    }

    renderRsvpSheet(event) {
        const isAttending = event.user_attending;
        
        this.rsvpContentTarget.innerHTML = `
            <div class="rsvp-sheet-header mb-3">
                <h3 class="rsvp-sheet-title">${this.escapeHtml(event.name)}</h3>
                <div class="rsvp-sheet-date">${this.formatEventDate(event)}</div>
            </div>
            ${isAttending ? this.renderEditRsvpForm(event) : this.renderNewRsvpForm(event)}
        `;
    }

    renderNewRsvpForm(event) {
        return `
            <form class="rsvp-form" data-action="submit->mobile-calendar#submitRsvp">
                <input type="hidden" name="gathering_id" value="${event.id}">
                
                <div class="mb-3">
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

    renderEditRsvpForm(event) {
        const shareKingdom = event.share_with_kingdom ? 'checked' : '';
        const shareHost = event.share_with_hosting_group ? 'checked' : '';
        const shareCrown = event.share_with_crown ? 'checked' : '';
        const publicNote = event.public_note || '';
        
        return `
            <div class="alert alert-success mb-3">
                <i class="bi bi-check-circle-fill me-2"></i>
                You're registered for this event!
            </div>
            
            <form class="edit-rsvp-form" data-action="submit->mobile-calendar#submitUpdateRsvp">
                <input type="hidden" name="attendance_id" value="${event.attendance_id}">
                
                <div class="mb-3">
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
                        <i class="bi bi-check-circle me-2"></i>Update
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

    formatEventDate(event) {
        const start = new Date(event.start_date + 'T' + (event.start_time || '00:00'));
        const options = { weekday: 'long', month: 'long', day: 'numeric' };
        let dateStr = start.toLocaleDateString('en-US', options);
        if (event.start_time) {
            dateStr += ` at ${this.formatTime(event.start_time)}`;
        }
        return dateStr;
    }

    openBottomSheet() {
        if (this.hasRsvpSheetTarget) {
            this.rsvpSheetTarget.classList.add('open');
            document.body.style.overflow = 'hidden';
        }
    }

    closeBottomSheet() {
        if (this.hasRsvpSheetTarget) {
            this.rsvpSheetTarget.classList.remove('open');
            document.body.style.overflow = '';
        }
    }

    async submitRsvp(event) {
        event.preventDefault();
        
        const form = event.currentTarget;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);
        
        data.share_with_kingdom = data.share_with_kingdom === '1';
        data.share_with_hosting_group = data.share_with_hosting_group === '1';
        data.share_with_crown = data.share_with_crown === '1';
        
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
        
        if (!this.online) {
            try {
                await offlineQueueService.queueAction('rsvp', this.rsvpUrlValue, 'POST', data, 
                    { eventName: this.currentRsvpEvent?.name });
                this.showToast('RSVP queued - will sync when online', 'warning');
                this.closeBottomSheet();
                if (this.currentRsvpEvent) {
                    this.currentRsvpEvent.user_attending = true;
                    this.applyFilters();
                }
            } catch (error) {
                this.showToast('Failed to queue RSVP', 'danger');
            }
            return;
        }
        
        try {
            const response = await this.fetchWithRetry(this.rsvpUrlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.getCsrfToken() },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showToast('RSVP confirmed!', 'success');
                this.closeBottomSheet();
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

    async submitUpdateRsvp(event) {
        event.preventDefault();
        
        const form = event.currentTarget;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);
        
        data.share_with_kingdom = data.share_with_kingdom === '1';
        data.share_with_hosting_group = data.share_with_hosting_group === '1';
        data.share_with_crown = data.share_with_crown === '1';
        
        const attendanceId = data.attendance_id;
        delete data.attendance_id;
        
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Updating...';
        
        if (!this.online) {
            try {
                await offlineQueueService.queueAction('update-rsvp', `${this.updateRsvpUrlValue}/${attendanceId}`, 
                    'PATCH', data, { eventName: this.currentRsvpEvent?.name });
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
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.getCsrfToken() },
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
                submitBtn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Update';
            }
        } catch (error) {
            this.showToast('Network error - please try again', 'danger');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Update';
        }
    }

    async cancelRsvp(event) {
        event.preventDefault();
        
        const button = event.currentTarget;
        const attendanceId = button.dataset.attendanceId;
        
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        
        if (!this.online) {
            try {
                await offlineQueueService.queueAction('unrsvp', `${this.unrsvpUrlValue}/${attendanceId}`, 
                    'DELETE', {}, { eventName: this.currentRsvpEvent?.name });
                this.showToast('Cancellation queued - will sync when online', 'warning');
                this.closeBottomSheet();
                if (this.currentRsvpEvent) {
                    this.currentRsvpEvent.user_attending = false;
                    this.currentRsvpEvent.attendance_id = null;
                    this.applyFilters();
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
                headers: { 'X-CSRF-Token': this.getCsrfToken() }
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

    // ==================== Utilities ====================

    getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]') 
            || document.querySelector('meta[name="csrfToken"]');
        return meta?.getAttribute('content') || '';
    }

    showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} position-fixed bottom-0 start-50 translate-middle-x mb-3`;
        toast.style.cssText = 'z-index: 9999; opacity: 0; transform: translateX(-50%) translateY(20px); transition: all 0.3s;';
        
        const icon = type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : 'info-circle';
        toast.innerHTML = `<i class="bi bi-${icon} me-2"></i>${message}`;
        document.body.appendChild(toast);
        
        requestAnimationFrame(() => {
            toast.style.opacity = '1';
            toast.style.transform = 'translateX(-50%) translateY(0)';
        });
        
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
