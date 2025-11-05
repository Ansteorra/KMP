import { Controller } from "@hotwired/stimulus";

/**
 * Timezone Input Controller
 * 
 * Automatically handles timezone conversion for datetime-local inputs.
 * Converts UTC values from server to user's local timezone for display/editing,
 * and converts back to UTC before form submission.
 * 
 * ## Usage
 * 
 * ### Basic Auto-Conversion
 * ```html
 * <form data-controller="timezone-input">
 *   <input type="datetime-local" 
 *          name="start_date"
 *          data-timezone-input-target="datetimeInput"
 *          data-utc-value="2025-03-15T14:30:00Z">
 * </form>
 * ```
 * 
 * ### Custom Timezone
 * ```html
 * <form data-controller="timezone-input" data-timezone-input-timezone-value="America/New_York">
 *   <input type="datetime-local" 
 *          name="start_date"
 *          data-timezone-input-target="datetimeInput"
 *          data-utc-value="2025-03-15T14:30:00Z">
 * </form>
 * ```
 * 
 * ### With Timezone Notice
 * ```html
 * <form data-controller="timezone-input">
 *   <input type="datetime-local" 
 *          name="start_date"
 *          data-timezone-input-target="datetimeInput"
 *          data-utc-value="2025-03-15T14:30:00Z">
 *   
 *   <!-- Timezone notice will be auto-populated -->
 *   <small data-timezone-input-target="notice" class="text-muted"></small>
 * </form>
 * ```
 * 
 * ## Features
 * - Automatic timezone detection from browser
 * - Converts UTC to local time on page load
 * - Converts local time back to UTC on form submit
 * - Shows timezone notice to user
 * - Handles multiple datetime inputs in one form
 * - Preserves original values for form reset
 * 
 * ## Targets
 * - `datetimeInput` - datetime-local inputs to convert (required)
 * - `notice` - Elements to populate with timezone info (optional)
 * 
 * ## Values
 * - `timezone` - Override timezone (default: browser detected)
 * - `showNotice` - Show timezone notice (default: true)
 * 
 * ## Actions
 * - `submit` - Converts all inputs to UTC before form submission
 * - `reset` - Restores original local values on form reset
 */
class TimezoneInputController extends Controller {
    static targets = ["datetimeInput", "notice"]
    
    static values = {
        timezone: String,
        showNotice: { type: Boolean, default: true }
    }

    /**
     * Initialize controller and convert UTC values to local time
     */
    connect() {
        // Get or detect timezone
        this.timezone = this.hasTimezoneValue ? 
            this.timezoneValue : 
            KMP_Timezone.detectTimezone();

        // Convert all UTC values to local time for display
        this.convertUtcToLocal();

        // Show timezone notice if requested
        if (this.showNoticeValue && this.hasNoticeTarget) {
            this.updateNotice();
        }

        // Attach submit handler
        this.element.addEventListener('submit', this.handleSubmit.bind(this));
        
        // Attach reset handler
        this.element.addEventListener('reset', this.handleReset.bind(this));
    }

    /**
     * Convert UTC values to local timezone for input display
     */
    convertUtcToLocal() {
        this.datetimeInputTargets.forEach(input => {
            const utcValue = input.dataset.utcValue;
            
            if (utcValue) {
                // Convert UTC to local time for input
                const localValue = KMP_Timezone.toLocalInput(utcValue, this.timezone);
                input.value = localValue;
                
                // Store original UTC value for reference
                input.dataset.originalUtc = utcValue;
                
                // Store converted local value for reset
                input.dataset.localValue = localValue;
            }
        });
    }

    /**
     * Update timezone notice elements
     */
    updateNotice() {
        const abbr = KMP_Timezone.getAbbreviation(this.timezone);
        const noticeText = `Times shown in ${this.timezone} (${abbr})`;
        
        this.noticeTargets.forEach(notice => {
            notice.innerHTML = `<i class="bi bi-clock"></i> ${noticeText}`;
        });
    }

    /**
     * Handle form submission - convert local times to UTC
     * 
     * @param {Event} event - Submit event
     */
    handleSubmit(event) {
        this.datetimeInputTargets.forEach(input => {
            if (input.value) {
                // Convert local time to UTC
                const utcValue = KMP_Timezone.toUTC(input.value, this.timezone);
                
                // Store original local value for potential reset
                input.dataset.submittedLocal = input.value;
                
                // Create hidden input with UTC value
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = input.name;
                hiddenInput.value = utcValue;
                hiddenInput.dataset.timezoneConverted = 'true';
                
                // Disable original input so it doesn't submit
                input.disabled = true;
                
                // Add hidden input to form
                this.element.appendChild(hiddenInput);
            }
        });
    }

    /**
     * Handle form reset - restore local values
     * 
     * @param {Event} event - Reset event
     */
    handleReset(event) {
        // Remove any hidden UTC inputs
        const hiddenInputs = this.element.querySelectorAll('input[data-timezone-converted="true"]');
        hiddenInputs.forEach(input => input.remove());

        // Re-enable and restore datetime inputs
        this.datetimeInputTargets.forEach(input => {
            input.disabled = false;
            
            // Restore to original local value
            if (input.dataset.localValue) {
                setTimeout(() => {
                    input.value = input.dataset.localValue;
                }, 0);
            }
        });
    }

    /**
     * Manually update timezone (called if timezone changes)
     * 
     * @param {string} newTimezone - New IANA timezone identifier
     */
    updateTimezone(newTimezone) {
        this.timezone = newTimezone;
        
        // Re-convert all values with new timezone
        this.convertUtcToLocal();
        
        // Update notice if shown
        if (this.showNoticeValue && this.hasNoticeTarget) {
            this.updateNotice();
        }
    }

    /**
     * Get current timezone being used
     * 
     * @returns {string} Current timezone identifier
     */
    getTimezone() {
        return this.timezone;
    }

    /**
     * Cleanup on disconnect
     */
    disconnect() {
        // Remove event listeners
        this.element.removeEventListener('submit', this.handleSubmit.bind(this));
        this.element.removeEventListener('reset', this.handleReset.bind(this));
    }
}

// Add to global controllers registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["timezone-input"] = TimezoneInputController;
