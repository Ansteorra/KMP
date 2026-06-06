import MobileControllerBase from "./mobile-controller-base.js"

/**
 * Mobile Approvals Controller
 *
 * Touch-optimized approval workflow with card-based UI.
 * Fetches pending approvals, renders expandable cards,
 * and handles inline approve/reject with serial-pick-next support.
 */
class MobileApprovalsController extends MobileControllerBase {
    static targets = [
        "list", "loading", "empty", "error", "errorMessage",
        "countBadge", "refreshBtn", "toast", "toastBody"
    ]

    static values = {
        dataUrl: String,
        recordUrl: String,
        triageUrl: String,
        eligibleUrl: String,
        detailUrl: String,
    }

    initialize() {
        super.initialize()
        this._approvals = []
        this._expandedId = null
        this._submitting = false
    }

    onConnect() {
        if (this.online) {
            this.loadApprovals()
        }
    }

    onConnectionStateChanged(isOnline) {
        if (isOnline && this._approvals.length === 0) {
            this.loadApprovals()
        }
    }

    async refresh() {
        this.refreshBtnTarget.disabled = true
        await this.loadApprovals()
        this.refreshBtnTarget.disabled = false
    }

    async loadApprovals() {
        this._showState('loading')

        try {
            const response = await this.fetchWithRetry(this.dataUrlValue)
            const data = await response.json()
            this._approvals = data.approvals || []

            if (this._approvals.length === 0) {
                this._showState('empty')
            } else {
                this._showState('list')
                this._renderCards()
            }

            this._updateBadge()
        } catch (error) {
            console.error('Failed to load approvals:', error)
            if (this.hasErrorMessageTarget) {
                this.errorMessageTarget.textContent = error.message || 'Failed to load approvals.'
            }
            this._showState('error')
        }
    }

    // --- Card Rendering ---

    _renderCards() {
        const html = this._approvals.map(a => this._renderCard(a)).join('')
        this.listTarget.innerHTML = html
    }

    _renderCard(approval) {
        const hideProgress = approval.approverConfig?.hideProgress === true
            || approval.approverConfig?.feedbackResponse === true
        const pct = approval.progress.required > 0
            ? Math.round((approval.progress.approved / approval.progress.required) * 100)
            : 0
        const age = this._timeAgo(approval.modified)
        const progressHtml = hideProgress ? "" : `
                        <span class="approval-progress">
                            <span class="approval-progress-bar">
                                <span class="approval-progress-fill" style="width: ${pct}%"></span>
                            </span>
                            <small>${approval.progress.approved}/${approval.progress.required}</small>
                        </span>`

        return `
        <div class="card approval-card" data-approval-id="${approval.id}">
            <div class="approval-card-summary"
                 data-action="click->mobile-approvals#toggleCard keydown->mobile-approvals#toggleCardWithKeyboard"
                 role="button"
                 tabindex="0"
                 aria-expanded="false"
                 data-id="${approval.id}">
                <div class="approval-card-icon">
                    <i class="bi ${this._escHtml(approval.icon)}"></i>
                </div>
                <div class="approval-card-info">
                    <div class="approval-card-title">${this._escHtml(approval.title)}</div>
                    <div class="approval-card-meta">
                        <span><i class="bi bi-person me-1"></i>${this._escHtml(approval.requester)}</span>
                        ${approval.triage?.stateLabel ? `<span><i class="bi bi-journal-text me-1"></i>${this._escHtml(approval.triage.stateLabel)}</span>` : ''}
                        ${progressHtml}
                        <span><i class="bi bi-clock me-1"></i>${age}</span>
                    </div>
                </div>
                <div class="approval-card-chevron">
                    <i class="bi bi-chevron-right"></i>
                </div>
            </div>
        </div>`
    }

    toggleCard(event) {
        event.preventDefault()
        const id = parseInt(event.currentTarget.dataset.id)
        const card = this.listTarget.querySelector(`[data-approval-id="${id}"]`)
        if (!card) return

        if (this._expandedId === id) {
            this._collapseCard(card, id)
        } else {
            // Collapse previous
            if (this._expandedId !== null) {
                const prev = this.listTarget.querySelector(`[data-approval-id="${this._expandedId}"]`)
                if (prev) this._collapseCard(prev, this._expandedId)
            }

            this._expandCard(card, id)
        }
    }

