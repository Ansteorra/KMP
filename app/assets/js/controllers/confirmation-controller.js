import { Controller } from "@hotwired/stimulus"

class ConfirmationController extends Controller {
    static values = {
        message: String,
        title: { type: String, default: "Confirm action" },
        confirmLabel: { type: String, default: "Confirm" },
        submitSelector: String,
    }

    async confirm(event) {
        event.preventDefault()
        event.stopPropagation()
        const trigger = event.currentTarget

        const confirmed = await window.KMP_accessibility.confirm(this.messageValue, {
            title: this.titleValue,
            confirmLabel: this.confirmLabelValue,
        })
        if (!confirmed) {
            return false
        }

        const targetForm = this.resolveForm(trigger)
        if (targetForm) {
            HTMLFormElement.prototype.submit.call(targetForm)
            return true
        }

        if (trigger instanceof HTMLAnchorElement && trigger.href) {
            window.location.assign(trigger.href)
        }

        return true
    }

    resolveForm(trigger) {
        if (this.hasSubmitSelectorValue) {
            const form = document.querySelector(this.submitSelectorValue)
            return form instanceof HTMLFormElement ? form : null
        }

        const form = trigger?.closest("form")
        return form instanceof HTMLFormElement ? form : null
    }
}

if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["confirmation"] = ConfirmationController
