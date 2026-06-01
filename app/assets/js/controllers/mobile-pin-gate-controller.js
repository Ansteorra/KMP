import { Controller } from "@hotwired/stimulus";
import QuickLoginService from "../services/quick-login-service.js";

const SESSION_UNLOCK_KEY = "kmp.quickLogin.sessionUnlocked";

class MobilePinGateController extends Controller {
    static values = {
        email: String,
        logoutUrl: { type: String, default: "/Members/logout" }
    };

    initialize() {
        this.overlay = null;
        this.requireFreshEntryPinCheck = false;
        this.currentGateReason = null;
        this._onlineHandler = this.enforceGate.bind(this);
        this._offlineHandler = this.enforceGate.bind(this);
        this._visibilityHandler = this.handleVisibilityChange.bind(this);
        this._submitHandler = this.handleUnlockSubmit.bind(this);
        this._focusTrapHandler = this.handleGateKeydown.bind(this);
        this.previouslyFocusedElement = null;
    }

    connect() {
        this.requireFreshEntryPinCheck = this.shouldRequireFreshEntryPinCheck();
        window.addEventListener("online", this._onlineHandler);
        window.addEventListener("offline", this._offlineHandler);
        document.addEventListener("visibilitychange", this._visibilityHandler);
        this.enforceGate();
    }

    disconnect() {
        window.removeEventListener("online", this._onlineHandler);
        window.removeEventListener("offline", this._offlineHandler);
        document.removeEventListener("visibilitychange", this._visibilityHandler);
        this.hideGate();
    }

    handleVisibilityChange() {
        if (document.visibilityState === "visible") {
            this.enforceGate();
        }
    }

    isQuickLoginConfiguredForCurrentMember() {
        const quickConfig = QuickLoginService.getQuickConfig();
        if (!quickConfig) {
            return false;
        }

        const currentEmail = (this.hasEmailValue ? this.emailValue : "").trim().toLowerCase();
        if (currentEmail === "") {
            return false;
        }

        const deviceId = QuickLoginService.getOrCreateDeviceId();
        return (
            quickConfig.deviceId === deviceId &&
            quickConfig.email.toLowerCase() === currentEmail
        );
    }

    isUnlocked() {
        return sessionStorage.getItem(SESSION_UNLOCK_KEY) === "1";
    }

    currentNavigationType() {
        const navigationEntries = window.performance?.getEntriesByType?.("navigation");
        const navType = navigationEntries?.[0]?.type;
        return typeof navType === "string" ? navType : "";
    }

    isTrustedReferrer(referrer) {
        if (typeof referrer !== "string" || referrer.trim() === "") {
            return false;
        }

        try {
            return new URL(referrer, window.location.href).origin === window.location.origin;
        } catch (error) {
            return false;
        }
    }

    shouldRequireFreshEntryPinCheck(referrer = document.referrer, navigationType = this.currentNavigationType()) {
        if (this.isTrustedReferrer(referrer)) {
            return false;
        }

        return navigationType !== "reload" && navigationType !== "back_forward";
    }

    gateMessage(reason) {
        if (reason === "offline") {
            return "You're offline. Enter your quick login PIN to unlock this device.";
        }

        return "Enter your quick login PIN to unlock this session.";
    }

    enforceGate() {
        if (!this.isQuickLoginConfiguredForCurrentMember()) {
            this.hideGate();
            return;
        }

        if (this.isUnlocked()) {
            this.hideGate();
            return;
        }

        if (!navigator.onLine) {
            this.showGate("offline");
            return;
        }

        if (this.requireFreshEntryPinCheck) {
            this.showGate("fresh-entry");
            return;
        }

        this.hideGate();
    }

