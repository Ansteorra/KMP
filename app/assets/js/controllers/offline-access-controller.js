import { currentOfflineContext } from '../services/offline-data-service.js';
import { preparePasskeyLogin, submitPasskeyLogin, passkeyRequest, decodePasskeyBytes, removePasskey } from '../services/passkey-auth-service.js';
import { tracePasskey, tracePasskeyError } from '../services/passkey-debug-service.js';
import { shortSiteTitle } from '../services/app-branding-service.js';
import { Controller } from '@hotwired/stimulus';
import vault from '../services/offline-vault-service.js';
import { prepareOfflineShell, updateTrustedDevice, offlineStatus } from '../services/offline-runtime-service.js';

import { verifyDevicePassword, loginWithSavedPassword } from '../services/device-login-service.js';
import { checkPasskeySupport, forgetPasskeyFailure, unavailablePasskey } from '../services/passkey-support-service.js';
import QuickLoginService from '../services/quick-login-service.js';
import { devicePromptKey, devicePromptDismissed, rememberDevicePromptChoice } from '../services/device-prompt-preference-service.js';

/** Guide personal-device setup and show explicit protection and offline-readiness results. */
class OfflineAccessController extends Controller {
    static targets = ['status', 'link', 'choice', 'forget', 'setup', 'password', 'passwordFields', 'method', 'pinFields', 'pin', 'confirm', 'continue', 'unlock', 'unlockPin', 'unlockButton', 'wizard', 'stepLabel', 'stepHeading', 'protectionFields', 'methodHelp', 'next', 'back', 'success', 'successHeading', 'successMethod', 'readiness', 'retry', 'setupProgress', 'setupError', 'setupErrorMessage', 'pinFallback', 'methodChoice', 'availability', 'recheck', 'passwordSwitch', 'migration', 'migrationMessage', 'trustButton', 'onlineOnly', 'addPin'];

