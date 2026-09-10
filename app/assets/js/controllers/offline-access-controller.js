import { shortSiteTitle } from '../services/app-branding-service.js';
import { Controller } from '@hotwired/stimulus';
import vault from '../services/offline-vault-service.js';
import { prepareOfflineShell, updateTrustedDevice, offlineStatus } from '../services/offline-runtime-service.js';

import { verifyDevicePassword, loginWithSavedPassword } from '../services/device-login-service.js';
import { checkPasskeySupport, forgetPasskeyFailure, unavailablePasskey } from '../services/passkey-support-service.js';
import QuickLoginService from '../services/quick-login-service.js';

/** Guide personal-device setup and show explicit protection and offline-readiness results. */
class OfflineAccessController extends Controller {
    static targets = ['status', 'link', 'choice', 'forget', 'setup', 'password', 'passwordFields', 'method', 'pinFields', 'pin', 'confirm', 'continue', 'unlock', 'unlockPin', 'unlockButton', 'wizard', 'stepLabel', 'stepHeading', 'protectionFields', 'methodHelp', 'next', 'back', 'success', 'successHeading', 'successMethod', 'readiness', 'retry', 'setupProgress', 'setupError', 'setupErrorMessage', 'pinFallback', 'methodChoice', 'availability', 'recheck', 'passwordSwitch', 'migration', 'migrationMessage', 'trustButton'];

    connect() {
        this.connected = true;
        QuickLoginService.beginPinMigration();
        this.update = () => { this.updatePasswordSwitch(); this.render(); };
        this.updatePasswordSwitch();
        window.addEventListener('kmp:offline-state', this.update);
        window.addEventListener('kmp:offline-progress', this.update);
        window.addEventListener('online', this.update);
        window.addEventListener('offline', this.update);
        this.render();
    }

    disconnect() {
        this.connected = false;
        window.removeEventListener('kmp:offline-state', this.update);
        window.removeEventListener('kmp:offline-progress', this.update);
        window.removeEventListener('online', this.update);
        window.removeEventListener('offline', this.update);
        this.cancelStatus?.();
        this.cancelSetup();
    }

    async shellReady() {
        const registration = await navigator.serviceWorker?.getRegistration?.('/');
        if (!registration?.active || !this.connected) return false;
        return new Promise(resolve => {
            const channel = new MessageChannel();
            const finish = ready => {
                clearTimeout(timer);
                channel.port1.close();
                this.cancelStatus = null;
                resolve(ready);
            };
            const timer = setTimeout(() => finish(false), 3000);
            this.cancelStatus = () => finish(false);
            channel.port1.onmessage = event => finish(event.data?.ready === true);
            registration.active.postMessage({ type: 'OFFLINE_STATUS' }, [channel.port2]);
        });
    }

