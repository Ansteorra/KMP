import { shortSiteTitle } from '../services/app-branding-service.js';
import { Controller } from "@hotwired/stimulus";
import QuickLoginService from "../services/quick-login-service.js";

class LoginDeviceAuthController extends Controller {
    static targets = [
        "passwordLogin", "deviceExperience", "deviceSwitch", "offlineNotice", "migrationNotice",
        "passwordExperience",
        "quickExperience",
        "passwordForm",
        "quickForm",
        "quickPin",
        "email",
        "rememberId",
        "quickEnable",
        "passwordDeviceId",
        "quickDeviceId",
        "quickEmail",
        "quickLoginLabel",
        "quickDisabled",
        "quickDisabledEmail",
        "modeTabs",
        "quickTabButton",
        "passwordTabButton",
        "pinSetupForm",
        "pinSetupPin",
        "pinSetupConfirm",
        "pinSetupEmail",
        "pinSetupDeviceId"
    ];

    initialize() {
        this.deviceAvailable = null;
        this.loginChoice = new URLSearchParams(location.search).get("method") === "password" ? "password" : null;
        this.deviceId = null;
        this.quickConfig = null;
        this.loginMode = "password";
        this.isSubmittingPinSetup = false;
        this._passwordSubmitHandler = this.handlePasswordSubmit.bind(this);
        this._quickSubmitHandler = this.handleQuickSubmit.bind(this);
        this._pinSetupSubmitHandler = this.handlePinSetupSubmit.bind(this);
    }

    connect() {
        this.deviceId = QuickLoginService.getOrCreateDeviceId();
        if (this.hasPasswordLoginTarget) QuickLoginService.beginPinMigration();
        this.quickConfig = this.hasPasswordLoginTarget ? null : this.getQuickConfigForDevice();
        this.handleServerQuickLoginDisabled();
        this.applyDeviceId();
        this.initializeLoginMode();
        this.syncQuickPreference();
        this.syncEmail();
        this.connectionChanged = () => this.renderLoginMethods();
        window.addEventListener("online", this.connectionChanged);
        window.addEventListener("offline", this.connectionChanged);
        this.renderLoginMethods();

        if (this.hasPasswordFormTarget) {
            this.passwordFormTarget.addEventListener("submit", this._passwordSubmitHandler);
        }
        if (this.hasQuickFormTarget) {
            this.quickFormTarget.addEventListener("submit", this._quickSubmitHandler);
        }
        if (this.hasPinSetupFormTarget) {
            this.pinSetupFormTarget.addEventListener("submit", this._pinSetupSubmitHandler);
        }
    }

    disconnect() {
        window.removeEventListener("online", this.connectionChanged);
        window.removeEventListener("offline", this.connectionChanged);
        if (this.hasPasswordFormTarget) {
            this.passwordFormTarget.removeEventListener("submit", this._passwordSubmitHandler);
        }
        if (this.hasQuickFormTarget) {
            this.quickFormTarget.removeEventListener("submit", this._quickSubmitHandler);
        }
        if (this.hasPinSetupFormTarget) {
            this.pinSetupFormTarget.removeEventListener("submit", this._pinSetupSubmitHandler);
        }
    }

    deviceAvailability(event) {
        this.deviceAvailable = event.detail.available;
        if (this.deviceAvailable) QuickLoginService.completePinMigration();
        this.renderLoginMethods();
    }

    /** Only one sign-in method is visible; password sign-in requires a connection. */
    renderLoginMethods() {
        if (!this.hasPasswordLoginTarget) return;
        const online = navigator.onLine;
        if (this.hasMigrationNoticeTarget) {
            this.migrationNoticeTarget.hidden = this.deviceAvailable !== false || !QuickLoginService.needsPinMigration();
        }
        const passwordHadFocus = this.passwordLoginTarget.contains(document.activeElement);
        const wasPassword = this.element.dataset.loginMethod === "password";
        if (!online) this.loginChoice = "device";
        const device = !online || (this.deviceAvailable && this.loginChoice !== "password");
        this.element.dataset.loginMethod = device ? "device" : "password";
        this.passwordLoginTarget.hidden = !online || this.deviceAvailable === null || device;
        this.deviceExperienceTarget.hidden = online && !device;
        this.deviceSwitchTarget.hidden = !online || !this.deviceAvailable;
        this.offlineNoticeTarget.hidden = online;
        this.offlineNoticeTarget.textContent = this.deviceAvailable
            ? `You’re offline. Use your PIN or passkey to unlock ${shortSiteTitle()}.`
            : "You’re offline. Connect to sign in and set up device unlock.";
        if (this.passwordLoginTarget.hidden && wasPassword) this.clearPasswordEntries();
        if (!online && passwordHadFocus) this.focusDeviceUnlock();
    }

