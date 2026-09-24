import { currentOfflineContext } from '../../../assets/js/services/offline-data-service.js';
import { removePasskey } from '../../../assets/js/services/passkey-auth-service.js';
jest.mock('../../../assets/js/services/offline-data-service.js', () => ({ currentOfflineContext: jest.fn() }));
jest.mock('../../../assets/js/services/passkey-auth-service.js', () => ({ removePasskey: jest.fn() }));
import OfflineController from '../../../assets/js/controllers/offline-vault-controller.js';
import vault from '../../../assets/js/services/offline-vault-service.js';
import { devicePromptDismissed } from '../../../assets/js/services/device-prompt-preference-service.js';
import { trustThisDevice } from '../../../assets/js/services/offline-runtime-service.js';
jest.mock('../../../assets/js/services/offline-runtime-service.js', () => ({ trustThisDevice: jest.fn(), updateTrustedDevice: jest.fn(), offlineStatus: { message: 'Ready offline.' } }));
jest.mock('../../../assets/js/services/offline-vault-service.js', () => ({ __esModule: true, default: { key: {}, metadata: jest.fn(), lock: jest.fn(), unlock: jest.fn(), clear: jest.fn() } }));
let controller;
const record = { wrapper: { method: 'trusted' }, expiresAt: Date.now() + 86400000 };
beforeEach(() => {
    jest.resetAllMocks();
    controller = new OfflineController(); controller.connected = true;
    controller.element = document.createElement('main'); document.body.replaceChildren(controller.element);
    OfflineController.targets.forEach(name => {
        controller[`${name}Target`] = document.createElement(name === 'passphrase' ? 'input' : 'div');
        controller.element.append(controller[`${name}Target`]);
    });
    vault.key = {}; vault.trusted = true; vault.metadata.mockResolvedValue(record);
});
test('trusted recovery exposes the existing page without rendering a parallel card or event list', async () => {
    controller.contentTarget.textContent = 'Existing mobile page';
    await controller.render();
    expect(controller.contentTarget.hidden).toBe(false);
    expect(controller.contentTarget.textContent).toBe('Existing mobile page');
    expect(controller.enrollTarget.hidden).toBe(true);
    expect(OfflineController.targets).not.toContain('card');
});
test('missing trust hides private content and offers sign-in', async () => {
    vault.metadata.mockResolvedValue(null); vault.key = null;
    await controller.render();
    expect(controller.contentTarget.hidden).toBe(true);
    expect(controller.enrollTarget.hidden).toBe(false);
});
test('revocation tells the mobile controllers to remove private data', async () => {
    const revoked = jest.fn(); window.addEventListener('kmp:offline-revoked', revoked);
    await controller.render(); vault.key = null; vault.metadata.mockResolvedValue(null); await controller.render();
    expect(revoked).toHaveBeenCalledTimes(1);
    window.removeEventListener('kmp:offline-revoked', revoked);
});
test('expired snapshots withhold the existing mobile page and retain recovery', async () => {
    vault.metadata.mockResolvedValue({ ...record, expiresAt: Date.now() - 1 });
    await controller.render();
    expect(controller.contentTarget.hidden).toBe(true);
    expect(controller.statusTarget.textContent).toContain('Waiting RSVPs are kept');
});
test('legacy recovery presents its original unlock method', async () => {
    vault.key = null; vault.trusted = false;
    vault.metadata.mockResolvedValue({ ...record, wrapper: { method: 'passphrase' } });
    await controller.render();
    expect(controller.lockedTarget.hidden).toBe(false);
    expect(controller.passphraseFormTarget.hidden).toBe(false);
    expect(controller.deviceUnlockTarget.hidden).toBe(true);
});
test('trust delegates preparation and reports errors without showing private content', async () => {
    trustThisDevice.mockRejectedValue(new Error('Unable to save'));
    await controller.trust();
    expect(controller.statusTarget.textContent).toBe('Unable to save');
});

test('ready mobile recovery hides the extra live region while the device panel owns progress', async () => {
    controller.connect();
    try {
        await controller.render();
        expect(controller.statusTarget.hidden).toBe(true);
        expect(controller.statusTarget.textContent).toBe('');
        window.dispatchEvent(new Event('kmp:offline-progress'));
        expect(controller.statusTarget.hidden).toBe(true);
        expect(controller.statusTarget.textContent).toBe('');
    } finally { controller.disconnect(); }
});
test('modern unlock instructions are not repeated in recovery', async () => {
    vault.key = null;
    vault.metadata.mockResolvedValue({ ...record, wrapper: { method: 'trusted', unlockMethod: 'pin' } });
    await controller.render();
    expect(controller.statusTarget.hidden).toBe(true);
});
test('recovery errors remain visible and progress cannot replace expiry instructions', async () => {
    vault.metadata.mockResolvedValue({ ...record, expiresAt: Date.now() - 1 });
    controller.connect();
    try {
        await controller.render();
        window.dispatchEvent(new Event('kmp:offline-progress'));
        expect(controller.statusTarget.hidden).toBe(false);
        expect(controller.statusTarget.textContent).toContain('Waiting RSVPs are kept');
        controller.message('Unable to unlock. Try again.');
        expect(controller.statusTarget.hidden).toBe(false);
        expect(controller.statusTarget.textContent).toBe('Unable to unlock. Try again.');
    } finally { controller.disconnect(); }
});

