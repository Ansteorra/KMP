import { Controller } from "@hotwired/stimulus";
import QuickLoginService from "../services/quick-login-service.js";

const SESSION_UNLOCK_KEY = "kmp.quickLogin.sessionUnlocked";

class MobilePinGateController extends Controller {
    static values = {
        email: String
    };

    initialize() {
        this.overlay = null;
        this.requireFreshEntryPinCheck = false;
        this.currentGateReason = null;
        this._onlineHandler = this.enforceGate.bind(this);
        this._offlineHandler = this.enforceGate.bind(this);
        this._visibilityHandler = this.handleVisibilityChange.bind(this);
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
        this.currentGateReason = reason;
        this.overlay.innerHTML = `
            <div class="mobile-pin-gate-card">
                <h2 class="h4 mb-2">PIN Required</h2>
                <p class="text-muted mb-3" data-mobile-pin-gate-message>${this.gateMessage(reason)}</p>
                <form data-mobile-pin-gate-form>
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
                    <div class="text-danger small mb-2 d-none" data-mobile-pin-gate-error></div>
                    <button type="submit" class="btn btn-primary w-100">Unlock</button>
                </form>
            </div>
        `;

        document.body.appendChild(this.overlay);
        document.body.style.overflow = "hidden";

        this.form = this.overlay.querySelector("[data-mobile-pin-gate-form]");
        this.errorNode = this.overlay.querySelector("[data-mobile-pin-gate-error]");
        this.pinInput = this.overlay.querySelector("#mobile-pin-gate-input");
        this.messageNode = this.overlay.querySelector("[data-mobile-pin-gate-message]");
        this.form?.addEventListener("submit", this.handleUnlockSubmit.bind(this));
        this.pinInput?.focus();
    }

    hideGate() {
        if (!this.overlay) {
            return;
        }

        this.overlay.remove();
        this.overlay = null;
        this.form = null;
        this.errorNode = null;
        this.pinInput = null;
        this.messageNode = null;
        this.currentGateReason = null;
        document.body.style.overflow = "";
    }

    async handleUnlockSubmit(event) {
        event.preventDefault();
        if (!this.pinInput || !this.errorNode) {
            return;
        }

        const pin = this.pinInput.value.trim();
        if (!/^\d{4,10}$/.test(pin)) {
            this.errorNode.textContent = "PIN must be 4 to 10 digits.";
            this.errorNode.classList.remove("d-none");
            return;
        }

        const isValid = await QuickLoginService.verifyPin(pin);
        if (!isValid) {
            this.errorNode.textContent = "Incorrect PIN.";
            this.errorNode.classList.remove("d-none");
            this.pinInput.value = "";
            this.pinInput.focus();
            return;
        }

        sessionStorage.setItem(SESSION_UNLOCK_KEY, "1");
        this.requireFreshEntryPinCheck = false;
        this.hideGate();
    }
}

if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["mobile-pin-gate"] = MobilePinGateController;

export default MobilePinGateController;