    clearPasswordEntries() {
        this.passwordLoginTarget.querySelectorAll('input[type="password"]').forEach(input => { input.value = ""; });
    }

    focusDeviceUnlock() {
        const control = this.deviceExperienceTarget.querySelector('[data-offline-access-target="unlockPin"]:not([disabled])')
            || this.deviceExperienceTarget.querySelector('[data-offline-access-target="unlockButton"]');
        if (this.deviceAvailable && control) control.focus();
        else this.offlineNoticeTarget.focus();
    }

    showPasswordLogin(event) {
        event.preventDefault();
        if (!navigator.onLine) { this.renderLoginMethods(); return; }
        this.loginChoice = "password";
        this.switchToPassword(event);
        this.renderLoginMethods();
        this.emailTarget.focus();
    }

    switchToDevice(event) {
        event.preventDefault();
        this.loginChoice = "device";
        this.clearPasswordEntries();
        this.renderLoginMethods();
        this.focusDeviceUnlock();
    }

    applyDeviceId() {
        if (this.hasPasswordDeviceIdTarget) {
            this.passwordDeviceIdTarget.value = this.deviceId || "";
        }
        if (this.hasQuickDeviceIdTarget) {
            this.quickDeviceIdTarget.value = this.deviceId || "";
        }
        if (this.hasPinSetupDeviceIdTarget) {
            this.pinSetupDeviceIdTarget.value = this.deviceId || this.pinSetupDeviceIdTarget.value || "";
        }
    }

    getQuickConfigForDevice() {
        const quickConfig = QuickLoginService.getQuickConfig();
        if (
            !quickConfig ||
            quickConfig.deviceId !== this.deviceId ||
            typeof quickConfig.email !== "string" ||
            quickConfig.email.trim() === ""
        ) {
            return null;
        }

        return quickConfig;
    }

    handleServerQuickLoginDisabled() {
        if (!this.hasQuickDisabledTarget || this.quickDisabledTarget.value !== "1") {
            return;
        }

        const disabledEmail = this.hasQuickDisabledEmailTarget
            ? this.quickDisabledEmailTarget.value.trim()
            : "";

        QuickLoginService.clearQuickConfig();
        this.quickConfig = null;
        this.loginMode = "password";

        if (disabledEmail !== "") {
            QuickLoginService.setRememberedId(disabledEmail);
        }
    }

    initializeLoginMode() {
        const rememberedId = QuickLoginService.getRememberedId();

        if (this.quickConfig) {
            this.loginMode = "quick";
            this.setEmail(this.quickConfig.email);
        } else {
            this.loginMode = "password";
            this.setEmail(rememberedId);
        }

        if (this.hasRememberIdTarget) {
            this.rememberIdTarget.checked = rememberedId !== "";
        }

        this.applyMode();
    }

    currentEmail() {
        if (this.hasEmailTarget) {
            return this.emailTarget.value.trim();
        }

        if (this.hasPinSetupEmailTarget) {
            return this.pinSetupEmailTarget.value.trim();
        }

        return "";
    }

    setEmail(value) {
        if (!this.hasEmailTarget) {
            return;
        }

        this.emailTarget.value = String(value || "").trim();
    }

    applyMode() {
        const quickAvailable = Boolean(this.quickConfig);
        const quickActive = quickAvailable && this.loginMode === "quick";

        if (this.hasQuickPinTarget) {
            this.quickPinTarget.required = quickActive;
            this.quickPinTarget.disabled = !quickActive;
        }

        if (this.hasQuickExperienceTarget) {
            this.quickExperienceTarget.classList.toggle("d-none", !quickActive);
        }

        if (this.hasPasswordExperienceTarget) {
            this.passwordExperienceTarget.classList.toggle("d-none", quickActive);
        }

        if (this.hasModeTabsTarget) {
            this.modeTabsTarget.classList.toggle("d-none", !quickAvailable);
        }

        if (this.hasQuickTabButtonTarget) {
            this.quickTabButtonTarget.disabled = !quickAvailable;
            this.quickTabButtonTarget.classList.toggle("active", quickActive);
            this.quickTabButtonTarget.setAttribute("aria-selected", quickActive ? "true" : "false");
        }

        if (this.hasPasswordTabButtonTarget) {
            const passwordActive = !quickActive;
            this.passwordTabButtonTarget.classList.toggle("active", passwordActive);
            this.passwordTabButtonTarget.setAttribute("aria-selected", passwordActive ? "true" : "false");
        }

        if (this.hasQuickLoginLabelTarget) {
            this.quickLoginLabelTarget.textContent = quickAvailable
                ? `Enter your PIN to use quick login as ${this.quickConfig.email}.`
                : "Enter your PIN to use quick login on this device.";
        }
    }

