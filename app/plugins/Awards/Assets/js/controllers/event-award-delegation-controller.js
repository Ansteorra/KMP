import { Controller } from "@hotwired/stimulus";

/** Keeps event delegation inside its dialog and reports expired access safely. */
class EventAwardDelegation extends Controller {
    static targets = ["frame"];
    static values = { url: String };

    /** Refresh previously opened history so another person's revocation is reflected. */
    open() {
        this.frameTarget.src = this.urlValue;
    }

    /** Remove private form content on closing; the next opening requests current access. */
    close() {
        this.frameTarget.removeAttribute("src");
        const status = document.createElement("p");
        status.setAttribute("role", "status");
        status.textContent = "Loading event award delegations…";
        this.frameTarget.replaceChildren(status);
    }

    /** Keep focus inside the dialog after a grant or revoke replaces the form. */
    loaded(event) {
        if (event.target !== this.frameTarget || !this.element.classList.contains("show")) return;
        if (this.frameTarget.contains(document.activeElement)) return;
        const feedback = this.frameTarget.querySelector(".alert, [role='alert']");
        const target = feedback || this.frameTarget.querySelector("select, input:not([type='hidden'])")
            || this.frameTarget.querySelector("h3");
        if (!target) return;
        if (target === feedback || target.tagName === "H3") target.setAttribute("tabindex", "-1");
        target.focus();
    }

    /** An authorization redirect must never navigate the page or expose its response inside the dialog. */
    unavailable(event) {
        if (event.target !== this.frameTarget) return;
        event.preventDefault();
        const alert = document.createElement("p");
        alert.className = "alert alert-danger";
        alert.setAttribute("role", "alert");
        alert.setAttribute("tabindex", "-1");
        alert.textContent = "Event award delegation is no longer available. Close this dialog and refresh the page.";
        this.frameTarget.replaceChildren(alert);
        if (this.element.classList.contains("show")) alert.focus();
    }
}

window.Controllers ||= {};
window.Controllers["event-award-delegation"] = EventAwardDelegation;
