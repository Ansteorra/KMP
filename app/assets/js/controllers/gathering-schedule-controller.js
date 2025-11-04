import { Controller } from "@hotwired/stimulus";

/**
 * Gathering Schedule Controller
 * 
 * Manages the gathering schedule interface including:
 * - Adding new scheduled activities via modal
 * - Editing existing scheduled activities via modal
 * - Deleting scheduled activities
 * - Dynamic form field updates based on activity selection
 */
class GatheringScheduleController extends Controller {
    static targets = [
        "scheduleList",
        "addModal",
        "editModal",
        "activitySelect",
        "isOtherCheckbox",
        "addForm",
        "editForm",
        "editActivitySelect",
        "editIsOtherCheckbox",
        "startDatetime",
        "endDatetime",
        "editStartDatetime",
        "editEndDatetime",
        "hasEndTimeCheckbox",
        "editHasEndTimeCheckbox",
        "endTimeContainer",
        "editEndTimeContainer"
    ]

    static values = {
        gatheringId: Number,
        gatheringStart: String,  // YYYY-MM-DD format
        gatheringEnd: String,    // YYYY-MM-DD format
        addUrl: String,
        editUrl: String,
        deleteUrl: String
    }

    /**
     * Initialize controller
     */
    connect() {
        console.log('Gathering schedule controller connected');
        this.setupDateTimeLimits();
    }

    /**
     * Setup min/max limits on datetime inputs based on gathering dates
     */
    setupDateTimeLimits() {
        // Validate that gathering dates are present and in correct format (YYYY-MM-DD)
        const datePattern = /^\d{4}-\d{2}-\d{2}$/;
        if (!this.gatheringStartValue || !this.gatheringEndValue || 
            !datePattern.test(this.gatheringStartValue) || 
            !datePattern.test(this.gatheringEndValue)) {
            console.warn('Invalid gathering dates - skipping datetime limits setup');
            return;
        }

        // Calculate min/max datetime strings
        const minDatetime = `${this.gatheringStartValue}T00:00`;
        const maxDatetime = `${this.gatheringEndValue}T23:59`;

        // Set limits on add form inputs
        if (this.hasStartDatetimeTarget) {
            this.startDatetimeTarget.min = minDatetime;
            this.startDatetimeTarget.max = maxDatetime;
            
            // Set default to start of gathering at 9:00 AM if empty
            if (!this.startDatetimeTarget.value) {
                this.startDatetimeTarget.value = `${this.gatheringStartValue}T09:00`;
            }
        }

        if (this.hasEndDatetimeTarget) {
            this.endDatetimeTarget.min = minDatetime;
            this.endDatetimeTarget.max = maxDatetime;
            
            // Don't set a default value - end time is optional
        }

        // Set limits on edit form inputs
        if (this.hasEditStartDatetimeTarget) {
            this.editStartDatetimeTarget.min = minDatetime;
            this.editStartDatetimeTarget.max = maxDatetime;
        }

        if (this.hasEditEndDatetimeTarget) {
            this.editEndDatetimeTarget.min = minDatetime;
            this.editEndDatetimeTarget.max = maxDatetime;
        }
    }

    /**
     * Reset add form when modal is opened
     */
    resetAddForm(event) {
        // Validate that gathering dates are present and in correct format (YYYY-MM-DD)
        const datePattern = /^\d{4}-\d{2}-\d{2}$/;
        if (!this.gatheringStartValue || !this.gatheringEndValue || 
            !datePattern.test(this.gatheringStartValue) || 
            !datePattern.test(this.gatheringEndValue)) {
            console.warn('Invalid gathering dates - skipping form reset defaults');
            return;
        }

        // Reset to defaults
        const minDatetime = `${this.gatheringStartValue}T00:00`;
        const maxDatetime = `${this.gatheringEndValue}T23:59`;
        
        if (this.hasStartDatetimeTarget) {
            this.startDatetimeTarget.value = `${this.gatheringStartValue}T09:00`;
        }
        
        if (this.hasEndDatetimeTarget) {
            // Don't set a default value - end time is optional
            this.endDatetimeTarget.value = '';
        }
    }

    /**
     * Handle activity select change - disable/enable based on "other" checkbox
     */
    handleActivityChange(event) {
        const isOther = this.isOtherCheckboxTarget.checked;
        this.activitySelectTarget.disabled = isOther;
        
        if (isOther) {
            this.activitySelectTarget.value = '';
        }
    }

    /**
     * Handle "other" checkbox change for add form
     */
    handleOtherChange(event) {
        const isOther = event.target.checked;
        this.activitySelectTarget.disabled = isOther;
        this.activitySelectTarget.required = !isOther;
        
        if (isOther) {
            this.activitySelectTarget.value = '';
        }
    }

