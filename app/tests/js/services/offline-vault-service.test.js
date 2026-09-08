import { webcrypto } from 'crypto';
import { OfflineVaultService, MAX_AGE } from '../../../assets/js/services/offline-vault-service.js';

// Real WebCrypto; the adapter keeps this unit suite independent of a browser database.
class MemoryVault extends OfflineVaultService {
    async stored() { return this.record ? structuredClone(this.record) : null; }
    async commit(record, revision = null) {
        if (revision !== null && this.record?.revision !== revision) throw new Error('Changed concurrently');
        this.record = record ? structuredClone(record) : null;
    }
}
const context = () => ({ owner: 'member-a', epoch: 'epoch-a', serverTime: Date.now(), expiresAt: Date.now() + MAX_AGE });
const passphrase = 'four separate woodland rivers';
let vault;
beforeEach(() => {
    Object.defineProperty(global, 'crypto', { value: webcrypto, configurable: true });
    global.structuredClone = value => JSON.parse(JSON.stringify(value));
    vault = new MemoryVault();
});
afterEach(() => vault.lock(false));

test('stored records contain no PII or keys, survive reopen locked, and decrypt with the passphrase', async () => {
    await vault.enroll(context(), 'passphrase', passphrase);
    await vault.mutate(data => { data.card = { name: 'PRIVATE-OFFLINE-MARKER' }; });
    expect(JSON.stringify(vault.record)).not.toContain('PRIVATE-OFFLINE-MARKER');
    expect(JSON.stringify(vault.record)).not.toContain(passphrase);
    const reopened = new MemoryVault(); reopened.record = structuredClone(vault.record);
    await expect(reopened.read()).rejects.toThrow('Unlock');
    await expect(reopened.unlock('wrong passphrase')).rejects.toThrow('Unable to unlock');
    await reopened.unlock(passphrase);
    expect((await reopened.read()).card.name).toBe('PRIVATE-OFFLINE-MARKER');
    reopened.lock(false);
    expect(reopened.key).toBeNull(); expect(reopened.wrappingKey).toBeNull();
});

test('legacy passphrase enrollment retains its original validation', async () => {
    await expect(vault.enroll(context(), 'passphrase', '123456')).rejects.toThrow('15–128');
    await expect(vault.enroll(context(), 'passphrase', '123456789012345678')).rejects.toThrow('15–128');
    expect(vault.record).toBeUndefined();
});

test('ciphertext and actor/expiry metadata tampering cannot be decrypted', async () => {
    await vault.enroll(context(), 'passphrase', passphrase);
    const original = structuredClone(vault.record);
    for (const mutate of [record => { record.owner = 'member-b'; }, record => { record.expiresAt -= 1000; },
        record => { record.payload.data = record.payload.data.slice(0, -4) + 'AAAA'; }]) {
        vault.record = structuredClone(original); mutate(vault.record); vault.lock(false);
        await expect(vault.unlock(passphrase)).rejects.toThrow('Unable to unlock');
    }
});

test('expiry and account/security-epoch changes purge the old vault', async () => {
    await vault.enroll(context(), 'passphrase', passphrase);
    vault.record.expiresAt = Date.now() - 1;
    expect(await vault.metadata()).toBeNull(); expect(vault.record).toBeNull();
    await vault.enroll(context(), 'passphrase', passphrase);
    await expect(vault.verifyContext({ ...context(), owner: 'member-b' })).rejects.toThrow('original member');
    expect(vault.record).toBeNull();
    await vault.enroll(context(), 'passphrase', passphrase);
    await expect(vault.verifyContext({ ...context(), epoch: 'revoked' })).rejects.toThrow('original member');
    expect(vault.key).toBeNull();
});

test('online refresh extends authenticated expiry, preserves pending requests, and stays decryptable after relock', async () => {
    await vault.enroll(context(), 'passphrase', passphrase);
    await vault.mutate(data => { data.pending.push({ id: 'request-a' }); });
    const refreshed = { ...context(), serverTime: Date.now() + 1000, expiresAt: Date.now() + MAX_AGE + 1000 };
    await vault.refresh(refreshed, { card: { name: 'refreshed' }, rsvps: [], months: {} });
    vault.lock(false); await vault.unlock(passphrase);
    expect((await vault.read()).pending).toEqual([{ id: 'request-a' }]);
    expect((await vault.read()).card.name).toBe('refreshed');
});