    async render() {
        const id = Symbol();
        this.renderId = id;
        this.cancelStatus?.();
        let record;
        let ready = false;
        try {
            record = await vault.metadata();
            if (!this.connected || this.renderId !== id) return;
            this.publishDeviceAvailability(record);
            if (record?.snapshotSaved) ready = await this.shellReady();
        } catch { this.publishDeviceAvailability(null); }
        if (!this.connected || this.renderId !== id) return;
        let context;
        try { context = JSON.parse(document.querySelector('meta[name="kmp-offline-session"]')?.content || 'null'); } catch { /* Remain hidden. */ }
        const eligible = context?.owner && !context.impersonating;
        const trusted = record?.wrapper.method === 'trusted';
        const protectedDevice = !!record?.wrapper.unlockMethod;
        const locked = protectedDevice && !vault.key;
        const settings = !!this.element.closest('turbo-frame');
        // A locked local copy is not a reason to ask an online, signed-in user to log in again.
        const signedInPage = !!context?.owner && navigator.onLine && !this.isLoginPage && !settings;
        const showUnlock = locked && !signedInPage;
        if (protectedDevice) QuickLoginService.completePinMigration();
        const dismissed = sessionStorage.getItem('kmp.offline.declined') === '1';
        const migrating = !protectedDevice && QuickLoginService.needsPinMigration();
        const showMigration = migrating && (!eligible || !dismissed || !!this.element.closest('turbo-frame')) && !this.setupStep && !this.completed
            && !this.element.closest('[data-controller~="login-device-auth"]');
        if (this.hasMigrationTarget) {
            this.migrationTarget.hidden = !showMigration;
            this.migrationMessageTarget.textContent = eligible
                ? 'You’re signed in. Your old PIN no longer works with the updated device unlock. Set up a new PIN or passkey on this personal device. We’ll guide you through it, and it will work online and offline.'
                : 'Your old PIN no longer works with the updated device unlock. Connect to the internet and sign in with your email and password once. Then we’ll help you set up a new PIN or passkey for online and offline access.';
        }
        if (this.hasTrustButtonTarget) this.trustButtonTarget.textContent = migrating ? 'Set up new PIN or passkey' : 'Trust this personal device';
        if ((this.setupStep || this.completed) && (!eligible || (this.completed && (!protectedDevice || !vault.key)))) this.resetSetup();
        this.element.hidden = (signedInPage && locked)
            || (!showMigration && !record && (!eligible || (dismissed && !settings)));
        if (this.hasChoiceTarget) this.choiceTarget.hidden = !eligible || protectedDevice || !!this.setupStep || this.completed;
        const compact = !this.completed && !this.setupStep && protectedDevice && !locked && eligible && !settings;
        this.element.classList.toggle('card', !compact);
        this.element.classList.toggle('my-3', !compact);
        this.element.classList.toggle('my-2', compact);
        this.element.querySelector('h2')?.toggleAttribute('hidden', compact);
        this.element.firstElementChild?.classList.toggle('card-body', !compact);
        if (this.hasForgetTarget) this.forgetTarget.hidden = !trusted || (!settings && !locked);
        this.linkTarget.hidden = !record || protectedDevice || compact || locked || !!this.setupStep || this.completed;
        if (this.completed && this.hasForgetTarget) this.forgetTarget.hidden = true;
        this.statusTarget.textContent = trusted
            ? (compact && ready && record.expiresAt > Date.now() ? 'Available offline on this device.' : offlineStatus.message || (ready && record.expiresAt > Date.now() ? 'Saved on this device. Ready offline.'
                : `Your device is trusted. ${shortSiteTitle()} will update your saved information when you’re signed in and connected.`))
            : record ? 'Open your previously saved information to switch to automatic access.'
                : 'Keep your card, RSVPs, and events ready without internet. Protect this personal device with a PIN or passkey.';
        if (protectedDevice && locked) this.statusTarget.textContent = `Unlock ${shortSiteTitle()} with your PIN or passkey. It works online and offline.`;
        if (trusted && !protectedDevice && eligible) this.statusTarget.textContent = 'Add a PIN or passkey to keep this device trusted after you log out.';
        if (trusted && !protectedDevice && !eligible && localStorage.getItem('kmp.offline.signedOut') === '1') {
            this.statusTarget.textContent = 'Sign in online once to add a PIN or passkey to this trusted device.';
            this.linkTarget.hidden = true;
        }
        if (this.hasUnlockTarget) {
            this.unlockTarget.hidden = !showUnlock;
            this.unlockPinTarget.hidden = record?.wrapper.unlockMethod !== 'pin';
            this.unlockPinTarget.required = showUnlock && record.wrapper.unlockMethod === 'pin';
            this.unlockPinTarget.disabled = !this.unlockPinTarget.required;
            this.unlockTarget.querySelector('label').hidden = this.unlockPinTarget.hidden;
            this.unlockButtonTarget.textContent = record?.wrapper.unlockMethod === 'device' ? 'Unlock with passkey' : `Unlock ${shortSiteTitle()}`;
        }
        this.linkTarget.textContent = trusted ? 'Open my card' : 'Open previously saved information';
        if (showMigration) this.statusTarget.textContent = '';
        if (this.setupStep) {
            this.statusTarget.textContent = '';
            this.setupProgressTarget.textContent = this.setupMessage || '';
            this.setupProgressTarget.hidden = !this.setupMessage || !!this.errorMessage;
        }
        else if (this.completed) {
            this.statusTarget.textContent = '';
            this.renderCompletion(ready && record?.snapshotSaved && record.expiresAt > Date.now());
        } else if (this.errorMessage) this.statusTarget.textContent = this.errorMessage;
        if (this.navigating) {
            this.statusTarget.textContent = `Opening ${shortSiteTitle()}…`;
            if (this.hasUnlockTarget) this.unlockTarget.hidden = true;
            if (this.hasForgetTarget) this.forgetTarget.hidden = true;
        }
        if (!this.busy && !this.navigating && protectedDevice && vault.key && this.isLoginPage && !this.usingPasswordLogin) await this.enterApp();
    }