    connect() {
        this.connected = true;
        QuickLoginService.beginPinMigration();
        this.update = event => {
            if (event?.type === 'online') { this.serverReachable = undefined; this.loginPreparationAttempted = false; }
            this.updatePasswordSwitch(); this.render();
        };
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
            this.record = record;
            this.publishDeviceAvailability(record);
            if (record?.snapshotSaved) ready = await this.shellReady();
        } catch { this.publishDeviceAvailability(null); }
        if (!this.connected || this.renderId !== id) return;
        let context;
        try { context = JSON.parse(document.querySelector('meta[name="kmp-offline-session"]')?.content || 'null'); } catch { /* Remain hidden. */ }
        const eligible = context?.owner && !context.impersonating;
        this.signedIn = !!eligible;
        const trusted = record?.wrapper.method === 'trusted';
        const protectedDevice = !!record?.wrapper.unlockMethod;
        const locked = protectedDevice && !vault.key;
        const settings = !!this.element.closest('turbo-frame');
        // A locked local copy is not a reason to ask an online, signed-in user to log in again.
        const signedInPage = !!context?.owner && navigator.onLine && !this.isLoginPage && !settings;
        const showUnlock = locked && !signedInPage && !this.setupStep && !this.completed;
        const onlineOnly = record?.wrapper.unlockMethod === 'passkey';
        const hybrid = record?.wrapper.unlockMethod === 'passkey-pin';
        const serverLogin = !!record?.wrapper.authentication && navigator.onLine && !eligible && this.serverReachable !== false;
        if (serverLogin && !this.loginPreparationAttempted) this.prepareLogin();
        if (this.hasAddPinTarget) this.addPinTarget.hidden = !onlineOnly || !eligible || !!this.setupStep || this.completed;
        if (protectedDevice) QuickLoginService.completePinMigration();
        const dismissed = devicePromptDismissed() || (!!devicePromptKey() && this.declinedPromptKey === devicePromptKey());
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
        if ((this.setupStep || this.completed) && (!eligible || (this.completed && (!protectedDevice || (!vault.key && !onlineOnly))))) this.resetSetup();
        this.element.hidden = (signedInPage && locked && !this.setupStep && !this.completed)
            || (!showMigration && !record && !eligible)
            || (eligible && dismissed && !settings && !protectedDevice && !this.setupStep && !this.completed);
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
        if (hybrid) this.statusTarget.textContent = serverLogin ? 'Sign in with your passkey. No PIN is needed online.'
            : 'Use your passkey, then your offline PIN to unlock saved information. Unlock here while online to refresh your offline copy.';
        if (onlineOnly) this.statusTarget.textContent = 'Your passkey works online. Offline access is unavailable until you add an offline PIN in Security.';
        if (trusted && !protectedDevice && eligible) this.statusTarget.textContent = 'Add a PIN or passkey to keep this device trusted after you log out.';
        if (trusted && !protectedDevice && !eligible && localStorage.getItem('kmp.offline.signedOut') === '1') {
            this.statusTarget.textContent = 'Sign in online once to add a PIN or passkey to this trusted device.';
            this.linkTarget.hidden = true;
        }
        if (this.hasUnlockTarget) {
            this.unlockTarget.hidden = !showUnlock;
            const needsPin = record?.wrapper.unlockMethod === 'pin' || (hybrid && !serverLogin && vault.hasOfflineAuthentication(record));
            this.unlockPinTarget.hidden = !needsPin;
            this.unlockPinTarget.required = showUnlock && needsPin;
            this.unlockPinTarget.disabled = !this.unlockPinTarget.required;
            this.unlockTarget.querySelector('label').hidden = this.unlockPinTarget.hidden;
            this.unlockButtonTarget.textContent = serverLogin ? 'Sign in with passkey'
                : !needsPin && record?.wrapper.authentication ? 'Continue with passkey'
                    : record?.wrapper.unlockMethod === 'device' ? 'Unlock with passkey' : `Unlock ${shortSiteTitle()}`;
            this.unlockButtonTarget.disabled = (onlineOnly && (!navigator.onLine || this.serverReachable === false)) || (serverLogin && !!this.preparingLogin);
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
        if (pin && this.ownsEnrollment && !this.hybridSetup) { vault.pendingDevice = null; this.ownsEnrollment = false; }
        this.pinFieldsTarget.hidden = !pin;
        this.pinTarget.disabled = this.confirmTarget.disabled = !pin || this.setupStep !== 2;
        this.pinTarget.required = this.confirmTarget.required = pin && this.setupStep === 2;
        this.methodHelpTarget.textContent = pin
            ? `Choose a PIN you can remember. This PIN is just for ${shortSiteTitle()} on this device. Avoid easy guesses like 123456.`
            : `Choose where to save your passkey when your browser asks. Passkeys can also unlock encrypted offline information; some password managers need a separate offline PIN. We’ll check your passkey and guide you through the options.`;
        this.nextTarget.hidden = this.setupStep === 2 && !pin;
        this.nextTarget.textContent = this.setupStep === 1 ? 'Continue' : 'Save PIN and trust device';
        this.continueTarget.hidden = this.setupStep !== 2 || pin;
        this.continueTarget.textContent = vault.pendingDevice && this.ownsEnrollment ? 'Finish passkey setup' : 'Set up passkey';
        this.stepHeadingTarget.textContent = this.setupStep === 1 ? 'Is this your personal device?'
            : this.passkeySupport?.available === false ? `Choose your ${shortSiteTitle()} PIN` : `Choose how to unlock ${shortSiteTitle()}`;
        if (this.hasOnlineOnlyTarget) this.onlineOnlyTarget.hidden = !this.hybridSetup || this.addingPin;
        if (this.hybridSetup) {
            this.methodChoiceTarget.hidden = true;
            this.availabilityTarget.hidden = false;
            this.availabilityTarget.textContent = 'Your passkey works for sign-in, but needs a PIN to decrypt offline information. Online sign-in will use only your passkey. Offline access will use your passkey, then this PIN.';
            this.stepHeadingTarget.textContent = 'Add a PIN for offline access';
            this.nextTarget.textContent = 'Save offline PIN';
        }
        this.render();
    }