    switchToPassword(event) {
        event.preventDefault();
        const rememberedId = QuickLoginService.getRememberedId();
        const fallbackEmail = this.quickConfig ? this.quickConfig.email : "";
        const email = rememberedId !== "" ? rememberedId : fallbackEmail;

        this.setEmail(email);
        this.loginMode = "password";
        this.applyMode();
        this.syncEmail();
    }

    switchToQuick(event) {
        event.preventDefault();
        if (!this.quickConfig) {
            return;
        }

        this.loginMode = "quick";
        this.setEmail(this.quickConfig.email);
        this.applyMode();
        this.syncEmail();
    }

    syncQuickPreference() {
        if (!this.hasQuickEnableTarget || !this.hasRememberIdTarget) {
            return;
        }

        const quickSelected = this.quickEnableTarget.checked;
        if (quickSelected) {
            this.rememberIdTarget.checked = true;
            this.rememberIdTarget.disabled = true;

            return;
        }

        this.rememberIdTarget.disabled = false;
    }

    syncEmail() {
        const quickEmail = this.quickConfig ? this.quickConfig.email : this.currentEmail();
        if (this.hasQuickEmailTarget) {
            this.quickEmailTarget.value = quickEmail;
        }
        if (this.hasPinSetupEmailTarget && this.hasEmailTarget) {
            this.pinSetupEmailTarget.value = this.currentEmail();
        }
    }

    handlePasswordSubmit(event) {
        if (!navigator.onLine) { event?.preventDefault(); this.renderLoginMethods(); return; }
        const email = this.currentEmail();
        const quickSelected = this.hasQuickEnableTarget && this.quickEnableTarget.checked;
        if (quickSelected) {
            this.syncQuickPreference();
        }

        const rememberSelected = (this.hasRememberIdTarget && this.rememberIdTarget.checked) || quickSelected;
        if (rememberSelected || quickSelected) {
            QuickLoginService.clearLoginState();
        }

        if (rememberSelected && email !== "") {
            QuickLoginService.setRememberedId(email);
        } else {
            QuickLoginService.clearRememberedId();
        }
    }

    handleQuickSubmit(event) {
        if (!navigator.onLine) { event?.preventDefault(); this.renderLoginMethods(); return; }
        const email = this.quickConfig ? this.quickConfig.email : this.currentEmail();
        if (this.hasQuickEmailTarget) {
            this.quickEmailTarget.value = email;
        }

        if (email !== "") {
            QuickLoginService.setRememberedId(email);
        }
    }

    async handlePinSetupSubmit(event) {
        if (
            this.isSubmittingPinSetup ||
            !this.hasPinSetupPinTarget ||
            !this.hasPinSetupConfirmTarget ||
            !this.hasPinSetupFormTarget
        ) {
            return;
        }

        event.preventDefault();

        const pin = this.pinSetupPinTarget.value.trim();
        const confirmPin = this.pinSetupConfirmTarget.value.trim();
        if (!/^\d{4,10}$/.test(pin) || pin !== confirmPin) {
            return;
        }

        const email = this.hasPinSetupEmailTarget ? this.pinSetupEmailTarget.value.trim() : "";
        const deviceId = this.hasPinSetupDeviceIdTarget
            ? this.pinSetupDeviceIdTarget.value.trim()
            : this.deviceId;

        this.isSubmittingPinSetup = true;
        try {
            const savedConfig = await QuickLoginService.saveQuickConfig({
                email,
                deviceId,
                pin
            });
            if (savedConfig === null) {
                this.isSubmittingPinSetup = false;
                return;
            }

            if (email !== "") {
                QuickLoginService.setRememberedId(email);
            }
            this.pinSetupFormTarget.submit();
        } catch (error) {
            this.isSubmittingPinSetup = false;
            console.error("Failed to save quick login configuration.", error);
        }
    }
}

if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["login-device-auth"] = LoginDeviceAuthController;

export default LoginDeviceAuthController;
