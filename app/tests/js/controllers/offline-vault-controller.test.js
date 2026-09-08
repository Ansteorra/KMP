import OfflineController from '../../../assets/js/controllers/offline-vault-controller.js';
import vault from '../../../assets/js/services/offline-vault-service.js';
jest.mock('../../../assets/js/services/offline-vault-service.js', () => ({ __esModule: true, default: { key: {}, metadata: jest.fn(), read: jest.fn(), lock: jest.fn(), unlock: jest.fn() }, purgeLegacyOfflineStorage: jest.fn() }));
jest.mock('../../../assets/js/services/offline-data-service.js', () => ({ currentOfflineContext: jest.fn(), refreshOfflineSnapshot: jest.fn() }));
jest.mock('../../../assets/js/services/rsvp-cache-service.js', () => ({ __esModule: true, default: {} }));
let controller;
const record = { wrapper: { method: 'passphrase' }, verifiedAt: Date.now(), expiresAt: Date.now() + 86400000 };
beforeEach(() => {
    jest.clearAllMocks();
    controller = new OfflineController(); controller.connected = true;
    controller.element = document.createElement('section'); document.body.replaceChildren(controller.element);
    OfflineController.targets.forEach(name => {
        const element = document.createElement(['passphrase', 'newPassphrase'].includes(name) ? 'input' : 'div');
        controller[`${name}Target`] = element; controller.element.append(element);
    });
    controller.unlockedTarget.append(document.createElement('button'));
    controller.statusTarget.setAttribute('role', 'status');
    vault.key = {};
    vault.metadata.mockResolvedValue(record);
    vault.read.mockResolvedValue({ card: { first_name: '<script>PRIVATE</script>', last_name: 'Member', sca_name: 'Society', branch: 'Branch', sections: [] },
        rsvps: [], months: {}, pending: [] });
});
test('renders private strings as text, with semantic definition-list fields', async () => {
    await controller.render();
    expect(controller.cardTarget.textContent).toContain('<script>PRIVATE</script>');
    expect(controller.cardTarget.querySelector('script')).toBeNull();
    expect(controller.cardTarget.querySelector('dt').textContent).toBe('Legal name');
});
test('locking removes private DOM and exposes only the correct unlock method', async () => {
    await controller.render(); vault.key = null; await controller.render();
    expect(controller.cardTarget.textContent).toBe(''); expect(controller.unlockedTarget.hidden).toBe(true);
    expect(controller.lockedTarget.hidden).toBe(false); expect(controller.passphraseFormTarget.hidden).toBe(false);
    expect(controller.deviceUnlockTarget.hidden).toBe(true); expect(controller.statusTarget.textContent).toBe('Saved information is locked.');
});
test('device mode exposes a keyboard-operable device button and hides the passphrase field', async () => {
    vault.key = null; vault.metadata.mockResolvedValue({ ...record, wrapper: { method: 'device' } });
    await controller.render(); expect(controller.deviceUnlockTarget.hidden).toBe(false); expect(controller.passphraseFormTarget.hidden).toBe(true);
});
test('a stale decrypt cannot repopulate private content after a lock render', async () => {
    let finish;
    vault.read.mockImplementation(() => new Promise(resolve => { finish = resolve; }));
    const rendering = controller.render(); await Promise.resolve(); await Promise.resolve();
    vault.key = null; await controller.render();
    finish({ card: { first_name: 'STALE-PRIVATE', sections: [] }, rsvps: [], months: {}, pending: [] });
    await rendering;
    expect(controller.cardTarget.textContent).toBe('');
});

test('the explicit lock action moves focus into the selected unlock control', async () => {
    vault.lock.mockImplementation(() => { vault.key = null; });
    await controller.lock();
    expect(document.activeElement).toBe(controller.passphraseTarget);
});