test('device enrollment requires a reproducible PRF and actual unwrap, not the support bit', async () => {
    const result = webcrypto.getRandomValues(new Uint8Array(32));
    const credential = value => ({ rawId: new Uint8Array([1, 2, 3]), response: { getTransports: () => ['internal'] },
        getClientExtensionResults: () => ({ prf: { enabled: true, results: { first: value?.slice().buffer } } }) });
    const get = jest.fn().mockImplementation(() => Promise.resolve(credential(result)));
    Object.defineProperty(navigator, 'credentials', { configurable: true, value: { create: jest.fn().mockResolvedValue(credential()), get } });
    await vault.enroll(context(), 'device');
    expect(get).not.toHaveBeenCalled();
    expect(vault.record).toBeUndefined();
    expect(await vault.continueDeviceEnrollment()).toBe('verify');
    expect(vault.record).toBeUndefined();
    expect(await vault.continueDeviceEnrollment()).toBe('complete');
    expect(get).toHaveBeenCalledTimes(2);
    expect(get.mock.calls[0][0].publicKey.userVerification).toBe('required');
    expect(JSON.stringify(vault.record)).not.toContain('results');
    vault.lock(false); await vault.unlock(); expect(vault.key).not.toBeNull();
    get.mockResolvedValue(credential());
    await expect(vault.unlock()).rejects.toThrow('Unable to unlock');
    expect(vault.key).toBeNull();
});

test('PRF enabled without usable output does not enroll or fall back to plaintext', async () => {
    Object.defineProperty(navigator, 'credentials', { configurable: true, value: {
        create: jest.fn().mockResolvedValue({ rawId: new Uint8Array([1]), response: {}, getClientExtensionResults: () => ({ prf: { enabled: true } }) }),
        get: jest.fn().mockResolvedValue({ getClientExtensionResults: () => ({ prf: {} }) })
    } });
    await vault.enroll(context(), 'device');
    await expect(vault.continueDeviceEnrollment()).rejects.toThrow('Device encryption');
    expect(vault.record).toBeUndefined();
});


test.each(['012345', '0123456', '01234567'])('explicit numeric PIN %s encrypts and unlocks offline without losing leading zeros', async pin => {
    await vault.enroll(context(), 'pin', pin);
    await vault.mutate(data => { data.card = { name: 'PIN-ENCRYPTED-PRIVATE' }; });
    expect(JSON.stringify(vault.record)).not.toContain('PIN-ENCRYPTED-PRIVATE');
    expect(vault.record.wrapper).toMatchObject({ method: 'pin', iterations: 600000 });
    vault.lock(false);
    await expect(vault.unlock(pin.slice(1))).rejects.toThrow('Unable to unlock');
    await vault.unlock(pin);
    expect((await vault.read()).card.name).toBe('PIN-ENCRYPTED-PRIVATE');
});

test.each(['12345', '123456789', '12a456', '１２３４５６', ' 123456'])('rejects malformed offline PIN %s', async pin => {
    await expect(vault.enroll(context(), 'pin', pin)).rejects.toThrow('6–8 digits');
    expect(vault.record).toBeUndefined();
});

test('OS prompt timeout cannot leave setup pending or commit a late credential', async () => {
    jest.useFakeTimers();
    let complete;
    Object.defineProperty(navigator, 'credentials', { configurable: true, value: {
        create: jest.fn(() => new Promise(resolve => { complete = resolve; }))
    } });
    const pending = vault.enroll(context(), 'device');
    const rejection = expect(pending).rejects.toThrow('timed out');
    await jest.advanceTimersByTimeAsync(45000);
    await rejection;
    complete({ rawId: new Uint8Array([1]), response: {}, getClientExtensionResults: () => ({ prf: { enabled: true } }) });
    await Promise.resolve();
    expect(vault.record).toBeUndefined(); expect(vault.pendingDevice).toBeUndefined();
    jest.useRealTimers();
});

test('session lock aborts an outstanding OS prompt even when the browser ignores abort', async () => {
    Object.defineProperty(navigator, 'credentials', { configurable: true, value: { create: jest.fn(() => new Promise(() => {})) } });
    const pending = vault.enroll(context(), 'device');
    vault.lock(false);
    await expect(pending).rejects.toThrow('cancelled');
    expect(vault.record).toBeUndefined();
});
