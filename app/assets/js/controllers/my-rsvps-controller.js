import MobileControllerBase from "./mobile-controller-base";
import rsvpCacheService from "../services/rsvp-cache-service.js";

/**
 * My RSVPs Controller
 * 
 * Handles RSVP editing using the attendance modal on the My RSVPs page.
 * Supports offline mode by displaying cached RSVPs.
 */
class MyRsvpsController extends MobileControllerBase {
    static targets = ["modal", "modalBody", "offlineBanner", "offlineList", "onlineContent", "emptyState"];

    onConnect() {
        this.modal = null;
        this.currentGatheringId = null;
        
        // Initialize RSVP cache service
        rsvpCacheService.init().catch(err => {
            console.warn('[MyRsvps] Failed to init RSVP cache:', err);
        });
        
        // Ensure correct visibility based on online status
        if (navigator.onLine) {
            // Explicitly hide offline elements when online
            if (this.hasOfflineBannerTarget) this.offlineBannerTarget.hidden = true;
            if (this.hasOfflineListTarget) this.offlineListTarget.hidden = true;
            if (this.hasOnlineContentTarget) this.onlineContentTarget.hidden = false;
        } else {
            // Show cached content when offline
            this.updateOfflineState();
        }
        
        // Set up modal event listener
        if (this.hasModalTarget) {
            this.modalTarget.addEventListener('hidden.bs.modal', () => {
                this.onModalHidden();
            });
        }
    }

    /**
     * Called when connection state changes
     */
    onConnectionStateChanged(isOnline) {
        this.updateOfflineState();
    }

    /**
     * Update UI based on offline status
     */
    async updateOfflineState() {
        const isOnline = navigator.onLine;
        
        if (isOnline) {
            // Online - show server content, hide offline content
            if (this.hasOfflineBannerTarget) this.offlineBannerTarget.hidden = true;
            if (this.hasOfflineListTarget) this.offlineListTarget.hidden = true;
            if (this.hasOnlineContentTarget) this.onlineContentTarget.hidden = false;
        } else {
            // Offline - show cached RSVPs
            if (this.hasOfflineBannerTarget) this.offlineBannerTarget.hidden = false;
            if (this.hasOnlineContentTarget) this.onlineContentTarget.hidden = true;
            
            await this.renderCachedRsvps();
        }
    }

