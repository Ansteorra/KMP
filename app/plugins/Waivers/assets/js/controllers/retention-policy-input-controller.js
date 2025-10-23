import { Controller } from "@hotwired/stimulus"

/**
 * Retention Policy Input Controller
 * 
 * Provides a structured interface for retention policy configuration with real-time preview.
 * Replaces simple JSON textarea with user-friendly inputs (years, months, days, anchor).
 * 
 * Targets:
 * - anchorSelect: The anchor point selection (gathering_end_date, upload_date, permanent)
 * - yearsInput: Years input field
 * - monthsInput: Months input field
 * - daysInput: Days input field
 * - durationSection: Container for duration inputs (hidden when anchor=permanent)
 * - preview: Preview text showing formatted policy
 * - hiddenInput: Hidden input that stores the JSON value for form submission
 * 
 * Actions:
 * - updatePreview: Updates preview text and hidden JSON field when any input changes
 */
class RetentionPolicyInputController extends Controller {
    static targets = [
        "anchorSelect",
        "yearsInput",
        "monthsInput",
        "daysInput",
        "durationSection",
        "preview",
        "hiddenInput"
    ]

    /**
     * Initialize controller
     */
    connect() {
        // Initialize preview on load
        this.updatePreview()
    }

    /**
     * Update preview text and hidden JSON field
     * Called whenever any input changes
     */
    updatePreview() {
        const anchor = this.anchorSelectTarget.value
        const years = parseInt(this.yearsInputTarget.value) || 0
        const months = parseInt(this.monthsInputTarget.value) || 0
        const days = parseInt(this.daysInputTarget.value) || 0

        // Show/hide duration section based on anchor
        if (anchor === 'permanent') {
            this.durationSectionTarget.style.display = 'none'
        } else {
            this.durationSectionTarget.style.display = 'block'
        }

        // Build JSON structure
        let policy = {
            anchor: anchor
        }

        // Add duration if not permanent
        if (anchor !== 'permanent') {
            policy.duration = {}
            if (years > 0) policy.duration.years = years
            if (months > 0) policy.duration.months = months
            if (days > 0) policy.duration.days = days
        }

        // Update hidden input with JSON
        this.hiddenInputTarget.value = JSON.stringify(policy)

        // Update preview text
        this.previewTarget.textContent = this.formatPreviewText(anchor, years, months, days)
    }

    /**
     * Format preview text in human-readable format
     * 
     * @param {string} anchor - The anchor point
     * @param {number} years - Years duration
     * @param {number} months - Months duration
     * @param {number} days - Days duration
     * @returns {string} Formatted preview text
     */
    formatPreviewText(anchor, years, months, days) {
        // Handle permanent retention
        if (anchor === 'permanent') {
            return 'Permanent retention (never expires)'
        }

        // Build duration parts
        const parts = []
        if (years > 0) {
            parts.push(`${years} ${years === 1 ? 'year' : 'years'}`)
        }
        if (months > 0) {
            parts.push(`${months} ${months === 1 ? 'month' : 'months'}`)
        }
        if (days > 0) {
            parts.push(`${days} ${days === 1 ? 'day' : 'days'}`)
        }

        // If no duration specified, show warning
        if (parts.length === 0) {
            return '⚠️ No duration specified'
        }

        // Format anchor point text
        const anchorText = anchor === 'gathering_end_date' 
            ? 'from gathering end date' 
            : 'from upload date'

        return `${parts.join(', ')} ${anchorText}`
    }

    /**
     * Parse existing JSON value into form fields
     * Called when editing an existing waiver type
     * 
     * @param {string} jsonValue - JSON string from database
     */
    parseJson(jsonValue) {
        try {
            const policy = JSON.parse(jsonValue)
            
            // Set anchor
            if (policy.anchor) {
                this.anchorSelectTarget.value = policy.anchor
            }

            // Set duration values
            if (policy.duration) {
                this.yearsInputTarget.value = policy.duration.years || 0
                this.monthsInputTarget.value = policy.duration.months || 0
                this.daysInputTarget.value = policy.duration.days || 0
            }

            // Update preview
            this.updatePreview()
        } catch (e) {
            console.error('Failed to parse retention policy JSON:', e)
            this.previewTarget.textContent = '⚠️ Invalid JSON format'
        }
    }

    /**
     * Validate inputs before form submission
     * 
     * @returns {boolean} True if valid, false otherwise
     */
    validate() {
        const anchor = this.anchorSelectTarget.value
        
        // Permanent is always valid
        if (anchor === 'permanent') {
            return true
        }

        // Check that at least one duration value is specified
        const years = parseInt(this.yearsInputTarget.value) || 0
        const months = parseInt(this.monthsInputTarget.value) || 0
        const days = parseInt(this.daysInputTarget.value) || 0

        if (years === 0 && months === 0 && days === 0) {
            alert('Please specify at least one duration value (years, months, or days)')
            return false
        }

        return true
    }
}

// Add to global controllers registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["retention-policy-input"] = RetentionPolicyInputController;