    /**
     * Handle "other" checkbox change for edit form
     */
    handleEditOtherChange(event) {
        const isOther = event.target.checked;
        this.editActivitySelectTarget.disabled = isOther;
        this.editActivitySelectTarget.required = !isOther;
        
        if (isOther) {
            this.editActivitySelectTarget.value = '';
        }
    }

    /**
     * Toggle end time field visibility for add form
     */
    toggleEndTime(event) {
        const hasEndTime = event.target.checked;
        
        if (this.hasEndTimeContainerTarget) {
            this.endTimeContainerTarget.style.display = hasEndTime ? 'block' : 'none';
        }
        
        // Clear end time if unchecking
        if (!hasEndTime && this.hasEndDatetimeTarget) {
            this.endDatetimeTarget.value = '';
        } else {
            // If checking, set default end time to one hour after start time
            if (this.hasStartDatetimeTarget && this.startDatetimeTarget.value) {
                const startDate = new Date(this.startDatetimeTarget.value);
                startDate.setHours(startDate.getHours() + 1);
                const year = startDate.getFullYear();
                const month = String(startDate.getMonth() + 1).padStart(2, '0');
                const day = String(startDate.getDate()).padStart(2, '0');
                const hours = String(startDate.getHours()).padStart(2, '0');
                const minutes = String(startDate.getMinutes()).padStart(2, '0');
                this.endDatetimeTarget.value = `${year}-${month}-${day}T${hours}:${minutes}`;
            }
        }
    }

    /**
     * Toggle end time field visibility for edit form
     */
    toggleEditEndTime(event) {
        const hasEndTime = event.target.checked;
        
        if (this.hasEditEndTimeContainerTarget) {
            this.editEndTimeContainerTarget.style.display = hasEndTime ? 'block' : 'none';
        }
        
        // Clear end time if unchecking
        if (!hasEndTime && this.hasEditEndDatetimeTarget) {
            this.editEndDatetimeTarget.value = '';
        } else {
            // If checking, set default end time to one hour after start time
            if (this.hasEditStartDatetimeTarget && this.editStartDatetimeTarget.value) {
                const startDate = new Date(this.editStartDatetimeTarget.value);
                startDate.setHours(startDate.getHours() + 1);
                const year = startDate.getFullYear();
                const month = String(startDate.getMonth() + 1).padStart(2, '0');
                const day = String(startDate.getDate()).padStart(2, '0');
                const hours = String(startDate.getHours()).padStart(2, '0');
                const minutes = String(startDate.getMinutes()).padStart(2, '0');
                this.editEndDatetimeTarget.value = `${year}-${month}-${day}T${hours}:${minutes}`;
            }
        }
    }

    /**
     * Open edit modal and populate with activity data
     */
    openEditModal(event) {
        event.preventDefault();
        const button = event.currentTarget;
        
        // Get data attributes from the button
        const activityId = button.dataset.activityId;
        const activityName = button.dataset.activityName;
        const gatheringActivityId = button.dataset.gatheringActivityId;
        const startDatetime = button.dataset.startDatetime;
        const endDatetime = button.dataset.endDatetime;
        const displayTitle = button.dataset.displayTitle;
        const description = button.dataset.description;
        const preRegister = button.dataset.preRegister === 'true';
        const isOther = button.dataset.isOther === 'true';
        const hasEndTime = button.dataset.hasEndTime === 'true';
        
        // Populate form fields
        const form = this.editFormTarget;
        form.action = this.editUrlValue.replace('__ID__', activityId);
        
        form.querySelector('[name="gathering_activity_id"]').value = gatheringActivityId || '';
        form.querySelector('[name="start_datetime"]').value = startDatetime;
        form.querySelector('[name="end_datetime"]').value = endDatetime || '';
        form.querySelector('[name="display_title"]').value = displayTitle;
        form.querySelector('[name="description"]').value = description || '';
        
        // Use getElementById for checkboxes to avoid hidden input conflicts
        document.getElementById('edit-pre-register').checked = preRegister;
        document.getElementById('edit-is-other').checked = isOther;
        document.getElementById('edit-has-end-time').checked = hasEndTime;
        
        // Handle activity select state based on is_other
        const activitySelect = this.editActivitySelectTarget;
        activitySelect.disabled = isOther;
        activitySelect.required = !isOther;
        
        // Handle end time container visibility
        if (this.hasEditEndTimeContainerTarget) {
            this.editEndTimeContainerTarget.style.display = hasEndTime ? 'block' : 'none';
        }
        
        // Show the modal
        const modal = new bootstrap.Modal(this.editModalTarget);
        modal.show();
    }