    /**
     * Render cached RSVPs when offline
     */
    async renderCachedRsvps() {
        if (!this.hasOfflineListTarget) return;
        
        try {
            const cachedRsvps = await rsvpCacheService.getAllCachedRsvps();
            
            if (cachedRsvps.length === 0) {
                this.offlineListTarget.innerHTML = `
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-cloud-slash text-muted d-block fs-1 mb-3"></i>
                            <h3 class="h5 mb-2">No Cached RSVPs</h3>
                            <p class="text-muted mb-0">
                                Connect to the internet to view and manage your RSVPs.
                            </p>
                        </div>
                    </div>
                `;
            } else {
                // Filter to only future events
                const now = new Date();
                now.setHours(0, 0, 0, 0);
                
                const futureRsvps = cachedRsvps.filter(rsvp => {
                    const eventDate = new Date(rsvp.start_date + 'T00:00:00');
                    return eventDate >= now;
                }).sort((a, b) => new Date(a.start_date) - new Date(b.start_date));
                
                if (futureRsvps.length === 0) {
                    this.offlineListTarget.innerHTML = `
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="bi bi-calendar-check text-muted d-block fs-1 mb-3"></i>
                                <h3 class="h5 mb-2">No Upcoming RSVPs</h3>
                                <p class="text-muted mb-0">
                                    Your cached RSVPs are all in the past.
                                </p>
                            </div>
                        </div>
                    `;
                } else {
                    this.offlineListTarget.innerHTML = `
                        <div class="rsvp-list">
                            ${futureRsvps.map(rsvp => this.renderCachedRsvpCard(rsvp)).join('')}
                        </div>
                    `;
                }
            }
            
            this.offlineListTarget.hidden = false;
            
        } catch (error) {
            console.error('[MyRsvps] Failed to load cached RSVPs:', error);
            this.offlineListTarget.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Failed to load cached RSVPs.
                </div>
            `;
            this.offlineListTarget.hidden = false;
        }
    }

    /**
     * Render a single cached RSVP card
     */
    renderCachedRsvpCard(rsvp) {
        const eventDate = new Date(rsvp.start_date + 'T00:00:00');
        const dateStr = eventDate.toLocaleDateString('en-US', {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
        
        const timeStr = rsvp.start_time ? this.formatTime(rsvp.start_time) : '';
        
        return `
            <div class="rsvp-card card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h4 class="rsvp-event-name h6 mb-1">${this.escapeHtml(rsvp.name)}</h4>
                        </div>
                        <span class="badge bg-secondary">
                            <i class="bi bi-cloud-slash me-1"></i>Cached
                        </span>
                    </div>
                    
                    <div class="rsvp-event-details text-muted small">
                        <p class="mb-1">
                            <i class="bi bi-calendar me-2"></i>
                            ${dateStr}${timeStr ? ` at ${timeStr}` : ''}
                        </p>
                        ${rsvp.location ? `
                            <p class="mb-1">
                                <i class="bi bi-geo-alt me-2"></i>
                                ${this.escapeHtml(rsvp.location)}
                            </p>
                        ` : ''}
                        ${rsvp.branch ? `
                            <p class="mb-1">
                                <i class="bi bi-building me-2"></i>
                                ${this.escapeHtml(rsvp.branch)}
                            </p>
                        ` : ''}
                    </div>
                    
                    <div class="mt-2 text-muted small">
                        <i class="bi bi-info-circle me-1"></i>
                        Connect to internet to edit this RSVP.
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Format time string
     */
    formatTime(timeStr) {
        if (!timeStr) return '';
        const [hours, minutes] = timeStr.split(':');
        const h = parseInt(hours, 10);
        const suffix = h >= 12 ? 'PM' : 'AM';
        const displayHour = h === 0 ? 12 : h > 12 ? h - 12 : h;
        return `${displayHour}:${minutes} ${suffix}`;
    }

    /**
     * Escape HTML
     */
    escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    /**
     * Edit RSVP - load attendance modal
     */
    async editRsvp(event) {
        // Don't allow editing when offline
        if (!navigator.onLine) {
            alert('You need to be online to edit RSVPs.');
            return;
        }
        
        const button = event.currentTarget;
        const gatheringId = button.dataset.gatheringId;
        const attendanceId = button.dataset.attendanceId;
        
        this.currentGatheringId = gatheringId;
        
        // Show modal with loading state
        this.modalBodyTarget.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `;
        
        // Initialize and show modal
        if (!this.modal) {
            this.modal = new bootstrap.Modal(this.modalTarget);
        }
        this.modal.show();
        
        try {
            // Load attendance modal content
            const url = `/gatherings/attendance-modal/${gatheringId}?attendance_id=${attendanceId}`;
            const response = await fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (!response.ok) {
                throw new Error(`Failed to load: ${response.status}`);
            }
            
            const html = await response.text();
            this.modalBodyTarget.innerHTML = html;
            
            // Set up form handlers
            this.setupFormHandlers();
            
        } catch (error) {
            console.error('Error loading RSVP form:', error);
            this.modalBodyTarget.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Failed to load RSVP form. Please try again.
                </div>
            `;
        }
    }

    /**
     * Set up form submission handlers
     */
    setupFormHandlers() {
        // Main attendance form
        const form = this.modalBodyTarget.querySelector('#attendanceModalForm');
        if (form) {
            form.addEventListener('submit', (e) => this.handleFormSubmit(e));
        }
        
        // Delete form
        const deleteForm = this.modalBodyTarget.querySelector('form[id^="deleteAttendanceForm_"]');
        if (deleteForm) {
            deleteForm.addEventListener('submit', (e) => this.handleDeleteSubmit(e));
        }
    }

    /**
     * Handle main form submission
     */
    async handleFormSubmit(event) {
        event.preventDefault();
        
        const form = event.target;
        const formData = new FormData(form);
        const submitButton = form.querySelector('button[type="submit"]');
        
        // Disable submit button
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
        }
        
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (response.ok || response.redirected) {
                // Update RSVP cache
                if (this.currentGatheringId) {
                    rsvpCacheService.updateCachedRsvp(this.currentGatheringId, {
                        share_with_kingdom: formData.get('share_with_kingdom') === '1',
                        share_with_hosting_group: formData.get('share_with_hosting_group') === '1',
                        share_with_crown: formData.get('share_with_crown') === '1',
                        public_note: formData.get('public_note') || ''
                    }).catch(err => console.warn('[MyRsvps] Failed to update RSVP cache:', err));
                }
                
                // Success - close modal and reload page
                this.modal.hide();
                window.location.reload();
            } else {
                throw new Error(`Request failed: ${response.status}`);
            }
        } catch (error) {
            console.error('Error submitting form:', error);
            
            // Re-enable submit button
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.innerHTML = '<i class="bi bi-check-circle me-2"></i>Save Changes';
            }
            
            // Show error
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-danger mt-3';
            alertDiv.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>Failed to save. Please try again.';
            form.appendChild(alertDiv);
        }
    }

    /**
     * Handle delete form submission
     */
    async handleDeleteSubmit(event) {
        event.preventDefault();
        
        if (!confirm('Are you sure you want to cancel your RSVP?')) {
            return;
        }
        
        const form = event.target;
        const formData = new FormData(form);
        const submitButton = form.querySelector('button[type="submit"]');
        
        // Disable submit button
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Cancelling...';
        }
        
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (response.ok || response.redirected) {
                // Remove from cache
                if (this.currentGatheringId) {
                    rsvpCacheService.removeCachedRsvp(this.currentGatheringId)
                        .catch(err => console.warn('[MyRsvps] Failed to remove from RSVP cache:', err));
                }
                
                // Success - close modal and reload page
                this.modal.hide();
                window.location.reload();
            } else {
                throw new Error(`Request failed: ${response.status}`);
            }
        } catch (error) {
            console.error('Error deleting attendance:', error);
            
            // Re-enable submit button
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.innerHTML = '<i class="bi bi-trash me-2"></i>Cancel RSVP';
            }
            
            alert('Failed to cancel RSVP. Please try again.');
        }
    }

    /**
     * Handle modal hidden event
     */
    onModalHidden() {
        // Reset modal content
        if (this.hasModalBodyTarget) {
            this.modalBodyTarget.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            `;
        }
        this.currentGatheringId = null;
    }

    onDisconnect() {
        if (this.modal) {
            this.modal.dispose();
            this.modal = null;
        }
    }
}

// Register controller
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["my-rsvps"] = MyRsvpsController;