    showGate(reason) {
        if (this.overlay) {
            if (this.currentGateReason !== reason && this.messageNode) {
                this.messageNode.textContent = this.gateMessage(reason);
                this.currentGateReason = reason;
            }
            return;
        }

        this.overlay = document.createElement("div");
        this.overlay.className = "mobile-pin-gate-overlay";
        this.overlay.setAttribute("role", "dialog");
        this.overlay.setAttribute("aria-modal", "true");
        this.overlay.setAttribute("aria-labelledby", "mobile-pin-gate-title");
        this.overlay.setAttribute("aria-describedby", "mobile-pin-gate-message");
        this.currentGateReason = reason;
        this.previouslyFocusedElement = document.activeElement instanceof HTMLElement
            ? document.activeElement
            : null;
        this.overlay.innerHTML = `
            <div class="mobile-pin-gate-card">
                <h2 class="h4 mb-2" id="mobile-pin-gate-title">PIN Required</h2>
                <p class="text-muted mb-3" id="mobile-pin-gate-message" data-mobile-pin-gate-message>${this.gateMessage(reason)}</p>
                <form data-mobile-pin-gate-form aria-busy="false">
                    <div class="mb-2">
                        <label class="form-label" for="mobile-pin-gate-input">PIN</label>
                        <input
                            id="mobile-pin-gate-input"
                            class="form-control"
                            type="password"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            minlength="4"
                            maxlength="10"
                            autocomplete="current-password"
                            required
                        />
                    </div>
                    <div class="text-danger small mb-2 d-none" role="alert" aria-live="assertive" data-mobile-pin-gate-error></div>
                    <button type="submit" class="btn btn-primary w-100" data-mobile-pin-gate-submit>Unlock</button>
                    <a class="btn btn-outline-danger w-100 mt-2" href="${this.escapeAttribute(this.logoutUrlValue || "/Members/logout")}" data-mobile-pin-gate-sign-out>Sign out</a>
                </form>
            </div>
        `;

        document.body.appendChild(this.overlay);
        document.body.style.overflow = "hidden";

        this.form = this.overlay.querySelector("[data-mobile-pin-gate-form]");
        this.errorNode = this.overlay.querySelector("[data-mobile-pin-gate-error]");
        this.pinInput = this.overlay.querySelector("#mobile-pin-gate-input");
        this.messageNode = this.overlay.querySelector("[data-mobile-pin-gate-message]");
        this.submitButton = this.overlay.querySelector("[data-mobile-pin-gate-submit]");
        this.signOutLink = this.overlay.querySelector("[data-mobile-pin-gate-sign-out]");
        this.form?.addEventListener("submit", this._submitHandler);
        this.overlay.addEventListener("keydown", this._focusTrapHandler);
        this.pinInput?.focus();
    }

    hideGate() {
        if (!this.overlay) {
            return;
        }

        this.form?.removeEventListener("submit", this._submitHandler);
        this.overlay.removeEventListener("keydown", this._focusTrapHandler);
        const previousFocus = this.previouslyFocusedElement;
        this.overlay.remove();
        this.overlay = null;
        this.form = null;
        this.errorNode = null;
        this.pinInput = null;
        this.messageNode = null;
        this.submitButton = null;
        this.signOutLink = null;
        this.currentGateReason = null;
        document.body.style.overflow = "";
        if (previousFocus && previousFocus.isConnected && typeof previousFocus.focus === "function") {
            previousFocus.focus();
        }
        this.previouslyFocusedElement = null;
    }

    async handleUnlockSubmit(event) {
        event.preventDefault();
        if (!this.pinInput || !this.errorNode) {
            return;
        }

        const pin = this.pinInput.value.trim();
        if (!/^\d{4,10}$/.test(pin)) {
            this.showError("PIN must be 4 to 10 digits.");
            return;
        }

        this.showError("");
        this.setBusy(true);
        let isValid;
        try {
            isValid = await QuickLoginService.verifyPin(pin);
        } finally {
            this.setBusy(false);
        }
        if (!isValid) {
            this.showError("Incorrect PIN.");
            this.pinInput.value = "";
            this.pinInput.focus();
            return;
        }

        sessionStorage.setItem(SESSION_UNLOCK_KEY, "1");
        this.requireFreshEntryPinCheck = false;
        this.hideGate();
    }

    setBusy(isBusy) {
        this.form?.setAttribute("aria-busy", isBusy ? "true" : "false");
        if (this.submitButton) {
            this.submitButton.disabled = isBusy;
        }
    }

    showError(message) {
        if (!this.errorNode) {
            return;
        }

        this.errorNode.textContent = message;
        this.errorNode.classList.toggle("d-none", message === "");
    }

    handleGateKeydown(event) {
        if (!this.overlay) {
            return;
        }

        if (event.key === "Escape") {
            event.preventDefault();
            this.signOutLink?.focus();
            return;
        }

        if (event.key !== "Tab") {
            return;
        }

        const focusable = this.focusableGateElements();
        if (focusable.length === 0) {
            event.preventDefault();
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    focusableGateElements() {
        if (!this.overlay) {
            return [];
        }

        return Array.from(this.overlay.querySelectorAll(
            'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
        )).filter((element) => !element.hidden && element.getAttribute("aria-hidden") !== "true");
    }

    escapeAttribute(value) {
        const div = document.createElement("div");
        div.textContent = value;
        return div.innerHTML.replace(/"/g, "&quot;");
    }
}

if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["mobile-pin-gate"] = MobilePinGateController;

export default MobilePinGateController;
