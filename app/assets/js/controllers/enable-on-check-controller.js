import { Controller } from "@hotwired/stimulus"

class EnableOnCheckController extends Controller {
    static values = {
        targetSelector: String,
    }

    toggle(event) {
        const target = document.querySelector(this.targetSelectorValue)
        if (target) {
            target.toggleAttribute("disabled", !event.currentTarget.checked)
            target.classList.toggle("disabled", !event.currentTarget.checked)
        }
    }
}

if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["enable-on-check"] = EnableOnCheckController
