import MobileControllerBase from "./mobile-controller-base";

/**
 * My RSVPs Controller
 * 
 * Handles RSVP editing using the attendance modal on the My RSVPs page.
 */
class MyRsvpsController extends MobileControllerBase {
    static targets = ["modal", "modalBody"];

    onConnect() {
        this.modal = null;
        this.currentGatheringId = null;
        
        // Set up modal event listener
        const modalEl = this.modalTarget;
        if (modalEl) {
            modalEl.addEventListener('hidden.bs.modal', () => {
                this.onModalHidden();
            });
        }
    }

    /**
     * Edit RSVP - load attendance modal
     */
    async editRsvp(event) {
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
        this.modalBodyTarget.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `;
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