    get isLoginPage() { return /^\/members\/login\/?$/i.test(location.pathname); }

    navigate(destination) { location.replace(destination); }

    /** Login owns routing; offline-data refresh continues on the destination page. */
    async enterApp() {
        if (this.navigating) return;
        this.navigating = true;
        const generation = vault.generation;
        this.linkTarget.hidden = true;
        this.statusTarget.textContent = `Opening ${shortSiteTitle()}…`;
        let destination = null;
        try {
            if (navigator.onLine) destination = await loginWithSavedPassword(true);
        } catch { /* A failed connection still permits the unlocked mobile copy. */ }
        if (!this.connected || !vault.key || generation !== vault.generation) {
            this.navigating = false;
            return;
        }
        this.navigate(destination || '/offline');
    }

    trust() {
        this.resetSetup();
        this.showStep(1);
    }

    showStep(step) {
        this.setupStep = step;
        this.setWizardActive(true);
        this.wizardTarget.hidden = false;
        this.choiceTarget.hidden = true;
        this.passwordFieldsTarget.hidden = this.passwordFieldsTarget.disabled = step !== 1;
        this.protectionFieldsTarget.hidden = this.protectionFieldsTarget.disabled = step !== 2;
        this.passwordTarget.required = step === 1;
        this.backTarget.hidden = step !== 2;
        this.stepLabelTarget.textContent = `Step ${step} of 3`;
        this.stepHeadingTarget.textContent = step === 1 ? 'Is this your personal device?' : `Choose how to unlock ${shortSiteTitle()}`;
        this.chooseMethod();
        this.stepHeadingTarget.focus();
        this.render();
    }

    chooseMethod() {
        this.errorMessage = this.setupMessage = '';
        this.setupErrorTarget.hidden = true;
        const pin = this.methodTarget.value === 'pin';
        if (pin && this.ownsEnrollment) { vault.pendingDevice = null; this.ownsEnrollment = false; }
        this.pinFieldsTarget.hidden = !pin;
        this.pinTarget.disabled = this.confirmTarget.disabled = !pin || this.setupStep !== 2;
        this.pinTarget.required = this.confirmTarget.required = pin && this.setupStep === 2;
        this.methodHelpTarget.textContent = pin
            ? `Choose a PIN you can remember. This PIN is just for ${shortSiteTitle()} on this device. Avoid easy guesses like 123456.`
            : `Your device will guide you through using Face ID, a fingerprint, or its screen lock. This creates a passkey. If it isn’t supported, choose ${shortSiteTitle()} PIN instead.`;
        this.nextTarget.hidden = this.setupStep === 2 && !pin;
        this.nextTarget.textContent = this.setupStep === 1 ? 'Continue' : 'Save PIN and trust device';
        this.continueTarget.hidden = this.setupStep !== 2 || pin;
        this.continueTarget.textContent = vault.pendingDevice && this.ownsEnrollment ? 'Finish passkey setup' : 'Set up passkey';
        this.stepHeadingTarget.textContent = this.setupStep === 1 ? 'Is this your personal device?'
            : this.passkeySupport?.available === false ? `Choose your ${shortSiteTitle()} PIN` : `Choose how to unlock ${shortSiteTitle()}`;
        this.render();
    }

