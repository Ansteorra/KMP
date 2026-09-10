import QuickLoginService from '../../../assets/js/services/quick-login-service.js';
import OfflineAccessController from '../../../assets/js/controllers/offline-access-controller.js';
import { loginWithSavedPassword, verifyDevicePassword } from '../../../assets/js/services/device-login-service.js';
import { updateTrustedDevice, prepareOfflineShell } from '../../../assets/js/services/offline-runtime-service.js';
import vault from '../../../assets/js/services/offline-vault-service.js';
jest.mock('../../../assets/js/services/offline-vault-service.js', () => ({ __esModule: true, default: { metadata: jest.fn(), unlock: jest.fn() } }));
jest.mock('../../../assets/js/services/offline-runtime-service.js', () => ({ offlineStatus: { message: '' }, updateTrustedDevice: jest.fn(), prepareOfflineShell: jest.fn() }));
jest.mock('../../../assets/js/services/device-login-service.js', () => ({ loginWithSavedPassword: jest.fn(), verifyDevicePassword: jest.fn() }));
let controller;
beforeEach(() => {
    jest.resetAllMocks();
    controller = new OfflineAccessController();
    controller.connected = true;
    controller.element = document.createElement('section');
    sessionStorage.clear();
    localStorage.clear();
    Object.defineProperty(navigator, 'onLine', { configurable: true, value: true });
    controller.statusTarget = document.createElement('p');
    controller.linkTarget = document.createElement('a');
    controller.shellReady = jest.fn().mockResolvedValue(true);
});
test('an unlock method alone is not offline readiness', async () => {
    vault.metadata.mockResolvedValue({ wrapper: { method: 'trusted' }, expiresAt: Date.now() + 86400000 });
    await controller.render();
    expect(controller.shellReady).not.toHaveBeenCalled();
    expect(controller.statusTarget.textContent).toContain('Your device is trusted');
});
test('readiness requires both a saved snapshot and verified shell assets', async () => {
    vault.metadata.mockResolvedValue({ snapshotSaved: true, wrapper: { method: 'trusted' }, expiresAt: Date.now() + 86400000 });
    controller.shellReady.mockResolvedValue(false);
    await controller.render();
    expect(controller.statusTarget.textContent).toContain('Your device is trusted');
    controller.shellReady.mockResolvedValue(true);
    await controller.render();
    expect(controller.statusTarget.textContent).toContain('Ready offline');
    expect(controller.linkTarget.textContent).toBe('Open my card');
});
test('a late readiness check cannot replace the state of a cleared account', async () => {
    let finish;
    vault.metadata.mockResolvedValue({ snapshotSaved: true, wrapper: { method: 'trusted' }, expiresAt: Date.now() + 86400000 });
    controller.shellReady.mockImplementation(() => new Promise(resolve => { finish = resolve; }));
    const old = controller.render();
    await Promise.resolve();
    vault.metadata.mockResolvedValue(null);
    await controller.render();
    finish(true); await old;
    expect(controller.element.hidden).toBe(true);
    expect(controller.linkTarget.hidden).toBe(true);
});

