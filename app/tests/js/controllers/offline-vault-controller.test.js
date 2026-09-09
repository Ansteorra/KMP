import OfflineController from '../../../assets/js/controllers/offline-vault-controller.js';
import vault from '../../../assets/js/services/offline-vault-service.js';
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
