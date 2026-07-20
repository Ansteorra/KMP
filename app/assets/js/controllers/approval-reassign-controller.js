import { Controller } from "@hotwired/stimulus"

/**
 * Approval Reassign Controller
 *
 * Handles the admin reassignment modal form logic.
 * Loads the eligible approver list for the selected approval
 * and submits the reassignment with an optional reason.
 */
class ApprovalReassignController extends Controller {
    static targets = ["approvalId", "reason", "submitBtn"]
    static values = {
        approvalId: Number,
        eligibleUrl: String,
    }

    connect() {
        this._updateSubmitState()
    }

    configure(data) {
        this.approvalIdValue = data.id || 0
        this.eligibleUrlValue = data.eligibleUrl || ''

        // Set hidden field
        if (this.hasApprovalIdTarget) {
            this.approvalIdTarget.value = this.approvalIdValue
        }

        // Reset reason
        if (this.hasReasonTarget) {
            this.reasonTarget.value = ''
        }

        // Update autocomplete URL
        const acEl = this.element.querySelector('[data-controller="ac"]')
        if (acEl && this.eligibleUrlValue) {
            acEl.setAttribute('data-ac-url-value', this.eligibleUrlValue)
            // Clear previous selection
            const hiddenInput = acEl.querySelector('[data-ac-target="hidden"]')
            if (hiddenInput) hiddenInput.value = ''
            const textInput = acEl.querySelector('[data-ac-target="input"]')
            if (textInput) textInput.value = ''
        }

        this._updateSubmitState()
    }

    onApproverChange() {
        this._updateSubmitState()
    }

    _updateSubmitState() {
        if (!this.hasSubmitBtnTarget) return
        const acEl = this.element.querySelector('[data-controller="ac"]')
        const hiddenInput = acEl?.querySelector('[data-ac-target="hidden"]')
        this.submitBtnTarget.disabled = !hiddenInput?.value
    }
}

if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["approval-reassign"] = ApprovalReassignController
