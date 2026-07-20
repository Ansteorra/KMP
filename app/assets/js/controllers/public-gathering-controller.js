import { Controller } from "@hotwired/stimulus"

class PublicGatheringController extends Controller {
    connect() {
        this.decodeEmailLinks()
    }

    decodeEmailLinks() {
        this.element.querySelectorAll(".email-link[data-email]").forEach((link) => {
            const encodedEmail = link.getAttribute("data-email")
            if (!encodedEmail) {
                return
            }

            const email = atob(encodedEmail)
            link.href = `mailto:${email}`
            link.textContent = email
            link.removeAttribute("data-email")
        })
    }
}

if (!window.Controllers) {
    window.Controllers = {}
}

window.Controllers["public-gathering"] = PublicGatheringController
