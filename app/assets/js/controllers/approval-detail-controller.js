import { Controller } from "@hotwired/stimulus"

/**
 * Expandable detail panel for approval rows in the Dataverse grid.
 *
 * Fetches rich context, progress, and response timeline from the
 * approvalDetail API and renders an inline detail row beneath the
 * clicked grid row.
 */
class ApprovalDetailController extends Controller {
    static values = {
        url: String,
        triageUrl: String,
    }

    connect() {
        this._expandedRows = new Map()
    }

    disconnect() {
        this._expandedRows.forEach((detailRow) => detailRow.remove())
        this._expandedRows.clear()
    }

    async toggle(event) {
        event.preventDefault()
        event.stopPropagation()

        const btn = event.currentTarget
        const row = btn.closest("tr")
        if (!row) return

        const approvalId = this._getApprovalId(row)
        if (!approvalId) return

        if (this._expandedRows.has(approvalId)) {
            this._collapse(approvalId, btn)
        } else {
            await this._expand(row, approvalId, btn)
        }
    }

    // --- private helpers ---------------------------------------------------

    _getApprovalId(row) {
        const checkbox = row.querySelector("[data-row-id]")
        if (checkbox) return checkbox.dataset.rowId

        const dataAttr = row.dataset.id
        if (dataAttr) return dataAttr

        const cells = row.querySelectorAll("td")
        for (const cell of cells) {
            const input = cell.querySelector("input[type=hidden][name*=id]")
            if (input) return input.value
        }

        const btn = row.querySelector("[data-outlet-btn-btn-data-value]")
        if (btn) {
            try {
                const d = JSON.parse(btn.getAttribute("data-outlet-btn-btn-data-value"))
                if (d.id) return String(d.id)
            } catch (_) { /* ignore */ }
        }

        return null
    }

    async _expand(row, approvalId, btn) {
        btn.disabled = true
        const icon = btn.querySelector("i.bi")
        if (icon) {
            icon.classList.remove("bi-chevron-down")
            icon.classList.add("bi-hourglass-split")
        }

        try {
            const response = await fetch(this.urlValue + approvalId, {
                headers: { "X-Requested-With": "XMLHttpRequest" },
            })
            if (!response.ok) throw new Error(`HTTP ${response.status}`)

            const data = await response.json()

            const colspan = row.querySelectorAll("td").length
            const detailRow = document.createElement("tr")
            detailRow.classList.add("approval-detail-row")
            detailRow.innerHTML = `<td colspan="${colspan}" class="p-0 border-top-0">${this._renderDetail(data)}</td>`
            row.after(detailRow)

            this._expandedRows.set(approvalId, detailRow)

            if (icon) {
                icon.classList.remove("bi-hourglass-split")
                icon.classList.add("bi-chevron-up")
            }
        } catch (err) {
            console.error("Failed to load approval detail:", err)
            if (icon) {
                icon.classList.remove("bi-hourglass-split")
                icon.classList.add("bi-chevron-down")
            }
        } finally {
            btn.disabled = false
        }
    }

    _collapse(approvalId, btn) {
        const detailRow = this._expandedRows.get(approvalId)
        if (detailRow) detailRow.remove()
        this._expandedRows.delete(approvalId)

        const icon = btn.querySelector("i.bi")
        if (icon) {
            icon.classList.remove("bi-chevron-up")
            icon.classList.add("bi-chevron-down")
        }
    }

    _escapeHtml(str) {
        if (str == null) return ""
        const div = document.createElement("div")
        div.appendChild(document.createTextNode(String(str)))
        return div.innerHTML
    }

