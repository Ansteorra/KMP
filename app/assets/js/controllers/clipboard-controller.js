import { Controller } from "@hotwired/stimulus"

class ClipboardController extends Controller {
    static values = {
        text: String,
        sourceSelector: String,
        successMessage: { type: String, default: "Copied to clipboard." },
    }

    copy(event) {
        event.preventDefault()
        const text = this.resolveText()
        if (!text) {
            window.KMP_accessibility.announce("Nothing available to copy.", { assertive: true })
            return
        }

        navigator.clipboard.writeText(text).then(() => {
            window.KMP_accessibility.announce(this.successMessageValue)
        }).catch(() => {
            window.KMP_accessibility.announce("Unable to copy to clipboard.", { assertive: true })
        })
    }

    selectSource(event) {
        event.currentTarget.select()
    }

    resolveText() {
        if (this.hasTextValue) {
            return this.textValue
        }

        if (this.hasSourceSelectorValue) {
            const source = document.querySelector(this.sourceSelectorValue)
            return source?.value || source?.textContent || ""
        }

        return this.element.value || this.element.textContent || ""
    }
}

if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["clipboard"] = ClipboardController