    applyPasskeySupport(support) {
        this.passkeySupport = support;
        const unavailable = !support.available;
        const option = this.methodTarget.querySelector('[value=device]');
        option.hidden = option.disabled = unavailable;
        this.methodChoiceTarget.hidden = unavailable;
        this.availabilityTarget.hidden = !unavailable;
        this.availabilityTarget.textContent = support.reason === 'provider'
            ? `Passkeys couldn’t protect offline information in this browser. Use a ${shortSiteTitle()} PIN to unlock ${shortSiteTitle()} online and offline. Your password check carries over.`
            : `Use a ${shortSiteTitle()} PIN on this device. It will unlock ${shortSiteTitle()} online and offline.`;
        this.recheckTarget.hidden = support.reason !== 'provider';
        if (unavailable) this.methodTarget.value = 'pin';
        this.chooseMethod();
    }

    async recheckPasskeys() {
        await this.run(async () => {
            forgetPasskeyFailure();
            const support = await checkPasskeySupport();
            if (!this.connected) return;
            this.applyPasskeySupport(support);
            this.focusAfterRun = support.available ? this.methodTarget : this.pinTarget;
        });
    }

    usePin() {
        this.methodTarget.value = 'pin';
        this.chooseMethod();
        this.pinTarget.focus();
    }

    setWizardActive(active) {
        this.element.dispatchEvent(new CustomEvent('kmp:device-setup', { bubbles: true, detail: { active } }));
    }

    resetSetup() {
        this.setWizardActive(false);
        this.setupStep = null;
        this.completed = false;
        this.pendingSetup = null;
        this.errorMessage = this.setupMessage = '';
        if (this.ownsEnrollment) vault.pendingDevice = null;
        this.ownsEnrollment = false;
        for (const name of ['password', 'pin', 'confirm']) {
            if (!this[`has${name[0].toUpperCase() + name.slice(1)}Target`]) continue;
            this[`${name}Target`].value = '';
            this[`${name}Target`].removeAttribute('aria-invalid');
        }
        if (this.hasWizardTarget) this.wizardTarget.hidden = true;
        if (this.hasSetupErrorTarget) this.setupErrorTarget.hidden = true;
        if (this.hasSetupProgressTarget) this.setupProgressTarget.hidden = true;
        if (this.hasSuccessTarget) this.successTarget.hidden = true;
    }

    cancelSetup() {
        this.resetSetup();
        if (this.connected) {
            this.choiceTarget.hidden = false;
            this.choiceTarget.querySelector('button')?.focus();
            this.render();
        }
    }

    back() {
        this.resetSetup();
        this.showStep(1);
    }

    async run(operation) {
        if (this.busy) return;
        this.busy = true;
        this.errorMessage = '';
        if (this.hasSetupErrorTarget) this.setupErrorTarget.hidden = true;
        this.element.setAttribute('aria-busy', 'true');
        const controls = [...this.element.querySelectorAll('[data-trust-control], [data-offline-access-target="method"]')];
        controls.forEach(control => { control.disabled = true; });
        try { await operation(); }
        catch (error) {
            this.errorMessage = error.name === 'NotAllowedError'
                ? `Passkey setup wasn’t completed. Try again, or choose ${shortSiteTitle()} PIN instead.` : error.message;
            if (this.setupStep === 2 && (error.code === 'PASSKEY_UNAVAILABLE' || error.name === 'OperationError')) {
                if (error.name === 'OperationError') unavailablePasskey('Passkey verification failed.');
                this.applyPasskeySupport({ available: false, reason: 'provider' });
                this.pinTarget.focus();
            } else if (this.setupStep) {
                if (error.name === 'OperationError') this.errorMessage = `This passkey could not reopen your protected information. Try again, or use a ${shortSiteTitle()} PIN on this device.`;
                this.setupErrorTarget.hidden = false;
                this.setupErrorMessageTarget.textContent = this.errorMessage;
                this.setupProgressTarget.hidden = true;
                this.pinFallbackTarget.hidden = this.setupStep !== 2 || this.methodTarget.value !== 'device';
                this.setupErrorMessageTarget.focus();
                console.info('[KMP device setup]', { stage: this.element.dataset.deviceSetupState || 'setup',
                    outcome: 'failed', error: ['NotAllowedError', 'OperationError', 'AbortError', 'SecurityError'].includes(error.name) ? error.name : 'SetupError' });
            } else {
                this.statusTarget.textContent = this.errorMessage;
                this.statusTarget.focus();
            }
        }
        finally {
            this.busy = false;
            controls.forEach(control => { control.disabled = false; });
            this.element.removeAttribute('aria-busy');
            if (this.errorMessage && this.setupStep === 2 && this.methodTarget.value === 'device') {
                this.continueTarget.textContent = vault.pendingDevice ? 'Finish passkey setup' : 'Try passkey setup again';
            }
            this.focusAfterRun?.focus();
            this.focusAfterRun = null;
        }
    }