    async updateTriage(event) {
        event.preventDefault()
        const form = event.currentTarget.closest("[data-approval-triage-form]")
        if (!form || !this.hasTriageUrlValue) return

        const approvalId = form.dataset.approvalTriageForm
        const state = form.querySelector("[data-approval-triage-state]")?.value || "new"
        const note = form.querySelector("[data-approval-triage-note]")?.value || ""
        const status = form.querySelector("[data-approval-triage-status]")
        const submit = event.currentTarget
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || ""

        submit.disabled = true
        if (status) {
            status.textContent = "Saving triage state..."
        }

        try {
            const body = new FormData()
            body.append("approvalId", approvalId)
            body.append("state", state)
            body.append("note", note)
            body.append("_csrfToken", csrfToken)

            const response = await fetch(this.triageUrlValue, {
                method: "POST",
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                    "X-CSRF-Token": csrfToken,
                },
                body,
            })
            const result = await response.json()
            if (!response.ok || result.success === false) {
                throw new Error(result.error || `HTTP ${response.status}`)
            }
            if (status) {
                status.textContent = "Private triage state saved."
            }
            if (window.KMP_accessibility?.announce) {
                window.KMP_accessibility.announce("Private triage state saved.")
            }
        } catch (error) {
            if (status) {
                status.textContent = error.message || "Unable to save triage state."
            }
            if (window.KMP_accessibility?.announce) {
                window.KMP_accessibility.announce(status?.textContent || "Unable to save triage state.", "assertive")
            }
        } finally {
            submit.disabled = false
        }
    }

    _renderDetail(data) {
        const h = (v) => this._escapeHtml(v)
        const ctx = data.context || {}
        const prog = data.progress || {}
        const responses = data.responses || []
        const ui = data.ui || {}
        const triage = data.triage || {}
        const hideProgress = ui.hideProgress === true || ui.feedbackResponse === true

        let html = '<div class="p-3 bg-light">'
        html += '<div class="row">'

        // ---- Left column: context ----
        html += hideProgress ? '<div class="col-12">' : '<div class="col-md-6 mb-3 mb-md-0">'
        html += `<h6 class="mb-2"><i class="bi ${h(ctx.icon)} me-1"></i>${h(ctx.title)}</h6>`
        if (ctx.description) {
            html += `<p class="text-muted small mb-2">${h(ctx.description)}</p>`
        }
        if (ctx.fields && ctx.fields.length) {
            ctx.fields.forEach((f) => {
                html += `<div class="mb-1"><strong class="small text-body-secondary">${h(f.label)}:</strong> <span class="small">${h(f.value)}</span></div>`
            })
        }
        if (ctx.entityUrl) {
            html += `<a href="${h(ctx.entityUrl)}" class="btn btn-sm btn-outline-primary mt-2" data-turbo-frame="_top"><i class="bi bi-box-arrow-up-right me-1"></i>View Entity</a>`
        }
        if (ui.canTriage === true) {
            html += this._renderTriageForm(data, triage)
        }
        html += "</div>"

        if (!hideProgress) {
            // ---- Right column: progress & timeline ----
            html += '<div class="col-md-6">'

            // Progress bar
            html += '<h6 class="mb-2">Approval Progress</h6>'
            const required = prog.required || 0
            const approved = prog.approved || 0
            const rejected = prog.rejected || 0
            const approvedPct = required > 0 ? Math.round((approved / required) * 100) : 0
            const rejectedPct = required > 0 ? Math.round((rejected / required) * 100) : 0

            html += '<div class="progress mb-1" role="progressbar" style="height:20px;">'
            if (approvedPct > 0) {
                html += `<div class="progress-bar bg-success" style="width:${approvedPct}%">${approved} approved</div>`
            }
            if (rejectedPct > 0) {
                html += `<div class="progress-bar bg-danger" style="width:${rejectedPct}%">${rejected} rejected</div>`
            }
            html += "</div>"
            html += `<p class="text-muted small mb-3">${approved} of ${required} required &middot; Status: <strong>${h(prog.status)}</strong></p>`

            // Response timeline
            if (responses.length > 0) {
                html += '<h6 class="mb-2">Response Timeline</h6>'
                html += '<ul class="list-group list-group-flush small">'
                responses.forEach((r) => {
                    const isApprove = r.decision === "approve"
                    const iconCls = isApprove ? "bi-check-circle-fill text-success" : "bi-x-circle-fill text-danger"
                    html += '<li class="list-group-item bg-transparent px-0 py-1">'
                    html += `<i class="bi ${iconCls} me-1"></i>`
                    html += `<strong>${h(r.memberName)}</strong> &mdash; ${h(r.decision)}`
                    if (r.comment) {
                        html += ` <em class="text-muted">&ldquo;${h(r.comment)}&rdquo;</em>`
                    }
                    html += ` <span class="text-muted">(${h(r.respondedAt)})</span>`
                    html += "</li>"
                })
                html += "</ul>"
            } else {
                html += '<p class="text-muted small fst-italic mb-0">No responses yet.</p>'
            }

            html += "</div>"
        }
        html += "</div></div>"
        return html
    }

    _renderTriageForm(data, triage) {
        const h = (v) => this._escapeHtml(v)
        const approvalId = data.progress?.approvalId || data.approvalId || ""
        const selectId = `approval-triage-state-${h(approvalId)}`
        const noteId = `approval-triage-note-${h(approvalId)}`
        const helpId = `approval-triage-help-${h(approvalId)}`
        const statusId = `approval-triage-status-${h(approvalId)}`
        const states = triage.states || {}
        let options = ""
        Object.keys(states).forEach((state) => {
            options += `<option value="${h(state)}"${state === triage.state ? " selected" : ""}>${h(states[state])}</option>`
        })

        return `<form class="border rounded bg-white p-2 mt-3"
                    data-approval-triage-form="${h(approvalId)}">
            <div class="mb-2">
                <label class="form-label small fw-semibold" for="${selectId}">Private triage state</label>
                <select class="form-select form-select-sm" id="${selectId}" data-approval-triage-state aria-describedby="${helpId}">
                    ${options}
                </select>
            </div>
            <div class="mb-2">
                <label class="form-label small fw-semibold" for="${noteId}">Private note</label>
                <textarea class="form-control form-control-sm" id="${noteId}" data-approval-triage-note rows="2" aria-describedby="${helpId}">${h(triage.note || "")}</textarea>
                <div class="form-text" id="${helpId}">Only you can see this triage note. It does not submit an approval decision.</div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-primary" data-action="click->approval-detail#updateTriage">
                    Save private triage
                </button>
                <span class="small text-muted" id="${statusId}" data-approval-triage-status role="status" aria-live="polite"></span>
            </div>
        </form>`
    }
}

if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["approval-detail"] = ApprovalDetailController
