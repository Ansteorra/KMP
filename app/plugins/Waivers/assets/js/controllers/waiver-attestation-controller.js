import { Controller } from "@hotwired/stimulus"

/**
 * Waiver Attestation Controller
 * 
 * Manages the modal for attesting that a waiver is not needed for a specific
 * activity/waiver type combination. Handles:
 * - Modal display with configurable reasons
 * - Reason selection
 * - Form submission via AJAX
 * - Success/error feedback
 * 
 * **Data Values**:
 * - activityId: Gathering activity ID
 * - waiverTypeId: Waiver type ID
 * - gatheringId: Gathering ID
 * - reasons: JSON array of exemption reasons
 * 
 * **Targets**:
 * - modal: The Bootstrap modal element
 * - reasonList: Container for reason radio buttons
 * - notes: Textarea for optional notes
 * - submitBtn: Submit button
 * - error: Error message container
 * - success: Success message container
 * 
 * **Actions**:
 * - showModal: Displays the modal with reasons
 * - submitAttestation: Submits the attestation form
 * 
 * @see GatheringWaiversController.attest() Server endpoint
 */
class WaiverAttestationController extends Controller {
    static targets = ["modal", "reasonList", "notes", "submitBtn", "error", "success"]
    static values = {
        activityId: Number,
        waiverTypeId: Number,
        gatheringId: Number,
        reasons: Array
    }

    /**
     * Initialize Bootstrap modal instance
     */
    connect() {
        if (this.hasModalTarget) {
            this.modalInstance = new bootstrap.Modal(this.modalTarget)
        }
    }

    /**
     * Show the modal and populate with reasons
     * Called when user clicks "Attest Not Needed" button
     */
    showModal(event) {
        event.preventDefault()
        
        // Get values from the button that was clicked
        const btn = event.currentTarget
        this.activityIdValue = parseInt(btn.dataset.activityId)
        this.waiverTypeIdValue = parseInt(btn.dataset.waiverTypeId)
        this.gatheringIdValue = parseInt(btn.dataset.gatheringId)
        
        try {
            this.reasonsValue = JSON.parse(btn.dataset.reasons)
        } catch (e) {
            console.error('Failed to parse reasons:', e)
            this.reasonsValue = []
        }

        // Populate reasons
        this.populateReasons()

        // Clear previous state
        this.clearMessages()
        if (this.hasNotesTarget) {
            this.notesTarget.value = ''
        }

        // Show modal
        if (this.modalInstance) {
            this.modalInstance.show()
        }
    }

    /**
     * Populate the reason selection radio buttons
     */
    populateReasons() {
        if (!this.hasReasonListTarget) return

        const reasons = this.reasonsValue || []
        
        if (reasons.length === 0) {
            this.reasonListTarget.innerHTML = `
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    No exemption reasons have been configured for this waiver type.
                </div>
            `
            if (this.hasSubmitBtnTarget) {
                this.submitBtnTarget.disabled = true
            }
            return
        }

        // Build radio buttons
        let html = '<div class="list-group">'
        reasons.forEach((reason, index) => {
            const id = `reason_${index}`
            html += `
                <label class="list-group-item list-group-item-action">
                    <input class="form-check-input me-2" type="radio" name="attestation_reason" 
                           id="${id}" value="${this.escapeHtml(reason)}">
                    ${this.escapeHtml(reason)}
                </label>
            `
        })
        html += '</div>'

        this.reasonListTarget.innerHTML = html

        if (this.hasSubmitBtnTarget) {
            this.submitBtnTarget.disabled = false
        }
    }

    /**
     * Submit the attestation form
     */
    async submitAttestation(event) {
        event.preventDefault()

        // Get selected reason
        const selectedReason = this.reasonListTarget.querySelector('input[name="attestation_reason"]:checked')
        
        if (!selectedReason) {
            this.showError('Please select a reason for the exemption.')
            return
        }

        const reason = selectedReason.value
        const notes = this.hasNotesTarget ? this.notesTarget.value : ''

        // Disable submit button
        if (this.hasSubmitBtnTarget) {
            this.submitBtnTarget.disabled = true
            this.submitBtnTarget.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Submitting...'
        }

        this.clearMessages()

        try {
            // Submit via AJAX
            const response = await fetch('/waivers/gathering-waivers/attest', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': this.getCsrfToken()
                },
                body: JSON.stringify({
                    gathering_activity_id: this.activityIdValue,
                    waiver_type_id: this.waiverTypeIdValue,
                    gathering_id: this.gatheringIdValue,
                    reason: reason,
                    notes: notes
                })
            })

            const data = await response.json()

            if (response.ok && data.success) {
                this.showSuccess(data.message || 'Attestation recorded successfully.')
                
                // Reload page after short delay
                setTimeout(() => {
                    window.location.reload()
                }, 1500)
            } else {
                this.showError(data.message || 'Failed to record attestation.')
                this.resetSubmitButton()
            }
        } catch (error) {
            console.error('Error submitting attestation:', error)
            this.showError('An error occurred while submitting the attestation.')
            this.resetSubmitButton()
        }
    }

    /**
     * Show error message
     */
    showError(message) {
        if (this.hasErrorTarget) {
            this.errorTarget.textContent = message
            this.errorTarget.classList.remove('d-none')
        }
        if (this.hasSuccessTarget) {
            this.successTarget.classList.add('d-none')
        }
    }

    /**
     * Show success message
     */
    showSuccess(message) {
        if (this.hasSuccessTarget) {
            this.successTarget.textContent = message
            this.successTarget.classList.remove('d-none')
        }
        if (this.hasErrorTarget) {
            this.errorTarget.classList.add('d-none')
        }
    }

    /**
     * Clear all messages
     */
    clearMessages() {
        if (this.hasErrorTarget) {
            this.errorTarget.classList.add('d-none')
        }
        if (this.hasSuccessTarget) {
            this.successTarget.classList.add('d-none')
        }
    }

    /**
     * Reset submit button to original state
     */
    resetSubmitButton() {
        if (this.hasSubmitBtnTarget) {
            this.submitBtnTarget.disabled = false
            this.submitBtnTarget.innerHTML = '<i class="bi bi-shield-check"></i> Submit Attestation'
        }
    }

    /**
     * Get CSRF token from meta tag
     */
    getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]')
        return meta ? meta.getAttribute('content') : ''
    }

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        const div = document.createElement('div')
        div.textContent = text
        return div.innerHTML
    }
}

// Register controller
if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["waivers-waiver-attestation"] = WaiverAttestationController