    async setupDevice(event) {
        event.preventDefault();
        await this.run(async () => {
            if (this.setupStep === 1) {
                this.setupMessage = this.statusTarget.textContent = 'Checking your password and preparing this device…';
                const verified = await verifyDevicePassword(this.passwordTarget.value);
                await prepareOfflineShell();
                if (!this.connected) return;
                if (verified.generation !== vault.generation) throw new Error('Your sign-in changed. Start device setup again.');
                this.pendingSetup = verified;
                this.passwordTarget.value = '';
                const support = await checkPasskeySupport();
                if (!this.connected) return;
                this.showStep(2);
                this.applyPasskeySupport(support);
                return;
            }
            if (this.setupStep !== 2 || this.methodTarget.value !== 'pin') return;
            if (!/^\d{6,12}$/.test(this.pinTarget.value) || this.pinTarget.value !== this.confirmTarget.value) {
                this.confirmTarget.setAttribute('aria-invalid', 'true');
                throw new Error('Enter the same 6–12 digit PIN in both boxes.');
            }
            this.confirmTarget.removeAttribute('aria-invalid');
            const verified = this.pendingSetup;
            if (!verified || verified.generation !== vault.generation) throw new Error('Your sign-in changed. Start device setup again.');
            this.setupMessage = this.statusTarget.textContent = 'Saving your PIN…';
            await vault.enroll(verified.context, 'pin', this.pinTarget.value, verified.login);
            await this.completeSetup('pin');
        });
    }

    async continueSetup() {
        await this.run(async () => {
            if (!this.pendingSetup) throw new Error('Device setup was interrupted. Choose Back to confirm your password and try again.');
            if (this.pendingSetup.generation !== vault.generation) throw new Error('Your sign-in changed. Start device setup again.');
            this.ownsEnrollment = true;
            this.element.dataset.deviceSetupState = vault.pendingDevice ? 'verifying-passkey' : 'creating-passkey';
            this.setupMessage = vault.pendingDevice ? 'Checking your passkey. Unlock when your device asks.'
                : 'Creating your passkey. Complete all prompts from your browser, including any password-manager setup.';
            this.setupProgressTarget.hidden = false;
            this.setupProgressTarget.textContent = this.setupMessage;
            this.continueTarget.textContent = vault.pendingDevice ? 'Checking passkey…' : 'Waiting for your browser…';
            const step = vault.pendingDevice ? await vault.continueDeviceEnrollment()
                : await vault.enroll(this.pendingSetup.context, 'device', '', this.pendingSetup.login);
            if (!this.connected) { vault.pendingDevice = null; return; }
            if (step === 'complete') await this.completeSetup('device');
            else {
                this.element.dataset.deviceSetupState = step === 'wrap' ? 'passkey-created' : 'passkey-ready-to-check';
                this.stepHeadingTarget.textContent = 'Your passkey was created. Let’s check it.';
                this.continueTarget.textContent = 'Finish passkey setup';
                this.setupMessage = step === 'wrap'
                    ? `Your passkey is saved in your browser. Tap Finish passkey setup to connect it to ${shortSiteTitle()}. Your device may ask you to unlock twice to check offline access.`
                    : `Final check: tap Finish passkey setup and unlock once more. ${shortSiteTitle()} will then finish trusting this device.`;
                this.setupProgressTarget.textContent = this.setupMessage;
                this.focusAfterRun = this.continueTarget;
            }
        });
    }

