import MobileControllerBase from "./mobile-controller-base";
import rsvpCacheService from "../services/rsvp-cache-service.js";

/**
 * My RSVPs Controller
 * 
 * Handles RSVP editing using the attendance modal on the My RSVPs page.
 */
class MyRsvpsController extends MobileControllerBase {
    static targets = ["modal", "modalBody", "actionButtons", "upcomingList", "pastList", "pastEmptyState", "upcomingCount", "pastCount"];

    onConnect() {
        this.modal = null;
        this.currentGatheringId = null;
        
        // Initialize RSVP cache service
        rsvpCacheService.init().catch(err => {
            console.warn('[MyRsvps] Failed to init RSVP cache:', err);
        });
        
        // Filter out past events on the client side (safety net for timezone edge cases)
        this.filterPastEvents();
        
        // Update button states based on online status
        this.updateOnlineButtons();
        
        // Set up modal event listener
        if (this.hasModalTarget) {
            this.modalTarget.addEventListener('hidden.bs.modal', () => {
                this.onModalHidden();
            });
        }
    }

    /**
     * Filter out events that have ended based on client's local date.
     * Moves them to the past tab and updates badge counts.
     */
    filterPastEvents() {
        if (!this.hasUpcomingListTarget) return;
        
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const todayStr = today.toISOString().split('T')[0]; // YYYY-MM-DD
        
        const cards = this.upcomingListTarget.querySelectorAll('.mobile-event-card[data-end-date]');
        let movedCount = 0;
        
        cards.forEach(card => {
            const endDate = card.dataset.endDate;
            if (endDate && endDate < todayStr) {
                // This event has ended - move it to past tab
                movedCount++;
                console.log(`[MyRsvps] Moving past event to Past tab (end: ${endDate}, today: ${todayStr})`);
                
                // Transform the card for past display
                this.transformCardForPast(card);
                
                // Move to past list
                if (this.hasPastListTarget) {
                    // Remove empty state if present
                    if (this.hasPastEmptyStateTarget) {
                        this.pastEmptyStateTarget.remove();
                    }
                    // Insert at beginning of past list (most recent first)
                    this.pastListTarget.insertBefore(card, this.pastListTarget.firstChild);
                } else {
                    // No past list target, just hide it
                    card.style.display = 'none';
                }
            }
        });
        
        if (movedCount > 0) {
            console.log(`[MyRsvps] Moved ${movedCount} events from Upcoming to Past`);
            
            // Update badge counts
            this.updateBadgeCounts();
            
            // Check if all upcoming events are now gone
            const remainingUpcoming = this.upcomingListTarget.querySelectorAll('.mobile-event-card');
            if (remainingUpcoming.length === 0) {
                // Show empty state message
                this.upcomingListTarget.innerHTML = `
                    <div class="card empty-state-card">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-calendar-check d-block fs-1 mb-3" style="color: var(--section-rsvps);"></i>
                            <h3 class="h5 mb-2">No Upcoming RSVPs</h3>
                            <p class="text-muted mb-4">
                                You haven't RSVPed to any upcoming gatherings yet.
                            </p>
                            <a href="/gatherings/mobile-calendar" class="btn btn-primary online-only-btn">
                                <i class="bi bi-calendar me-2"></i>Browse Calendar
                            </a>
                        </div>
                    </div>
                `;
            }
        }
    }

    /**
     * Transform a card from upcoming style to past style
     */
    transformCardForPast(card) {
        // Remove 'attending' class and add 'past' class
        card.classList.remove('attending');
        card.classList.add('past');
        
        // Change the success check icon to muted
        const checkIcon = card.querySelector('.bi-check-circle-fill.text-success');
        if (checkIcon) {
            checkIcon.classList.remove('bi-check-circle-fill', 'text-success');
            checkIcon.classList.add('bi-check-circle', 'text-muted');
        }
        
        // Remove action buttons row
        const actionsRow = card.querySelector('.mobile-event-actions-row');
        if (actionsRow) {
            actionsRow.remove();
        }
    }

    /**
     * Update the badge counts for both tabs
     */
    updateBadgeCounts() {
        // Count visible upcoming cards
        if (this.hasUpcomingCountTarget && this.hasUpcomingListTarget) {
            const upcomingCards = this.upcomingListTarget.querySelectorAll('.mobile-event-card:not(.past)');
            const count = upcomingCards.length;
            this.upcomingCountTarget.textContent = count;
            this.upcomingCountTarget.hidden = count === 0;
        }
        
        // Count past cards
        if (this.hasPastCountTarget && this.hasPastListTarget) {
            const pastCards = this.pastListTarget.querySelectorAll('.mobile-event-card');
            const count = pastCards.length;
            this.pastCountTarget.textContent = count;
            this.pastCountTarget.hidden = count === 0;
        }
    }

    /**
     * Called when connection state changes
     */
    onConnectionStateChanged(isOnline) {
        this.updateOnlineButtons();
    }

    /**
     * Update online-only buttons based on connection state
     */
    updateOnlineButtons() {
        const buttons = this.element.querySelectorAll('.online-only-btn');
        buttons.forEach(btn => {
            if (navigator.onLine) {
                btn.classList.remove('disabled');
                btn.style.opacity = '1';
                btn.style.pointerEvents = 'auto';
                btn.removeAttribute('aria-disabled');
            } else {
                btn.classList.add('disabled');
                btn.style.opacity = '0.5';
                btn.style.pointerEvents = 'none';
                btn.setAttribute('aria-disabled', 'true');
            }
        });
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