    /**
     * Validate datetime is within gathering range
     */
    validateDatetimeRange(event) {
        const input = event.target;
        const value = input.value;
        
        if (!value) return;
        
        const minDatetime = `${this.gatheringStartValue}T00:00`;
        const maxDatetime = `${this.gatheringEndValue}T23:59`;
        
        const selectedDate = new Date(value);
        const minDate = new Date(minDatetime);
        const maxDate = new Date(maxDatetime);
        
        if (selectedDate < minDate || selectedDate > maxDate) {
            input.setCustomValidity(`Date must be between ${this.formatDate(minDate)} and ${this.formatDate(maxDate)}`);
            input.reportValidity();
        } else {
            input.setCustomValidity('');
        }
    }

    /**
     * Format date for display
     */
    formatDate(date) {
        return date.toLocaleDateString('en-US', { 
            month: 'short', 
            day: 'numeric', 
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit'
        });
    }

    /**
     * Normalize error response into a readable string
     * Handles errors that may be an array, object, string, or missing
     */
    normalizeErrors(result) {
        if (!result.errors) {
            return result.message || 'An error occurred';
        }

        // If errors is already an array, join it
        if (Array.isArray(result.errors)) {
            return result.errors.join(', ');
        }

        // If errors is a string, use it directly
        if (typeof result.errors === 'string') {
            return result.errors;
        }

        // If errors is an object, try to extract values
        if (typeof result.errors === 'object') {
            try {
                // Try to flatten nested arrays and join
                const errorValues = Object.values(result.errors).flat();
                if (errorValues.length > 0) {
                    return errorValues.join(', ');
                }
                // Fall back to JSON stringify for complex objects
                return JSON.stringify(result.errors);
            } catch (e) {
                console.error('Error parsing errors object:', e);
                return result.message || 'An error occurred';
            }
        }

        // Final fallback
        return result.message || 'An error occurred';
    }

    /**
     * Submit add form via AJAX
     */
    async submitAddForm(event) {
        event.preventDefault();
        
        const form = event.target;
        const formData = new FormData(form);
        
        try {
            const response = await fetch(this.addUrlValue, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Close modal
                const modal = bootstrap.Modal.getInstance(this.addModalTarget);
                modal.hide();
                
                // Reset form
                form.reset();
                
                // Show success message and reload page
                this.showFlashMessage('success', result.message);
                window.location.reload();
            } else {
                // Show error message
                const errorMsg = this.normalizeErrors(result);
                this.showFlashMessage('error', errorMsg);
            }
        } catch (error) {
            console.error('Error submitting form:', error);
            this.showFlashMessage('error', 'An error occurred while adding the scheduled activity.');
        }
    }

    /**
     * Submit edit form via AJAX
     */
    async submitEditForm(event) {
        event.preventDefault();
        
        const form = event.target;
        const formData = new FormData(form);
        
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Close modal
                const modal = bootstrap.Modal.getInstance(this.editModalTarget);
                modal.hide();
                
                // Show success message and reload page
                this.showFlashMessage('success', result.message);
                window.location.reload();
            } else {
                // Show error message
                const errorMsg = this.normalizeErrors(result);
                this.showFlashMessage('error', errorMsg);
            }
        } catch (error) {
            console.error('Error submitting form:', error);
            this.showFlashMessage('error', 'An error occurred while updating the scheduled activity.');
        }
    }

    /**
     * Show flash message
     */
    showFlashMessage(type, message) {
        // Create flash message element
        const flashContainer = document.querySelector('.flash-messages') || this.createFlashContainer();
        
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        const flashDiv = document.createElement('div');
        flashDiv.className = `alert ${alertClass} alert-dismissible fade show`;
        flashDiv.role = 'alert';
        
        // Safely add message text using textContent (prevents XSS)
        const messageText = document.createTextNode(message);
        flashDiv.appendChild(messageText);
        
        // Create close button separately with proper attributes
        const closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'btn-close';
        closeButton.setAttribute('data-bs-dismiss', 'alert');
        closeButton.setAttribute('aria-label', 'Close');
        flashDiv.appendChild(closeButton);
        
        flashContainer.appendChild(flashDiv);
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            flashDiv.remove();
        }, 5000);
    }

    /**
     * Create flash message container if it doesn't exist
     */
    createFlashContainer() {
        const container = document.createElement('div');
        container.className = 'flash-messages container mt-3';
        document.querySelector('main').prepend(container);
        return container;
    }
}

// Add to global controllers registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["gathering-schedule"] = GatheringScheduleController;