    async completeSetup(method) {
        QuickLoginService.completePinMigration();
        this.resetSetup();
        this.completed = true;
        this.element.dataset.deviceSetupState = 'complete';
        this.setWizardActive(true);
        this.saving = true;
        this.successTarget.hidden = false;
        this.successMethodTarget.textContent = method === 'device'
            ? 'Your passkey is set up and ready to use.' : `Your ${shortSiteTitle()} PIN is set up and ready to use.`;
        this.renderCompletion(false);
        this.successHeadingTarget.focus();
        navigator.storage?.persist?.().catch(() => false);
        await this.render();
        await this.retrySave();
    }

    renderCompletion(ready) {
        this.readinessTarget.textContent = ready ? 'Ready offline. Your card, RSVPs, and events are saved on this device.'
            : this.saving ? `Saving your offline information… Keep ${shortSiteTitle()} open for a moment.`
                : `Your unlock is set up, but your offline information isn’t ready yet. Stay connected and try saving again. ${shortSiteTitle()} will also retry automatically.`;
        this.retryTarget.hidden = ready || this.saving;
    }

    async retrySave() {
        this.saving = true;
        this.retryTarget.disabled = true;
        this.renderCompletion(false);
        try { await updateTrustedDevice(true); }
        catch { /* Protection is saved; the completion panel explains how to retry data saving. */ }
        finally {
            this.saving = false;
            this.retryTarget.disabled = false;
            if (this.connected) await this.render();
        }
    }

    async done() {
        this.resetSetup();
        this.statusTarget.textContent = 'This device is trusted.';
        this.statusTarget.focus();
        await this.render();
    }

    async unlockDevice(event) {
        event.preventDefault();
        await this.run(async () => {
            await vault.unlock(this.unlockPinTarget.value);
            this.unlockPinTarget.value = '';
            if (this.isLoginPage) { await this.enterApp(); return; }
            try {
                if (navigator.onLine) await loginWithSavedPassword(true);
                await updateTrustedDevice(true);
            } catch { this.statusTarget.textContent = 'Your saved information is unlocked. Sign in with your password if your online password has changed.'; }
            await this.render();
        });
        this.unlockPinTarget.value = '';
    }

    publishDeviceAvailability(record) {
        if (!this.connected) return;
        this.element.dispatchEvent(new CustomEvent('kmp:device-login-availability', {
            bubbles: true, detail: { available: !!record?.wrapper.unlockMethod }
        }));
    }

    updatePasswordSwitch() {
        if (this.hasPasswordSwitchTarget) this.passwordSwitchTarget.hidden = !navigator.onLine;
    }

    get usingPasswordLogin() {
        return this.element.closest('[data-controller~="login-device-auth"]')?.dataset.loginMethod === 'password';
    }

    usePassword() {
        if (!navigator.onLine) return;
        if (this.element.closest('[data-controller~="login-device-auth"]')) {
            this.unlockPinTarget.value = '';
            this.element.dispatchEvent(new CustomEvent('kmp:password-login', { bubbles: true, cancelable: true }));
        } else location.assign('/members/login?method=password');
    }

    decline() {
        sessionStorage.setItem('kmp.offline.declined', '1');
        this.choiceTarget.hidden = true;
        this.statusTarget.textContent = `This browser will use ${shortSiteTitle()} online. You can trust it later in Security.`;
        this.statusTarget.focus();
    }

    async forget() {
        if (!await window.KMP_accessibility.confirm('Stop trusting this device? Saved information and any RSVPs waiting to send will be removed.')) return;
        await vault.clear();
        await this.render();
        this.statusTarget.textContent = 'This device is no longer trusted. Saved information has been removed.';
        this.statusTarget.focus();
    }
}
window.Controllers ||= {};
window.Controllers['offline-access'] = OfflineAccessController;
export default OfflineAccessController;