describe('guided device setup', () => {
    beforeEach(() => {
        document.head.innerHTML = '<meta name="kmp-offline-session" content=\'{"owner":"member","epoch":"epoch"}\'>';
        document.body.replaceChildren(controller.element);
        for (const name of OfflineAccessController.targets) {
            if (['status', 'link'].includes(name)) continue;
            const tag = ['password', 'pin', 'confirm'].includes(name) ? 'input'
                : name === 'method' ? 'select' : ['continue', 'next', 'back', 'retry'].includes(name) ? 'button' : 'div';
            controller[`${name}Target`] = document.createElement(tag);
            controller[`has${name[0].toUpperCase() + name.slice(1)}Target`] = true;
            controller.element.append(controller[`${name}Target`]);
        }
        controller.element.append(controller.statusTarget, controller.linkTarget);
        controller.choiceTarget.innerHTML = '<button>Trust this personal device</button>';
        controller.unlockTarget.innerHTML = '<label>Device PIN</label>';
        controller.methodTarget.innerHTML = '<option value="device">Passkey</option><option value="pin">PIN</option>';
        controller.stepHeadingTarget.tabIndex = controller.successHeadingTarget.tabIndex = controller.statusTarget.tabIndex = controller.setupErrorMessageTarget.tabIndex = -1;
        vault.generation = 1;
        vault.key = {};
        vault.pendingDevice = null;
        vault.metadata.mockResolvedValue({ snapshotSaved: true, wrapper: { method: 'trusted', unlockMethod: 'device' }, expiresAt: Date.now() + 86400000 });
    });
    afterEach(() => { document.head.innerHTML = ''; document.body.innerHTML = ''; history.replaceState({}, '', '/'); });

    test('verifies the password and prepares offline assets before choosing an unlock method', async () => {
        const verified = { context: { owner: 'member', epoch: 'epoch' }, login: { email: 'member@example.test', password: 'setup input' }, generation: 1 };
        verifyDevicePassword.mockResolvedValue(verified);
        controller.showStep(1);
        controller.passwordTarget.value = 'setup input';
        const event = { preventDefault: jest.fn() };
        await controller.setupDevice(event);
        expect(event.preventDefault).toHaveBeenCalled();
        expect(verifyDevicePassword).toHaveBeenCalledWith('setup input');
        expect(prepareOfflineShell).toHaveBeenCalledTimes(1);
        expect(verifyDevicePassword.mock.invocationCallOrder[0]).toBeLessThan(prepareOfflineShell.mock.invocationCallOrder[0]);
        expect(controller.pendingSetup).toBe(verified);
        expect(controller.passwordTarget.value).toBe('');
        expect(controller.setupStep).toBe(2);
        expect(controller.stepHeadingTarget).toHaveFocus();
    });

    test('a rejected password stays on the first step with an accessible error', async () => {
        verifyDevicePassword.mockRejectedValue(new Error('Check your password and try again.'));
        controller.showStep(1);
        controller.passwordTarget.value = 'incorrect input';
        await controller.setupDevice({ preventDefault: jest.fn() });
        expect(prepareOfflineShell).not.toHaveBeenCalled();
        expect(controller.pendingSetup).toBeFalsy();
        expect(controller.setupStep).toBe(1);
        expect(controller.setupErrorMessageTarget).toHaveFocus();
        expect(controller.setupErrorMessageTarget.textContent).toBe('Check your password and try again.');
    });

    test.each(['/members/view/123', '/members/view-mobile-card'])('signed-in online page %s does not ask to unlock a locked copy', async path => {
        history.replaceState({}, '', path);
        vault.key = null;
        await controller.render();
        expect(controller.element.hidden).toBe(true);
        expect(controller.unlockTarget.hidden).toBe(true);
        expect(controller.unlockPinTarget.disabled).toBe(true);
        expect(vault.unlock).not.toHaveBeenCalled();
        expect(vault.key).toBeNull();
        Object.defineProperty(navigator, 'onLine', { configurable: true, value: false });
        await controller.render();
        expect(controller.element.hidden).toBe(false);
        expect(controller.unlockTarget.hidden).toBe(false);
    });

    test('uses configured branding in unlock, wizard and completion messages as plain text', async () => {
        const meta = document.createElement('meta');
        meta.name = 'kmp-short-site-title';
        meta.content = 'Guild & <Friends>';
        document.head.append(meta);
        vault.key = null;
        vault.metadata.mockResolvedValue({ wrapper: { method: 'trusted', unlockMethod: 'pin' }, expiresAt: Date.now() + 86400000 });
        await controller.render();
        expect(controller.unlockButtonTarget.textContent).toBe('Unlock Guild & <Friends>');
        controller.trust();
        controller.showStep(2);
        expect(controller.stepHeadingTarget.textContent).toBe('Choose how to unlock Guild & <Friends>');
        controller.saving = true;
        controller.renderCompletion(false);
        expect(controller.readinessTarget.textContent).toContain('Keep Guild & <Friends> open');
        expect(controller.element.querySelector('friends')).toBeNull();
    });

    test('explicit Security controls can still unlock the encrypted copy', async () => {
        history.replaceState({}, '', '/members/view/123');
        const frame = document.createElement('turbo-frame');
        document.body.append(frame);
        frame.append(controller.element);
        vault.key = null;
        await controller.render();
        expect(controller.element.hidden).toBe(false);
        expect(controller.unlockTarget.hidden).toBe(false);
    });

    test('signed-out login still offers unlock even while online', async () => {
        history.replaceState({}, '', '/members/login');
        document.head.innerHTML = '';
        vault.key = null;
        await controller.render();
        expect(controller.element.hidden).toBe(false);
        expect(controller.unlockTarget.hidden).toBe(false);
    });

    test('guides signed-in legacy users and retains the reminder through cancellation', async () => {
        localStorage.setItem(QuickLoginService.storageKeys.pinMigration, '1');
        vault.metadata.mockResolvedValue(null);
        await controller.render();
        expect(controller.migrationTarget.hidden).toBe(false);
        expect(controller.migrationMessageTarget.textContent).toContain('You’re signed in');
        expect(controller.trustButtonTarget.textContent).toBe('Set up new PIN or passkey');
        controller.trust();
        await controller.cancelSetup();
        expect(QuickLoginService.needsPinMigration()).toBe(true);
        expect(controller.migrationTarget.hidden).toBe(false);
        controller.decline();
        await controller.render();
        expect(controller.element.hidden).toBe(true);
        expect(QuickLoginService.needsPinMigration()).toBe(true);
    });

    test('public offline recovery explains migration without offering password entry', async () => {
        document.head.innerHTML = '';
        localStorage.setItem(QuickLoginService.storageKeys.pinMigration, '1');
        vault.metadata.mockResolvedValue(null);
        await controller.render();
        expect(controller.element.hidden).toBe(false);
        expect(controller.migrationTarget.hidden).toBe(false);
        expect(controller.migrationMessageTarget.textContent).toContain('Connect to the internet');
        expect(controller.choiceTarget.hidden).toBe(true);
    });

    test('an already protected device clears a stale migration reminder', async () => {
        localStorage.setItem(QuickLoginService.storageKeys.pinMigration, '1');
        await controller.render();
        expect(controller.migrationTarget.hidden).toBe(true);
        expect(QuickLoginService.needsPinMigration()).toBe(false);
    });

    test('starts with personal-device explanation and only enables the current step', () => {
        controller.trust();
        expect(controller.stepLabelTarget.textContent).toBe('Step 1 of 3');
        expect(controller.passwordFieldsTarget.hidden).toBe(false);
        expect(controller.protectionFieldsTarget.disabled).toBe(true);
        expect(controller.stepHeadingTarget).toHaveFocus();
        controller.showStep(2);
        expect(controller.passwordFieldsTarget.disabled).toBe(true);
        expect(controller.protectionFieldsTarget.hidden).toBe(false);
        expect(controller.continueTarget.hidden).toBe(false);
    });

    test('switching to PIN clears incomplete passkey setup and enables PIN controls', () => {
        controller.setupStep = 2;
        controller.ownsEnrollment = true;
        vault.pendingDevice = { sensitive: true };
        controller.pendingSetup = { login: {} };
        controller.methodTarget.value = 'pin';
        controller.chooseMethod();
        expect(vault.pendingDevice).toBeNull();
        expect(controller.pendingSetup).not.toBeNull();
        expect(controller.pinTarget.required).toBe(true);
        expect(controller.continueTarget.hidden).toBe(true);
    });

    test('background progress preserves completion until Done, which returns focus to status', async () => {
        controller.completed = true;
        controller.successTarget.hidden = false;
        controller.successMethodTarget.textContent = 'Your passkey is set up and ready to use.';
        await controller.render();
        expect(controller.successTarget.hidden).toBe(false);
        expect(controller.successMethodTarget.textContent).toContain('Your passkey is set up');
        expect(controller.readinessTarget.textContent).toContain('Ready offline');
        expect(controller.linkTarget.hidden).toBe(true);
        let finish;
        controller.render = jest.fn(() => new Promise(resolve => { finish = resolve; }));
        const done = controller.done();
        expect(controller.successTarget.hidden).toBe(true);
        expect(controller.statusTarget).toHaveFocus();
        finish();
        await done;
    });

    test('does not claim offline readiness when data saving has not finished or fails', () => {
        controller.saving = true;
        controller.renderCompletion(false);
        expect(controller.readinessTarget.textContent).toContain('Saving your offline information');
        expect(controller.retryTarget.hidden).toBe(true);
        controller.saving = false;
        controller.renderCompletion(false);
        expect(controller.readinessTarget.textContent).toContain('isn’t ready yet');
        expect(controller.retryTarget.hidden).toBe(false);
    });

    test('cancellation clears sensitive setup fields and pending enrollment', async () => {
        controller.passwordTarget.value = 'temporary setup input';
        controller.pinTarget.value = controller.confirmTarget.value = '582694';
        controller.pendingSetup = { login: {} };
        controller.ownsEnrollment = true;
        vault.pendingDevice = {};
        controller.cancelSetup();
        await controller.render();
        expect(controller.pendingSetup).toBeNull();
        expect(vault.pendingDevice).toBeNull();
        expect(controller.passwordTarget.value).toBe('');
        expect(controller.pinTarget.value).toBe('');
        expect(controller.wizardTarget.hidden).toBe(true);
    });

    test('locking the vault dismisses an obsolete success screen', async () => {
        controller.completed = true;
        vault.key = null;
        await controller.render();
        expect(controller.completed).toBe(false);
        expect(controller.successTarget.hidden).toBe(true);
    });
    test('known unsupported passkeys switch directly to PIN and preserve the verified password', async () => {
        controller.setupStep = 2;
        const verified = { login: {} };
        controller.pendingSetup = verified;
        await controller.run(async () => { throw Object.assign(new Error('Unsupported'), { code: 'PASSKEY_UNAVAILABLE' }); });
        expect(controller.methodChoiceTarget.hidden).toBe(true);
        expect(controller.methodTarget.value).toBe('pin');
        expect(controller.pinTarget).toHaveFocus();
        expect(controller.pendingSetup).toBe(verified);
        expect(controller.continueTarget.hidden).toBe(true);
    });

    test('cancelled browser prompts offer retry without claiming incompatibility or completion', async () => {
        controller.setupStep = 2;
        await controller.run(async () => { throw new DOMException('Cancelled', 'NotAllowedError'); });
        expect(controller.setupErrorMessageTarget.textContent).toContain('wasn’t completed');
        expect(controller.setupErrorMessageTarget).toHaveFocus();
        expect(controller.continueTarget.textContent).toBe('Try passkey setup again');
        expect(controller.passkeySupport).toBeUndefined();
        expect(controller.completed).not.toBe(true);
    });

    test.each([true, false])('login unlock enters the app directly without waiting for a snapshot: online=%s', async online => {
        history.replaceState({}, '', '/members/login');
        Object.defineProperty(navigator, 'onLine', { configurable: true, value: online });
        loginWithSavedPassword.mockResolvedValue('/members/view/member');
        controller.navigate = jest.fn();
        await controller.unlockDevice({ preventDefault: jest.fn() });
        expect(controller.navigate).toHaveBeenCalledWith(online ? '/members/view/member' : '/offline');
        expect(controller.linkTarget.hidden).toBe(true);
        expect(updateTrustedDevice).not.toHaveBeenCalled();
        expect(loginWithSavedPassword).toHaveBeenCalledTimes(online ? 1 : 0);
    });

    test('an already unlocked login follows the server destination and never exposes the card link', async () => {
        history.replaceState({}, '', '/members/login');
        Object.defineProperty(navigator, 'onLine', { configurable: true, value: true });
        loginWithSavedPassword.mockResolvedValue('/members/view/member');
        controller.navigate = jest.fn();
        await controller.render();
        expect(controller.linkTarget.hidden).toBe(true);
        expect(controller.navigate).toHaveBeenCalledWith('/members/view/member');
    });

    test('logout while login is pending prevents navigation with the stale unlock', async () => {
        Object.defineProperty(navigator, 'onLine', { configurable: true, value: true });
        let finish;
        loginWithSavedPassword.mockImplementation(() => new Promise(resolve => { finish = resolve; }));
        controller.navigate = jest.fn();
        const entering = controller.enterApp();
        expect(controller.linkTarget.hidden).toBe(true);
        vault.generation++;
        finish('/members/view/member');
        await entering;
        expect(controller.navigate).not.toHaveBeenCalled();
    });

});
