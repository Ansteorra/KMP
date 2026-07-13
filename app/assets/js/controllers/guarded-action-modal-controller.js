import { Controller } from "@hotwired/stimulus"

/**
 * Opens server-rendered guarded action forms in one reusable Bootstrap modal.
 */
class GuardedActionModalController extends Controller {
    static targets = ["modal", "content", "title"]

    initialize() {
        this.lastTrigger = null
        this.submitting = false
        this.clearTimer = null
        this.boundHandleShown = this.handleShown.bind(this)
        this.boundHandleHidden = this.handleHidden.bind(this)
        this.boundHandleSubmit = this.submit.bind(this)
        this.boundHandleInput = this.clearConfirmationError.bind(this)
        this.boundHandleChange = this.updateExpectedConfirmation.bind(this)
    }

    connect() {
        this.modalInstance = new window.bootstrap.Modal(this.modalTarget)
        this.modalTarget.addEventListener("shown.bs.modal", this.boundHandleShown)
        this.modalTarget.addEventListener("hidden.bs.modal", this.boundHandleHidden)
        this.contentTarget.addEventListener("submit", this.boundHandleSubmit)
        this.contentTarget.addEventListener("input", this.boundHandleInput)
        this.contentTarget.addEventListener("change", this.boundHandleChange)
    }

    disconnect() {
        this.modalTarget.removeEventListener("shown.bs.modal", this.boundHandleShown)
        this.modalTarget.removeEventListener("hidden.bs.modal", this.boundHandleHidden)
        this.contentTarget.removeEventListener("submit", this.boundHandleSubmit)
        this.contentTarget.removeEventListener("input", this.boundHandleInput)
        this.contentTarget.removeEventListener("change", this.boundHandleChange)
        if (this.clearTimer !== null) {
            window.clearTimeout(this.clearTimer)
            this.clearTimer = null
        }
        this.modalInstance?.dispose()
    }

    open(event) {
        event.preventDefault()
        const trigger = event.currentTarget
        const template = document.getElementById(trigger.dataset.guardedTemplateId || "")
        if (!(template instanceof HTMLTemplateElement) || !this.element.contains(template)) {
            window.KMP_accessibility.announce("The approval form could not be opened.", { assertive: true })
            return
        }

        this.clearContent()
        this.lastTrigger = trigger
        this.submitting = false
        this.titleTarget.textContent = trigger.dataset.guardedModalTitle || "Approve backup action"
        this.contentTarget.append(template.content.cloneNode(true))
        this.modalInstance.show()
    }

    submit(event) {
        const form = event.target
        if (!(form instanceof HTMLFormElement)) {
            return
        }

        const confirmation = form.elements.namedItem("confirmation")
        const expected = form.dataset.expectedConfirmation || ""
        if (!(confirmation instanceof HTMLInputElement) || confirmation.value !== expected) {
            event.preventDefault()
            if (confirmation instanceof HTMLInputElement) {
                confirmation.setCustomValidity(`Type "${expected}" exactly to continue.`)
                confirmation.reportValidity()
                confirmation.focus()
            }
            window.KMP_accessibility.announce(`Type "${expected}" exactly to continue.`, { assertive: true })
            return
        }

        confirmation.setCustomValidity("")
        this.submitting = true
        this.modalInstance.hide()
    }

    clearConfirmationError(event) {
        const input = event.target
        if (input instanceof HTMLInputElement && input.name === "confirmation") {
            input.setCustomValidity("")
        }
    }

    /**
     * Recompute the expected typed confirmation when a select carrying a
     * data-confirmation-template (e.g. a restore-target picker) changes.
     */
    updateExpectedConfirmation(event) {
        const source = event.target
        if (!(source instanceof HTMLSelectElement) || !source.dataset.confirmationTemplate) {
            return
        }
        const form = source.closest("form")
        if (!(form instanceof HTMLFormElement)) {
            return
        }
        const expected = source.dataset.confirmationTemplate.replaceAll("{value}", source.value)
        form.dataset.expectedConfirmation = expected
        const confirmation = form.elements.namedItem("confirmation")
        if (confirmation instanceof HTMLInputElement) {
            confirmation.setCustomValidity("")
            const label = form.querySelector(`label[for="${confirmation.id}"]`)
            if (label instanceof HTMLElement) {
                label.textContent = `Type "${expected}" to confirm`
            }
        }
    }

    handleShown() {
        const initialFocus = this.contentTarget.querySelector("[data-guarded-action-initial-focus]")
        if (initialFocus instanceof HTMLElement) {
            initialFocus.focus()
        }
    }

    handleHidden() {
        if (this.submitting) {
            this.clearTimer = window.setTimeout(() => {
                this.clearContent()
                this.clearTimer = null
            }, 0)
        } else {
            this.clearContent()
        }
        this.submitting = false

        if (this.lastTrigger instanceof HTMLElement && this.lastTrigger.isConnected) {
            this.lastTrigger.focus({ preventScroll: true })
        }
    }

    clearContent() {
        this.contentTarget.replaceChildren()
    }
}

if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["guarded-action-modal"] = GuardedActionModalController
