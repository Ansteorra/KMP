import { Controller } from "@hotwired/stimulus"

class RecommendationFeedbackModalController extends Controller {
    static targets = [
        "ids",
        "selectionSummary",
        "recipientIds",
        "recipientLookup",
        "recipientList",
        "recipientStatus",
        "addRecipientButton",
        "submitButton",
    ]

    connect() {
        this.recipients = new Map()
        this.handleOutlet = this.handleOutlet.bind(this)
        document.addEventListener("outlet-btn:outlet-button-clicked", this.handleOutlet)
        document.addEventListener("outlet-btn:notice", this.handleOutlet)
        this.updateSubmitState()
    }

    disconnect() {
        document.removeEventListener("outlet-btn:outlet-button-clicked", this.handleOutlet)
        document.removeEventListener("outlet-btn:notice", this.handleOutlet)
    }

    handleOutlet(event) {
        const detail = event.detail || {}
        const ids = Array.isArray(detail.ids) ? detail.ids : [detail.id].filter(Boolean)
        if (ids.length === 0) return

        this.idsTarget.value = ids.join(",")
        this.selectionSummaryTarget.textContent = ids.length === 1
            ? "1 recommendation selected"
            : `${ids.length} recommendations selected`
        this.updateSubmitState()
    }

    recipientSelected() {
        this.updateSubmitState()
    }

    addRecipient() {
        const selected = this.currentLookupSelection()
        if (!selected.id || !selected.label) return

        if (this.recipients.has(selected.id)) {
            this.announce(`${selected.label} is already selected.`)
            this.clearLookup()
            this.updateSubmitState()
            return
        }

        this.recipients.set(selected.id, selected.label)
        this.renderRecipients()
        this.clearLookup()
        this.announce(`${selected.label} added as a feedback recipient.`)
        this.updateSubmitState()
    }

    removeRecipient(event) {
        const id = event.currentTarget.dataset.recipientId
        const label = this.recipients.get(id)
        this.recipients.delete(id)
        this.renderRecipients()
        this.announce(`${label || "Recipient"} removed.`)
        this.updateSubmitState()
    }

    updateSubmitState() {
        const lookupSelection = this.currentLookupSelection()
        if (this.hasAddRecipientButtonTarget) {
            this.addRecipientButtonTarget.disabled = !lookupSelection.id || !lookupSelection.label
        }

        if (this.hasSubmitButtonTarget) {
            this.submitButtonTarget.disabled = !this.idsTarget.value || this.recipients.size === 0
        }
    }

    currentLookupSelection() {
        if (!this.hasRecipientLookupTarget) return { id: "", label: "" }

        const id = this.recipientLookupTarget.querySelector("[data-ac-target='hidden']")?.value || ""
        const label = this.recipientLookupTarget.querySelector("[data-ac-target='hiddenText']")?.value
            || this.recipientLookupTarget.querySelector("[data-ac-target='input']")?.value
            || ""

        return { id: id.trim(), label: label.trim() }
    }

    renderRecipients() {
        this.recipientIdsTarget.value = Array.from(this.recipients.keys()).join(",")
        this.recipientListTarget.replaceChildren()

        for (const [id, label] of this.recipients.entries()) {
            const item = document.createElement("span")
            item.className = "badge rounded-2 text-bg-light border d-inline-flex align-items-center gap-1"
            item.setAttribute("role", "listitem")

            const text = document.createElement("span")
            text.textContent = label

            const button = document.createElement("button")
            button.type = "button"
            button.className = "btn btn-sm btn-outline-secondary ms-1"
            button.textContent = "Remove"
            button.dataset.recipientId = id
            button.setAttribute("aria-label", `Remove ${label}`)
            button.addEventListener("click", this.removeRecipient.bind(this))

            item.append(text, button)
            this.recipientListTarget.append(item)
        }
    }

    clearLookup() {
        if (!this.hasRecipientLookupTarget) return

        const getController = window.Stimulus?.getControllerForElementAndIdentifier
        const autocomplete = typeof getController === "function"
            ? getController.call(window.Stimulus, this.recipientLookupTarget, "ac")
            : null
        if (autocomplete?.clear) {
            autocomplete.clear()
            return
        }

        this.recipientLookupTarget.querySelectorAll("[data-ac-target='hidden'], [data-ac-target='hiddenText']").forEach((field) => {
            field.value = ""
            field.dispatchEvent(new Event("change", { bubbles: true }))
        })
        const input = this.recipientLookupTarget.querySelector("[data-ac-target='input']")
        if (input) {
            input.value = ""
            input.disabled = false
            input.dispatchEvent(new Event("input", { bubbles: true }))
        }
        const clearButton = this.recipientLookupTarget.querySelector("[data-ac-target='clearBtn']")
        if (clearButton) clearButton.disabled = true
    }

    announce(message) {
        if (this.hasRecipientStatusTarget) {
            this.recipientStatusTarget.textContent = message
        }
    }
}

if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["recommendation-feedback-modal"] = RecommendationFeedbackModalController