    toggleCardWithKeyboard(event) {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return
        }
        this.toggleCard(event)
    }

    _collapseCard(card, id) {
        card.classList.remove('expanded')
        card.querySelector('.approval-card-summary')?.setAttribute('aria-expanded', 'false')
        const detail = card.querySelector('.approval-card-detail')
        if (detail) detail.remove()
        this._expandedId = null
    }

    async _expandCard(card, id) {
        card.classList.add('expanded')
        card.querySelector('.approval-card-summary')?.setAttribute('aria-expanded', 'true')
        this._expandedId = id

        const approval = this._approvals.find(a => a.id === id)
        if (!approval) return

        // Show loading detail
        const loadingHtml = `<div class="approval-card-detail">
            <div class="text-center py-3">
                <div class="spinner-border spinner-border-sm" style="color: var(--section-approvals);" role="status"></div>
                <small class="ms-2 text-muted">Loading details...</small>
            </div>
        </div>`
        card.insertAdjacentHTML('beforeend', loadingHtml)

        // Fetch full detail (response timeline)
        let responses = []
        try {
            const detailResp = await this.fetchWithRetry(`${this.detailUrlValue}/${id}`)
            const detailData = await detailResp.json()
            responses = detailData.responses || []
        } catch (e) {
            // Non-fatal — show card without response timeline
            console.warn('Could not load approval detail:', e)
        }

        // Replace loading with full detail
        const existingDetail = card.querySelector('.approval-card-detail')
        if (existingDetail) existingDetail.remove()

        // Only render if still expanded
        if (this._expandedId !== id) return

        const detailHtml = this._renderDetail(approval, responses)
        card.insertAdjacentHTML('beforeend', detailHtml)

        // Scroll card into view
        card.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
    }

    _renderDetail(approval, responses) {
        let html = '<div class="approval-card-detail">'

        // Description
        if (approval.description) {
            html += `<p class="mb-2" style="font-size: 0.85rem; color: var(--medieval-stone-dark, #555);">${this._escHtml(approval.description)}</p>`
        }

        // Context fields
        if (approval.fields && approval.fields.length > 0) {
            html += '<div class="approval-detail-fields">'
            for (const f of approval.fields) {
                html += `<div class="approval-detail-field">
                    <span class="approval-detail-label">${this._escHtml(f.label)}</span>
                    <span class="approval-detail-value">${this._escHtml(f.value)}</span>
                </div>`
            }
            html += '</div>'
        }

        // Response timeline
        if (responses.length > 0) {
            html += '<div class="approval-responses"><small class="fw-semibold text-muted d-block mb-1">Responses:</small>'
            for (const r of responses) {
                const icon = r.decision === 'approve'
                    ? '<i class="bi bi-check-circle-fill text-success approval-response-icon"></i>'
                    : '<i class="bi bi-x-circle-fill text-danger approval-response-icon"></i>'
                html += `<div class="approval-response-item">
                    ${icon}
                    <div>
                        <strong>${this._escHtml(r.memberName)}</strong>
                        <span class="text-muted"> — ${this._escHtml(r.respondedAt)}</span>
                        ${r.comment ? `<div class="text-muted fst-italic">"${this._escHtml(r.comment)}"</div>` : ''}
                    </div>
                </div>`
            }
            html += '</div>'
        }

        // Response form
        html += this._renderTriageForm(approval)
        html += this._renderResponseForm(approval)

        html += '</div>'
        return html
    }

    _renderTriageForm(approval) {
        const triage = approval.triage || { state: "new", note: "", states: { new: "New" } }
        const stateId = `mobile-approval-triage-state-${approval.id}`
        const noteId = `mobile-approval-triage-note-${approval.id}`
        const helpId = `mobile-approval-triage-help-${approval.id}`
        const statusId = `mobile-approval-triage-status-${approval.id}`
        const options = Object.entries(triage.states || {}).map(([value, label]) => {
            return `<option value="${this._escHtml(value)}"${value === triage.state ? " selected" : ""}>${this._escHtml(label)}</option>`
        }).join("")

        return `<form class="approval-triage-form mb-3"
                    data-mobile-triage-form="${approval.id}">
            <div class="mb-2">
                <label class="form-label fw-semibold" style="font-size: 0.85rem;" for="${stateId}">Private triage state</label>
                <select class="form-select form-select-sm" id="${stateId}" data-mobile-triage-state aria-describedby="${helpId}">
                    ${options}
                </select>
            </div>
            <div class="mb-2">
                <label class="form-label fw-semibold" style="font-size: 0.85rem;" for="${noteId}">Private note</label>
                <textarea class="form-control form-control-sm" id="${noteId}" data-mobile-triage-note rows="2" aria-describedby="${helpId}">${this._escHtml(triage.note || "")}</textarea>
                <div class="form-text" id="${helpId}">Only you can see this triage note. It does not submit an approval decision.</div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-action="click->mobile-approvals#saveTriage">
                    Save private triage
                </button>
                <span class="small text-muted" id="${statusId}" data-mobile-triage-status role="status" aria-live="polite"></span>
            </div>
        </form>`
    }

    _renderResponseForm(approval) {
        const cfg = approval.approverConfig || {}
        const commentWarning = cfg.commentWarning || ''
        const isFeedbackResponse = cfg.feedbackResponse === true
        const decisionOptions = Array.isArray(cfg.decisionOptions) ? cfg.decisionOptions : []
        const decisionPromptLabel = cfg.decisionPromptLabel || 'Decision'
        const requiresComment = cfg.requiresComment === true

        let html = `<div class="approval-response-form" data-approval-form-id="${approval.id}"${isFeedbackResponse && decisionOptions.length === 0 ? ' data-selected-decision="approve"' : ''}>`

        if (decisionOptions.length > 0) {
            html += `<fieldset class="mb-2">
                <legend class="form-label fw-semibold mb-1" style="font-size: 0.85rem;">${this._escHtml(decisionPromptLabel)}</legend>`
            for (const [index, option] of decisionOptions.entries()) {
                const inputId = `approval-${approval.id}-decision-${index}`
                html += `<div class="form-check">
                    <input class="form-check-input" type="radio" name="decision-${approval.id}" id="${inputId}"
                           value="${this._escHtml(option.value)}"
                           data-action="change->mobile-approvals#selectDecision"
                           data-id="${approval.id}" data-decision="${this._escHtml(option.value)}">
                    <label class="form-check-label" for="${inputId}">${this._escHtml(option.label)}</label>
                </div>`
            }
            html += '</fieldset>'
        } else if (!isFeedbackResponse) {
            html += `<div class="approval-decision-btns">
                <button type="button" class="btn btn-approve"
                       data-action="click->mobile-approvals#selectDecision"
                       data-id="${approval.id}" data-decision="approve">
                    <i class="bi bi-check-circle me-1"></i>Approve
                </button>
                <button type="button" class="btn btn-reject"
                       data-action="click->mobile-approvals#selectDecision"
                       data-id="${approval.id}" data-decision="reject">
                    <i class="bi bi-x-circle me-1"></i>Reject
                </button>
            </div>`
        }

        // Comment
        const commentPlaceholder = isFeedbackResponse
            ? 'Enter requested feedback...'
            : 'Comment (required for rejections)...'
        html += `<div class="mb-2">
            ${isFeedbackResponse ? `<label class="form-label fw-semibold" style="font-size: 0.85rem;">Feedback${requiresComment ? ' <span class="text-danger">(required)</span>' : ''}</label>` : ''}
            <textarea class="approval-comment-box"
                      data-approval-comment="${approval.id}"
                      placeholder="${commentPlaceholder}"
                      rows="2"></textarea>`
        if (commentWarning) {
            html += `<div class="approval-comment-warning"><i class="bi bi-eye me-1"></i>${this._escHtml(commentWarning)}</div>`
        }
        html += `<div class="text-danger small" data-comment-required="${approval.id}" hidden>
            <i class="bi bi-exclamation-circle me-1"></i>${isFeedbackResponse ? 'Feedback is required.' : 'A comment is required when rejecting.'}
        </div></div>`

        // Next approver (hidden until needed)
        html += `<div data-next-approver-section="${approval.id}" hidden>
            <div class="alert alert-info py-2 small" role="alert" data-next-approver-info="${approval.id}"></div>
            <label class="form-label fw-semibold" style="font-size: 0.85rem;">Select Next Approver</label>
            <div class="approval-next-approver">
                <select data-next-approver-select="${approval.id}" class="form-select form-select-lg">
                    <option value="">Loading approvers...</option>
                </select>
            </div>
        </div>`

        // Submit
        html += `<button type="button" class="btn approval-submit-btn"
                         data-action="click->mobile-approvals#submitResponse"
                         data-submit-btn="${approval.id}"${isFeedbackResponse && decisionOptions.length === 0 ? '' : ' disabled'}>
            <i class="bi bi-send me-1"></i>${isFeedbackResponse ? 'Send Feedback' : 'Submit Response'}
        </button>`

        html += '</div>'
        return html
    }

    // --- Decision Handling ---

    selectDecision(event) {
        event.preventDefault()
        const id = parseInt(event.currentTarget.dataset.id)
        const decision = event.currentTarget.dataset.decision
        const form = this.element.querySelector(`[data-approval-form-id="${id}"]`)
        if (!form) return

        // Update button states
        form.querySelectorAll('.btn-approve, .btn-reject').forEach(btn => btn.classList.remove('active'))
        if (event.currentTarget.classList.contains('btn')) {
            event.currentTarget.classList.add('active')
        }

        // Store decision
        form.dataset.selectedDecision = decision

        // Show/hide comment required hint
        const commentRequired = form.querySelector(`[data-comment-required="${id}"]`)
        if (commentRequired) {
            commentRequired.hidden = decision !== 'reject'
        }

        // Handle serial-pick-next for approve
        const approval = this._approvals.find(a => a.id === id)
        const cfg = approval?.approverConfig || {}
        const nextSection = form.querySelector(`[data-next-approver-section="${id}"]`)

        if (decision === 'approve' && cfg.serialPickNext && cfg.feedbackResponse !== true) {
            const remaining = (approval.progress.required || 1) - (approval.progress.approved || 0) - 1
            if (remaining > 0) {
                if (nextSection) {
                    nextSection.hidden = false
                    const infoEl = form.querySelector(`[data-next-approver-info="${id}"]`)
                    if (infoEl) {
                        infoEl.textContent = `This approval requires ${remaining} more approver(s). Select who should review next.`
                    }
                }
                this._loadEligibleApprovers(id)
            } else if (nextSection) {
                nextSection.hidden = true
            }
        } else if (nextSection) {
            nextSection.hidden = true
        }

        // Enable submit
        const submitBtn = form.querySelector(`[data-submit-btn="${id}"]`)
        if (submitBtn) submitBtn.disabled = false
    }

    async _loadEligibleApprovers(approvalId) {
        const select = this.element.querySelector(`[data-next-approver-select="${approvalId}"]`)
        if (!select) return

        select.innerHTML = '<option value="">Loading...</option>'
        select.disabled = true

        try {
            const resp = await this.fetchWithRetry(`${this.eligibleUrlValue}/${approvalId}`)
            const htmlContent = await resp.text()

            // Parse the HTML list items returned by eligibleApprovers endpoint
            select.innerHTML = '<option value="">-- Choose next approver --</option>'
            const temp = document.createElement('div')
            temp.innerHTML = htmlContent
            const items = temp.querySelectorAll('li[data-ac-value]')
            items.forEach(li => {
                const option = document.createElement('option')
                option.value = li.dataset.acValue
                option.textContent = li.textContent
                select.appendChild(option)
            })
            select.disabled = false
        } catch (e) {
            console.error('Failed to load eligible approvers:', e)
            select.innerHTML = '<option value="">Error loading approvers</option>'
        }
    }

    // --- Submit Response ---

    async saveTriage(event) {
        event.preventDefault()
        const form = event.currentTarget.closest("[data-mobile-triage-form]")
        if (!form || !this.hasTriageUrlValue) return

        const approvalId = parseInt(form.dataset.mobileTriageForm)
        const state = form.querySelector("[data-mobile-triage-state]")?.value || "new"
        const note = form.querySelector("[data-mobile-triage-note]")?.value || ""
        const status = form.querySelector("[data-mobile-triage-status]")
        const button = event.currentTarget
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || ''

        button.disabled = true
        if (status) status.textContent = "Saving triage state..."

        try {
            const body = new FormData()
            body.append('approvalId', approvalId)
            body.append('state', state)
            body.append('note', note)
            body.append('_csrfToken', csrfToken)

            const response = await fetch(this.triageUrlValue, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrfToken,
                },
                body,
            })
            const result = await response.json()
            if (!response.ok || result.success === false) {
                throw new Error(result.error || `HTTP ${response.status}`)
            }

            const approval = this._approvals.find(a => a.id === approvalId)
            if (approval) {
                approval.triage = result.triage
            }
            if (status) status.textContent = "Private triage state saved."
            this._showToast("Private triage state saved.", "success")
        } catch (error) {
            const message = error.message || "Unable to save triage state."
            if (status) status.textContent = message
            this._showToast(message, "danger")
        } finally {
            button.disabled = false
        }
    }

    async submitResponse(event) {
        event.preventDefault()
        if (this._submitting) return

        const id = parseInt(event.currentTarget.dataset.submitBtn)
        const form = this.element.querySelector(`[data-approval-form-id="${id}"]`)
        if (!form) return

        const decision = form.dataset.selectedDecision
        if (!decision) return

        const comment = (form.querySelector(`[data-approval-comment="${id}"]`)?.value || '').trim()
        const nextApproverId = form.querySelector(`[data-next-approver-select="${id}"]`)?.value || null

        // Validate
        const approval = this._approvals.find(a => a.id === id)
        const cfg = approval?.approverConfig || {}
        const requiresComment = decision === 'reject' || cfg.requiresComment === true
        if (requiresComment && !comment) {
            const hint = form.querySelector(`[data-comment-required="${id}"]`)
            if (hint) hint.hidden = false
            form.querySelector(`[data-approval-comment="${id}"]`)?.focus()
            return
        }

        if (!this.online) {
            this._showToast('You must be online to submit responses.', 'danger')
            return
        }

        // Submit
        this._submitting = true
        const card = this.listTarget.querySelector(`[data-approval-id="${id}"]`)
        if (card) card.classList.add('approval-card-submitting')

        const submitBtn = form.querySelector(`[data-submit-btn="${id}"]`)
        if (submitBtn) {
            submitBtn.disabled = true
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Submitting...'
        }

        try {
            // Get CSRF token
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || ''

            const body = new FormData()
            body.append('approvalId', id)
            body.append('decision', decision)
            body.append('comment', comment)
            if (nextApproverId) body.append('next_approver_id', nextApproverId)
            body.append('_csrfToken', csrfToken)

            const response = await fetch(this.recordUrlValue, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrfToken,
                },
                body: body,
            })

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`)
            }

            const result = await response.json()

            if (result.success === false) {
                throw new Error(result.error || 'Failed to record response')
            }

            // Success — animate card out and remove from list
            if (card) {
                card.classList.remove('approval-card-submitting')
                card.classList.add('approval-card-done')
                card.addEventListener('animationend', () => card.remove(), { once: true })
            }

            this._approvals = this._approvals.filter(a => a.id !== id)
            this._expandedId = null
            this._updateBadge()

            const message = cfg.feedbackResponse === true
                ? 'Feedback sent successfully.'
                : `Approval ${decision === 'approve' ? 'approved' : 'rejected'} successfully.`
            this._showToast(message, 'success')

            // Show empty state if none left
            if (this._approvals.length === 0) {
                setTimeout(() => this._showState('empty'), 500)
            }
        } catch (error) {
            console.error('Failed to submit response:', error)
            this._showToast(error.message || 'Failed to submit response.', 'danger')

            if (card) card.classList.remove('approval-card-submitting')
            if (submitBtn) {
                submitBtn.disabled = false
                submitBtn.innerHTML = '<i class="bi bi-send me-1"></i>Submit Response'
            }
        } finally {
            this._submitting = false
        }
    }

    // --- UI Helpers ---

    _showState(state) {
        if (this.hasLoadingTarget) this.loadingTarget.hidden = state !== 'loading'
        if (this.hasEmptyTarget) this.emptyTarget.hidden = state !== 'empty'
        if (this.hasErrorTarget) this.errorTarget.hidden = state !== 'error'
        if (this.hasListTarget) this.listTarget.hidden = state !== 'list'
    }

    _updateBadge() {
        if (!this.hasCountBadgeTarget) return
        const count = this._approvals.length
        this.countBadgeTarget.textContent = `${count} pending`
        this.countBadgeTarget.hidden = count === 0
    }

    _showToast(message, type) {
        if (!this.hasToastTarget || !this.hasToastBodyTarget) return
        this.toastBodyTarget.textContent = message
        this.toastTarget.className = `toast align-items-center border-0 text-bg-${type}`
        const toast = new bootstrap.Toast(this.toastTarget)
        toast.show()
    }

    _timeAgo(isoString) {
        if (!isoString) return ''
        const diff = Date.now() - new Date(isoString).getTime()
        const mins = Math.floor(diff / 60000)
        if (mins < 1) return 'just now'
        if (mins < 60) return `${mins}m ago`
        const hrs = Math.floor(mins / 60)
        if (hrs < 24) return `${hrs}h ago`
        const days = Math.floor(hrs / 24)
        return `${days}d ago`
    }

    _escHtml(str) {
        if (!str) return ''
        const div = document.createElement('div')
        div.textContent = String(str)
        return div.innerHTML
    }
}

if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["mobile-approvals"] = MobileApprovalsController

export default MobileApprovalsController