    applyPasskeySupport(support) {
        this.passkeySupport = support;
        const unavailable = !support.available;
        const option = this.methodTarget.querySelector('[value=device]');
        option.hidden = option.disabled = unavailable;
        this.methodChoiceTarget.hidden = true;
        this.methodTarget.value = unavailable ? 'pin' : 'device';
        this.availabilityTarget.hidden = !unavailable;
        this.availabilityTarget.textContent = support.reason === 'provider'
            ? `Passkey authentication could not be completed on this device. Use a ${shortSiteTitle()} PIN, or choose Check passkey support again and select another provider in the browser prompt. Your password check carries over.`
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
            this.focusAfterRun = support.available ? this.continueTarget : this.pinTarget;
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
        this.hybridSetup = this.addingPin = false;
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
            if (this.setupStep === 2) tracePasskeyError('setup-error', error);
            this.errorMessage = error.name === 'NotAllowedError'
                ? (this.setupStep ? `Passkey setup wasn’t completed. Try again, or choose ${shortSiteTitle()} PIN instead.` : 'Passkey authentication wasn’t completed. Try your passkey again.') : error.message;
            if (this.setupStep === 2 && (error.code === 'PASSKEY_UNAVAILABLE' || error.name === 'OperationError')) {
                if (error.name === 'OperationError') unavailablePasskey('Passkey verification failed.');
                this.applyPasskeySupport({ available: false, reason: 'provider' });
                this.setupProgressTarget.hidden = true;
                this.focusAfterRun = this.pinTarget;
            } else if (this.setupStep) {
                if (error.name === 'OperationError') this.errorMessage = `This passkey could not reopen your protected information. Try again, or use a ${shortSiteTitle()} PIN on this device.`;
                this.setupErrorTarget.hidden = false;
                this.setupErrorMessageTarget.textContent = this.errorMessage;
                this.setupProgressTarget.hidden = true;
                this.pinFallbackTarget.hidden = this.setupStep !== 2 || this.methodTarget.value !== 'device';
                this.setupErrorMessageTarget.focus();
            } else {
                await this.render();
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
                try { await prepareOfflineShell(); }
                catch { /* Completion reports offline readiness separately; online-only setup can continue. */ }
                if (!this.connected) return;
                if (verified.generation !== vault.generation) throw new Error('Your sign-in changed. Start device setup again.');
                this.pendingSetup = verified;
                if (this.addingPin) {
                    await vault.prepareExistingPasskey(verified.context, verified.login);
                    this.ownsEnrollment = true;
                }
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
            if (this.hybridSetup) await vault.finishPasskeyWithPin(this.pinTarget.value);
            else await vault.enroll(verified.context, 'pin', this.pinTarget.value, verified.login);
            await this.completeSetup(this.hybridSetup ? 'passkey-pin' : 'pin');
        });
    }

    async continueSetup() {
        tracePasskey('setup-click', { pendingCredential: !!vault.pendingDevice,
            generationMatches: this.pendingSetup?.generation === vault.generation });
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
                : await vault.enroll(this.pendingSetup.context, 'device', '', this.pendingSetup.login, this.pendingSetup.passkey);
            if (!this.connected) { vault.pendingDevice = null; return; }
            tracePasskey(step === 'pin' ? 'setup-needs-pin' : step === 'complete' ? 'setup-complete' : step === 'wrap' ? 'setup-wait-wrap' : 'setup-wait-verify');
            if (step === 'pin') {
                this.hybridSetup = true;
                this.methodTarget.value = 'pin';
                this.chooseMethod();
                this.focusAfterRun = this.pinTarget;
            } else if (step === 'complete') await this.completeSetup('device');
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
        rememberDevicePromptChoice(false);
        this.declinedPromptKey = null;
        QuickLoginService.completePinMigration();
        this.resetSetup();
        this.completed = true;
        this.completedMethod = method;
        this.element.dataset.deviceSetupState = 'complete';
        this.setWizardActive(true);
        this.saving = true;
        this.successTarget.hidden = false;
        this.successMethodTarget.textContent = method === 'device'
            ? 'Your passkey is set up for online sign-in and offline unlock.'
            : method === 'passkey' ? 'Your passkey is ready for online sign-in.'
                : method === 'passkey-pin' ? 'Use your passkey online. Offline, use your passkey followed by your offline PIN.' : `Your ${shortSiteTitle()} PIN is set up and ready to use.`;
        this.renderCompletion(false);
        this.successHeadingTarget.focus();
        navigator.storage?.persist?.().catch(() => false);
        await this.render();
        if (method !== 'passkey') await this.retrySave();
    }

    renderCompletion(ready) {
        if (this.completedMethod === 'passkey') {
            this.readinessTarget.textContent = 'Online only: this device cannot open saved information offline. Add an offline PIN later in Security.';
            this.retryTarget.hidden = true;
            return;
        }
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
            const record = this.record;
            if (record?.wrapper.authentication && navigator.onLine && !this.signedIn && this.serverReachable !== false) {
                if (!this.loginOptions || this.loginOptions.until <= Date.now()) {
                    await this.prepareLogin();
                    throw new Error(this.loginOptions ? 'Sign-in is ready. Tap Sign in with passkey again.'
                        : 'The server could not be reached. Continue with your passkey to open saved offline information.');
                }
                const options = this.loginOptions;
                this.loginOptions = null;
                const request = passkeyRequest(record.wrapper.authentication, decodePasskeyBytes(options.challenge));
                const generation = vault.generation;
                const credential = await vault.credentialRequest('get', request);
                const destination = await submitPasskeyLogin(credential, options);
                if (!this.connected || generation !== vault.generation) return;
                this.navigating = true;
                this.navigate(destination);
                return;
            }
            if (record?.wrapper.unlockMethod === 'passkey-pin' && !vault.hasOfflineAuthentication(record)) {
                await vault.authenticateOffline(record);
                await this.render();
                this.focusAfterRun = this.unlockPinTarget;
                return;
            }
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

    async prepareLogin() {
        if (this.loginOptions?.until > Date.now()) return;
        this.loginPreparationAttempted = true;
        if (!this.preparingLogin) this.preparingLogin = preparePasskeyLogin().then(options => {
            if (this.connected) { this.loginOptions = options; this.serverReachable = true; }
        }).catch(() => { this.loginOptions = null; this.serverReachable = false; }).finally(() => { this.preparingLogin = null; if (this.connected) this.render(); });
        return this.preparingLogin;
    }

    addOfflinePin() {
        this.trust();
        this.addingPin = true;
    }

    async finishOnlineOnly() {
        await this.run(async () => {
            await vault.finishOnlinePasskey();
            tracePasskey('setup-online-only');
            await this.completeSetup('passkey');
        });
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
        const remembered = rememberDevicePromptChoice(true);
        this.declinedPromptKey = devicePromptKey();
        this.choiceTarget.hidden = true;
        this.statusTarget.textContent = remembered
            ? `This browser will use ${shortSiteTitle()} online. We’ll remember your choice after logout. You can trust it later in Security.`
            : `This browser will use ${shortSiteTitle()} online. Your browser could not save this choice for future visits. You can trust it later in Security.`;
        this.statusTarget.focus();
    }

    async forget() {
        if (!await window.KMP_accessibility.confirm('Stop trusting this device? Saved information and any RSVPs waiting to send will be removed.')) return;
        await this.run(async () => {
            const record = await vault.metadata();
            if (record?.wrapper.authentication) {
                if (!navigator.onLine || !this.signedIn) throw new Error('Connect and sign in to remove this device’s passkey in Security.');
                await removePasskey(record.wrapper.authentication, await currentOfflineContext());
            }
            await vault.clear();
            rememberDevicePromptChoice(true, record?.owner);
            this.declinedPromptKey = devicePromptKey();
            await this.render();
            this.statusTarget.textContent = 'This device is no longer trusted. Saved information has been removed.';
            this.statusTarget.focus();
        });
    }
}
window.Controllers ||= {};
window.Controllers['offline-access'] = OfflineAccessController;
export default OfflineAccessController;
