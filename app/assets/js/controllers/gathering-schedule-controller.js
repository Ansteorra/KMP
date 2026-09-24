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
        "editStartDatetime",
        "startDate",
        "startTime",
        "duration",
        "editStartDate",
        "editStartTime",
        "editDuration"
    ]

    static values = {
        gatheringId: Number,
        gatheringStart: String,  // YYYY-MM-DD format
        gatheringEnd: String,    // YYYY-MM-DD format
        addUrl: String,
        editUrl: String,
        deleteUrl: String
    }

    /** Set date bounds without letting the browser timezone alter gathering wall times. */
    setupDateTimeLimits() {
        for (const input of [...this.startDateTargets, ...this.editStartDateTargets]) {
            input.min = this.gatheringStartValue.slice(0, 10);
            input.max = this.gatheringEndValue.slice(0, 10);
        }
    }

    /** Default new activities to the first quarter-hour within the gathering. */
    resetAddForm() {
        this.setupDateTimeLimits();
        const start = new Date(`${this.gatheringStartValue}Z`);
        if (Number.isNaN(start.getTime())) return;
        start.setUTCMinutes(Math.ceil(start.getUTCMinutes() / 15) * 15, 0, 0);
        const value = start.toISOString().slice(0, 16);
        this.startDateTarget.value = value.slice(0, 10);
        this.startTimeTarget.value = value.slice(11);
        this.durationTarget.value = '60';
        this.updateTiming(false);
    }

    /** Keep the submitted local datetime in sync with the labeled date/time controls. */
    timingChanged(event) {
        this.updateTiming(event.target.form === this.editFormTarget);
    }

    /** Validate gathering boundaries on a visible control so native errors are reachable. */
    updateTiming(edit) {
        const date = edit ? this.editStartDateTarget : this.startDateTarget;
        const time = edit ? this.editStartTimeTarget : this.startTimeTarget;
        const datetime = edit ? this.editStartDatetimeTarget : this.startDatetimeTarget;
        datetime.value = date.value && time.value ? `${date.value}T${time.value}` : '';
        const outside = datetime.value && (datetime.value < this.gatheringStartValue ||
            datetime.value > this.gatheringEndValue);
        time.setCustomValidity(outside ? 'Start time must fall within the gathering dates and times.' : '');
    }

    /** Preserve saved times without adding off-quarter choices to new activities. */
    setEditTiming(start, end, hasEndTime, duration = null) {
        this.editStartDateTarget.value = start.slice(0, 10);
        const time = start.slice(11, 16);
        this.editStartTimeTarget.querySelectorAll('[data-existing]').forEach(option => option.remove());
        if (!Array.from(this.editStartTimeTarget.options).some(option => option.value === time)) {
            const option = new Option(`${time} (current)`, time);
            option.dataset.existing = 'true';
            this.editStartTimeTarget.add(option);
        }
        this.editStartTimeTarget.value = time;
        this.editDurationTarget.querySelectorAll('[data-existing]').forEach(option => option.remove());
        // Preserve the exact stored end, including across daylight-saving transitions.
        const standardDuration = Array.from(this.editDurationTarget.options)
            .some(option => option.value === duration);
        if (hasEndTime && standardDuration) {
            this.editDurationTarget.value = duration;
        } else if (hasEndTime && end) {
            const option = new Option(`Keep current end: ${end.replace('T', ' ')}`, 'existing');
            option.dataset.existing = 'true';
            this.editDurationTarget.add(option);
            this.editDurationTarget.value = 'existing';
        } else {
            this.editDurationTarget.value = 'other';
        }
        this.updateTiming(true);
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
     * Open edit modal and populate with activity data
     */
    openEditModal(event) {
        event.preventDefault();
        
        // Setup datetime limits when modal opens (values are guaranteed to be available now)
        this.setupDateTimeLimits();
        
        const button = event.currentTarget;
        this.editTrigger = button;
        
        // Get data attributes from the button
        const activityId = button.dataset.activityId;
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
        form.querySelector('[name="display_title"]').value = displayTitle;
        form.querySelector('[name="description"]').value = description || '';
        
        // Use getElementById for checkboxes to avoid hidden input conflicts
        document.getElementById('edit-pre-register').checked = preRegister;
        document.getElementById('edit-is-other').checked = isOther;
        
        // Handle activity select state based on is_other
        const activitySelect = this.editActivitySelectTarget;
        activitySelect.disabled = isOther;
        activitySelect.required = !isOther;
        
        this.setEditTiming(startDatetime, endDatetime, hasEndTime, button.dataset.durationMinutes);

        // Show the modal
        const modal = bootstrap.Modal.getOrCreateInstance(this.editModalTarget);
        modal.show();
    }

    /** Return keyboard focus to the schedule entry after closing its editor. */
    restoreEditFocus() {
        if (this.editTrigger?.isConnected) this.editTrigger.focus();
        this.editTrigger = null;
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
        this.updateTiming(false);
        if (!form.reportValidity()) return;
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
        this.updateTiming(true);
        if (!form.reportValidity()) return;
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
        const flashContainer = this.element.querySelector('.modal.show .modal-body') ||
            document.querySelector('.flash-messages') || this.createFlashContainer();
        
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
        
        flashContainer.prepend(flashDiv);
        flashDiv.scrollIntoView?.({ block: 'nearest' });
        
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
        const main = document.querySelector('main') || document.body;
        main.prepend(container);
        return container;
    }
}

// Add to global controllers registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["gathering-schedule"] = GatheringScheduleController;
