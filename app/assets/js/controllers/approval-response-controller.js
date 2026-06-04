import { Controller } from "@hotwired/stimulus"

/**
 * Approval Response Controller
 *
 * Handles the workflow approval response modal form logic.
 * Shows/hides the "Pick Next Approver" section based on decision
 * and serialPickNext configuration.
 */
class ApprovalResponseController extends Controller {
    static targets = ["decision", "decisionSection", "decisionLegend", "decisionOptions", "nextApproverSection", "nextApproverInput", "submitBtn", "comment", "commentRequiredHint", "commentWarning", "commentWarningText", "infoText"]
    static values = {
        serialPickNext: Boolean,
        requiredCount: Number,
        approvedCount: Number,
        approvalId: Number,
        eligibleUrl: String,
    }

    connect() {
        this._updateVisibility()
    }

    onDecisionChange(event) {
        this._updateVisibility()
    }

    /**
     * Called when the modal is shown with approval data.
     */
    configure(approvalData) {
        this.approvalIdValue = approvalData.id || 0
        this.serialPickNextValue = approvalData.serialPickNext || false
        this.requiredCountValue = approvalData.requiredCount || 1
        this.approvedCountValue = approvalData.approvedCount || 0
        this.eligibleUrlValue = approvalData.eligibleUrl || ''
        this._commentWarning = approvalData.commentWarning || ''
        this._requiresComment = approvalData.requiresComment || false
        this._feedbackResponse = approvalData.feedbackResponse || false
        this._decisionOptions = Array.isArray(approvalData.decisionOptions) ? approvalData.decisionOptions : []
        this._decisionPromptLabel = approvalData.decisionPromptLabel || 'Decision'
        this._approveLabel = approvalData.approveLabel || 'Approve'
        this._hideReject = approvalData.hideReject || false
        this._renderDecisionOptions()

        // Reset form
        this.decisionTargets.forEach(el => el.checked = false)
        if (approvalData.defaultDecision) {
            const defaultDecision = this.decisionTargets.find(el => el.value === approvalData.defaultDecision)
            if (defaultDecision) defaultDecision.checked = true
        }
        if (this.hasCommentTarget) this.commentTarget.value = ''
        if (this.hasNextApproverInputTarget) {
            // Clear the autocomplete widget
            const acEl = this.nextApproverInputTarget.closest('[data-controller="ac"]')
            if (acEl && acEl.value !== undefined) {
                acEl.value = ''
            }
        }

        // Show/hide comment warning from workflow config
        if (this.hasCommentWarningTarget) {
            if (this._commentWarning) {
                this.commentWarningTextTarget.textContent = this._commentWarning
                this.commentWarningTarget.hidden = false
            } else {
                this.commentWarningTarget.hidden = true
            }
        }

        // Update info text
        if (this.hasInfoTextTarget) {
            const remaining = this.requiredCountValue - this.approvedCountValue - 1
            if (remaining > 0 && this.serialPickNextValue) {
                this.infoTextTarget.textContent = `This approval requires ${remaining} more approver(s). Select who should review next.`
                this.infoTextTarget.hidden = false
            } else {
                this.infoTextTarget.hidden = true
            }
        }

        // Update the autocomplete URL if we have one
        if (this.eligibleUrlValue && this.hasNextApproverInputTarget) {
            const acEl = this.nextApproverInputTarget.closest('[data-controller="ac"]')
            if (acEl) {
                acEl.setAttribute('data-ac-url-value', this.eligibleUrlValue)
            }
        }

        this._updateVisibility()
    }

    _updateVisibility() {
        if (!this.hasNextApproverSectionTarget) return

        const decision = this._getSelectedDecision()
        const isApprove = decision === 'approve'
        const isReject = decision === 'reject'
        const needsMore = (this.approvedCountValue + 1) < this.requiredCountValue
        const showPicker = !this._feedbackResponse && isApprove && this.serialPickNextValue && needsMore

        if (this.hasDecisionSectionTarget) {
            this.decisionSectionTarget.hidden = this._feedbackResponse && this._decisionOptions.length === 0
        }
        if (this.hasDecisionLegendTarget) {
            this.decisionLegendTarget.textContent = this._decisionPromptLabel || 'Decision'
        }

        this.nextApproverSectionTarget.hidden = !showPicker

        // Comment is required for rejections and feedback responses.
        if (this.hasCommentTarget) {
            if (isReject || this._requiresComment) {
                this.commentTarget.setAttribute('required', 'required')
                this.commentTarget.placeholder = isReject
                    ? 'A reason is required when rejecting...'
                    : 'Feedback is required...'
            } else {
                this.commentTarget.removeAttribute('required')
                this.commentTarget.placeholder = 'Optional comment...'
            }
        }

        // Show/hide rejection reason label
        if (this.hasCommentRequiredHintTarget) {
            this.commentRequiredHintTarget.hidden = !(isReject || this._requiresComment)
        }

        // Update required state on hidden input
        if (this.hasNextApproverInputTarget) {
            const acEl = this.nextApproverInputTarget.closest('[data-controller="ac"]')
            if (acEl) {
                const hiddenInput = acEl.querySelector('[data-ac-target="hidden"]')
                if (hiddenInput) {
                    if (showPicker) {
                        hiddenInput.setAttribute('required', 'required')
                    } else {
                        hiddenInput.removeAttribute('required')
                    }
                }
            }
        }

        // Enable/disable submit
        if (this.hasSubmitBtnTarget) {
            this.submitBtnTarget.disabled = !decision
        }
    }

    _getSelectedDecision() {
        for (const el of this.decisionTargets) {
            if (el.checked) return el.value
        }
        return null
    }

    _renderDecisionOptions() {
        if (!this.hasDecisionOptionsTarget) return

        if (this._decisionOptions.length === 0) {
            const rejectHidden = this._hideReject ? ' hidden' : ''
            this.decisionOptionsTarget.innerHTML = `
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="decision" id="decisionApprove" value="approve"
                        data-approval-response-target="decision"
                        data-action="change->approval-response#onDecisionChange">
                    <label class="form-check-label text-success fw-semibold" for="decisionApprove">
                        <i class="bi bi-check-circle me-1"></i>${this._escHtml(this._approveLabel)}
                    </label>
                </div>
                <div class="form-check"${rejectHidden}>
                    <input class="form-check-input" type="radio" name="decision" id="decisionReject" value="reject"
                        data-approval-response-target="decision"
                        data-action="change->approval-response#onDecisionChange">
                    <label class="form-check-label text-danger fw-semibold" for="decisionReject">
                        <i class="bi bi-x-circle me-1"></i>Reject
                    </label>
                </div>`
            return
        }

        this.decisionOptionsTarget.innerHTML = this._decisionOptions.map((option, index) => {
            const id = `decisionCustom${index}`
            const value = this._escAttr(option.value)
            const label = this._escHtml(option.label)

            return `<div class="form-check">
                <input class="form-check-input" type="radio" name="decision" id="${id}" value="${value}"
                    data-approval-response-target="decision"
                    data-action="change->approval-response#onDecisionChange">
                <label class="form-check-label fw-semibold" for="${id}">${label}</label>
            </div>`
        }).join('')
    }

    _escHtml(value) {
        const div = document.createElement('div')
        div.textContent = String(value ?? '')
        return div.innerHTML
    }

    _escAttr(value) {
        return this._escHtml(value).replace(/"/g, '&quot;')
    }
}

if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["approval-response"] = ApprovalResponseController
