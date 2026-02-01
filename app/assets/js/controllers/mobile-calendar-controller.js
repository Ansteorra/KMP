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
                                <a href="/gatherings/public-landing/${event.public_id}?from=mobile" 
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
     * Create RSVP modal container
     */
    createBottomSheet() {
        // Create a Bootstrap modal container for RSVP
        if (document.getElementById('mobileRsvpModal')) return;
        
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = 'mobileRsvpModal';
        modal.tabIndex = -1;
        modal.setAttribute('aria-labelledby', 'mobileRsvpModalLabel');
        modal.setAttribute('aria-hidden', 'true');
        modal.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" data-mobile-calendar-target="rsvpContent">
                    <div class="modal-body text-center py-5">
                        <div class="spinner-border text-primary"></div>
                        <p class="mt-2 text-muted">Loading...</p>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        
        // Listen for modal hidden to refresh data
        modal.addEventListener('hidden.bs.modal', () => {
            this.loadCalendarData();
        });
    }

    /**
     * Show RSVP modal by loading attendance modal content from server
     */
    async showRsvpSheet(event) {
        event.preventDefault();
        event.stopPropagation();
        
        const button = event.currentTarget;
        const eventId = button.dataset.eventId;
        const eventData = this.filteredEvents.find(e => e.id == eventId) 
            || this.calendarData?.events?.find(e => e.id == eventId);
        
        if (!eventData) return;
        
        this.currentRsvpEvent = eventData;
        
        // Show modal with loading state
        const modalEl = document.getElementById('mobileRsvpModal');
        if (!modalEl) return;
        
        const modalContent = modalEl.querySelector('.modal-content');
        modalContent.innerHTML = `
            <div class="modal-body text-center py-5">
                <div class="spinner-border text-primary"></div>
                <p class="mt-2 text-muted">Loading...</p>
            </div>
        `;
        
        // Show the modal
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
        
        // Load attendance modal content from server
        try {
            let url = `/gatherings/attendance-modal/${eventData.id}`;
            if (eventData.attendance_id) {
                url += `?attendance_id=${eventData.attendance_id}`;
            }
            
            const response = await this.fetchWithRetry(url);
            const html = await response.text();
            
            modalContent.innerHTML = html;
            
            // Handle form submission via AJAX instead of regular submit
            this.setupModalFormHandlers(modalContent);
            
        } catch (error) {
            console.error('Failed to load attendance modal:', error);
            modalContent.innerHTML = `
                <div class="modal-header">
                    <h5 class="modal-title">Error</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        Failed to load attendance form. Please try again.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            `;
        }
    }

    /**
     * Setup form handlers for the attendance modal
     */
    setupModalFormHandlers(modalContent) {
        const mainForm = modalContent.querySelector('#attendanceModalForm');
        const deleteForm = modalContent.querySelector('[id^="deleteAttendanceForm_"]');
        
        if (mainForm) {
            mainForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                await this.handleAttendanceFormSubmit(mainForm);
            });
        }
        
        if (deleteForm) {
            deleteForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                await this.handleDeleteAttendance(deleteForm);
            });
        }
    }

    /**
     * Handle attendance form submission
     */
    async handleAttendanceFormSubmit(form) {
        const submitBtn = form.closest('.modal-content').querySelector('button[type="submit"][form]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
        }
        
        const formData = new FormData(form);
        
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (response.ok || response.redirected) {
                this.showToast('Attendance saved!', 'success');
                bootstrap.Modal.getInstance(document.getElementById('mobileRsvpModal'))?.hide();
            } else {
                throw new Error('Form submission failed');
            }
        } catch (error) {
            this.showToast('Failed to save attendance', 'danger');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Register';
            }
        }
    }

    /**
     * Handle delete attendance
     */
    async handleDeleteAttendance(form) {
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (response.ok || response.redirected) {
                this.showToast('Attendance removed!', 'success');
                bootstrap.Modal.getInstance(document.getElementById('mobileRsvpModal'))?.hide();
            } else {
                throw new Error('Delete failed');
            }
        } catch (error) {
            this.showToast('Failed to remove attendance', 'danger');
        }
    }

    // Legacy methods kept for compatibility (not used with new modal)
    openBottomSheet() {}
    closeBottomSheet() {
        bootstrap.Modal.getInstance(document.getElementById('mobileRsvpModal'))?.hide();
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
