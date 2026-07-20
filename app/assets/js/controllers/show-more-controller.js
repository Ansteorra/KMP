import { Controller } from "@hotwired/stimulus"

class ShowMoreController extends Controller {
    static values = {
        targetSelector: String,
        moreLabel: String,
        lessLabel: String,
    }

    toggle(event) {
        event.preventDefault()
        const target = document.querySelector(this.targetSelectorValue)
        if (!target) {
            return
        }

        const hidden = target.hidden || target.style.display === "none"
        target.hidden = !hidden
        target.style.display = hidden ? "inline" : "none"
        this.element.textContent = hidden ? this.lessLabelValue : this.moreLabelValue
        window.KMP_accessibility.announce(hidden ? "Additional items shown." : "Additional items hidden.")
    }
}

if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["show-more"] = ShowMoreController
