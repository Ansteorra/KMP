import { Controller } from "@hotwired/stimulus";

/**
 * Timezone Input Controller
 *
 * Automatically converts datetime-local inputs between user's local timezone
 * and UTC storage. Converts UTC values to local time on page load, and converts
 * back to UTC before form submission.
 *
 * See /docs/10.3.2-timezone-input-controller.md for complete documentation.
 *
 * @example
 * <form data-controller="timezone-input">
 *   <input type="datetime-local"
 *          name="start_date"
 *          data-timezone-input-target="datetimeInput"
 *          data-utc-value="2025-03-15T14:30:00Z">
 * </form>
 */
class TimezoneInputController extends Controller {
    static targets = ["datetimeInput", "notice"]

    static values = {
        timezone: String,
        showNotice: { type: Boolean, default: true }
    }

    /**
     * Initialize controller - detect timezone and convert UTC to local time
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

        // Cache bound event handlers for proper cleanup
        this._handleSubmit = this.handleSubmit.bind(this);
        this._handleReset = this.handleReset.bind(this);

        // Attach submit handler
        this.element.addEventListener('submit', this._handleSubmit);

        // Attach reset handler
        this.element.addEventListener('reset', this._handleReset);
    }

    /**
     * Convert UTC values to local timezone for input display
     * Stores original and local values in data attributes for reset
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
     * Handle form submission - convert local times to UTC and create hidden inputs
     * @param {Event} event
     */
    handleSubmit(event) {
        this.datetimeInputTargets.forEach(input => {
            if (input.value) {
                // Convert local time to UTC
                const utcValue = KMP_Timezone.toUTC(input.value, this.timezone);

                // Store original local value for potential reset
                input.dataset.submittedLocal = input.value;
                // Only proceed when conversion succeeds
                if (utcValue) {
                    delete input.dataset.timezoneConversionFailed;

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
                } else {
                    input.dataset.timezoneConversionFailed = 'true';
                }
            }
        });
    }

    /**
     * Handle form reset - remove hidden inputs and restore original local values
     * @param {Event} event
     */
    handleReset(event) {
        // Remove any hidden UTC inputs
        const hiddenInputs = this.element.querySelectorAll('input[data-timezone-converted="true"]');
        hiddenInputs.forEach(input => input.remove());

        // Re-enable and restore datetime inputs
        this.datetimeInputTargets.forEach(input => {
            input.disabled = false;
            delete input.dataset.timezoneConversionFailed;

            // Restore to original local value
            if (input.dataset.localValue) {
                setTimeout(() => {
                    input.value = input.dataset.localValue;
                }, 0);
            }
        });
    }

    /**
     * Manually update timezone and re-convert all values
     * @param {string} newTimezone - IANA timezone identifier
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
     * @returns {string} Current IANA timezone identifier
     */
    getTimezone() {
        return this.timezone;
    }

    /**
     * Cleanup on disconnect - remove event listeners and prevent memory leaks
     */
    disconnect() {
        // Remove event listeners using cached references
        if (this._handleSubmit) {
            this.element.removeEventListener('submit', this._handleSubmit);
            this._handleSubmit = null;
        }

        if (this._handleReset) {
            this.element.removeEventListener('reset', this._handleReset);
            this._handleReset = null;
        }
    }
}

// Add to global controllers registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["timezone-input"] = TimezoneInputController;