test.each([true, false])('offline removal remembers the owner only when confirmed: %s', async confirmed => {
    localStorage.clear(); sessionStorage.clear(); document.head.innerHTML = '';
    const accessibility = window.KMP_accessibility;
    window.KMP_accessibility = { confirm: jest.fn().mockResolvedValue(confirmed) };
    vault.metadata.mockResolvedValue({ ...record, owner: 'offline-member' });
    vault.clear.mockImplementation(async () => { vault.metadata.mockResolvedValue(null); });
    try {
        await controller.forget();
        document.head.innerHTML = '<meta name="kmp-offline-session" content=\'{"owner":"offline-member"}\'>';
        expect(devicePromptDismissed()).toBe(confirmed);
        expect(vault.clear).toHaveBeenCalledTimes(confirmed ? 1 : 0);
    } finally {
        window.KMP_accessibility = accessibility;
        localStorage.clear(); sessionStorage.clear(); document.head.innerHTML = '';
    }
});

describe('public recovery passkey removal', () => {
    let accessibility;
    let online;
    const authentication = { credentialId: 'device-credential' };
    beforeEach(() => {
        accessibility = window.KMP_accessibility;
        window.KMP_accessibility = { confirm: jest.fn().mockResolvedValue(true) };
        online = Object.getOwnPropertyDescriptor(window.navigator, 'onLine');
        Object.defineProperty(window.navigator, 'onLine', { configurable: true, value: true });
        localStorage.clear(); sessionStorage.clear();
        document.head.innerHTML = '<meta name="kmp-offline-session" content=\'{"owner":"offline-member"}\'>';
        vault.metadata.mockResolvedValue({ ...record, owner: 'offline-member', wrapper: { method: 'trusted', authentication } });
        currentOfflineContext.mockResolvedValue({ owner: 'offline-member', csrfToken: 'test-token' });
        controller.enrollTarget.innerHTML = '<a href="/members/login">Sign in</a>';
        controller.enrollTarget.hidden = true;
        vault.clear.mockImplementation(async () => { vault.metadata.mockResolvedValue(null); });
    });
    afterEach(() => {
        window.KMP_accessibility = accessibility;
        if (online) Object.defineProperty(window.navigator, 'onLine', online);
        else delete window.navigator.onLine;
        localStorage.clear(); sessionStorage.clear(); document.head.innerHTML = '';
    });
    test('revokes using fresh context before clearing local data and moving focus', async () => {
        await controller.forget();
        expect(removePasskey).toHaveBeenCalledWith(authentication, { owner: 'offline-member', csrfToken: 'test-token' });
        expect(currentOfflineContext.mock.invocationCallOrder[0]).toBeLessThan(removePasskey.mock.invocationCallOrder[0]);
        expect(removePasskey.mock.invocationCallOrder[0]).toBeLessThan(vault.clear.mock.invocationCallOrder[0]);
        expect(devicePromptDismissed()).toBe(true);
        expect(document.activeElement).toBe(controller.enrollTarget.querySelector('a'));
    });
    test.each(['offline', 'signed out', 'revocation failed'])('retains local information and focus when %s', async failure => {
        const trigger = document.createElement('button'); controller.element.append(trigger); trigger.focus();
        if (failure === 'offline') Object.defineProperty(window.navigator, 'onLine', { configurable: true, value: false });
        if (failure === 'signed out') currentOfflineContext.mockRejectedValue(new Error('Sign in online to continue.'));
        if (failure === 'revocation failed') removePasskey.mockRejectedValue(new Error('Failed to fetch'));
        await controller.forget();
        expect(vault.clear).not.toHaveBeenCalled();
        expect(devicePromptDismissed()).toBe(false);
        expect(controller.statusTarget.textContent).toMatch(/sign in/i);
        expect(controller.statusTarget.hidden).toBe(false);
        expect(document.activeElement).toBe(trigger);
        if (failure === 'offline') expect(currentOfflineContext).not.toHaveBeenCalled();
        if (failure !== 'revocation failed') expect(removePasskey).not.toHaveBeenCalled();
    });
    test('cancellation leaves both credentials intact', async () => {
        window.KMP_accessibility.confirm.mockResolvedValue(false);
        await controller.forget();
        expect(currentOfflineContext).not.toHaveBeenCalled();
        expect(removePasskey).not.toHaveBeenCalled();
        expect(vault.clear).not.toHaveBeenCalled();
    });
});
