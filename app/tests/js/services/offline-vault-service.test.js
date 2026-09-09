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
    localStorage.clear(); sessionStorage.clear();
    document.head.innerHTML = '<meta name="kmp-short-site-title" content="Guild App">';
    global.structuredClone = value => {
        if (!value || typeof value !== 'object' || value.constructor?.name === 'CryptoKey') return value;
        if (Array.isArray(value)) return value.map(structuredClone);
        return Object.fromEntries(Object.entries(value).map(([key, item]) => [key, structuredClone(item)]));
    };
    vault = new MemoryVault();
});
afterEach(() => { vault.lock(false); document.head.innerHTML = ''; });

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

test('a short or numeric PIN never becomes an offline encryption credential', async () => {
    await expect(vault.enroll(context(), 'passphrase', '123456')).rejects.toThrow('15 characters');
    await expect(vault.enroll(context(), 'passphrase', '123456789012345678')).rejects.toThrow('15 characters');
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
    expect(navigator.credentials.create.mock.calls[0][0].publicKey.rp.name).toBe('Guild App offline access');
    expect(navigator.credentials.create.mock.calls[0][0].publicKey.user.displayName).toBe('Guild App offline access');
    expect(vault.record).toBeUndefined();
    await vault.continueDeviceEnrollment();
    expect(vault.record).toBeUndefined();
    await vault.continueDeviceEnrollment();
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
    await expect(vault.continueDeviceEnrollment()).rejects.toThrow('This passkey cannot');
    expect(vault.record).toBeUndefined();
});


test('deliberately trusted records reopen without credentials and keep encrypted payloads', async () => {
    await vault.trust(context());
    await vault.mutate(data => { data.card = { name: 'TRUSTED-PRIVATE' }; data.pending = [{ id: 'waiting' }]; });
    expect(vault.record.wrapper.key.extractable).toBe(false);
    expect(JSON.stringify(vault.record)).not.toContain('TRUSTED-PRIVATE');
    vault.lock(false);
    await vault.openTrusted();
    expect((await vault.read()).card.name).toBe('TRUSTED-PRIVATE');
    await expect(webcrypto.subtle.exportKey('raw', vault.record.wrapper.key)).rejects.toThrow();
});

test('expired trusted snapshots retain trust and unsent requests but withhold private display until refreshed', async () => {
    const start = Date.now();
    await vault.trust({ ...context(), serverTime: start - MAX_AGE + 1000, expiresAt: start + 1000 });
    await vault.mutate(data => { data.pending = [{ id: 'waiting' }]; });
    const now = jest.spyOn(Date, 'now').mockReturnValue(start + 2000);
    try {
        vault.lock(false);
        await vault.openTrusted();
        await expect(vault.read()).rejects.toThrow('Waiting RSVPs are kept');
        expect((await vault.read(true)).pending).toEqual([{ id: 'waiting' }]);
        await vault.refresh(context(), { card: { name: 'fresh' }, rsvps: [], months: {} });
        expect((await vault.read()).pending).toEqual([{ id: 'waiting' }]);
    } finally { now.mockRestore(); }
});

test('legacy conversion requires unlock and preserves pending requests and snapshot age', async () => {
    await vault.enroll(context(), 'passphrase', passphrase);
    await vault.mutate(data => { data.pending = [{ id: 'old-waiting' }]; });
    const age = vault.record.verifiedAt;
    vault.lock(false);
    await expect(vault.trust(context())).rejects.toThrow('previously saved');
    await vault.unlock(passphrase);
    await vault.trust(context());
    vault.lock(false); await vault.openTrusted();
    expect((await vault.read()).pending).toEqual([{ id: 'old-waiting' }]);
    expect(vault.record.verifiedAt).toBe(age);
});

test('explicit removal prevents reopening and an account change removes device trust', async () => {
    await vault.trust(context());
    await vault.clear();
    expect(await vault.openTrusted()).toBe(false);
    await vault.trust(context());
    await expect(vault.verifyContext({ ...context(), owner: 'someone-else' })).rejects.toThrow();
    expect(await vault.openTrusted()).toBe(false);
});


test.each([
    { owner: 'another-member', epoch: 'epoch-a' },
    { owner: 'member-a', epoch: 'changed-epoch' },
    { owner: 'member-a', epoch: 'epoch-a', impersonating: true },
])('direct saved-card access rejects a conflicting page identity: %j', async pageContext => {
    await vault.trust(context());
    vault.lock(false);
    const meta = document.createElement('meta');
    meta.name = 'kmp-offline-session';
    meta.content = JSON.stringify(pageContext);
    document.head.append(meta);
    try {
        expect(await vault.openTrusted()).toBe(false);
        expect(await vault.metadata()).toBeNull();
        expect(vault.key).toBeNull();
    } finally { meta.remove(); }
});


test('a background snapshot keeps newer captured resources without renewing stale visited months', async () => {
    await vault.trust(context());
    await vault.mutate(data => {
        data.card = { name: 'New foreground card' };
        data.rsvps = [{ gathering_id: 42 }];
        data.months = { '2026-8': [{ name: 'Old visited month' }], '2026-11': [{ name: 'New visit' }] };
        data.resourceTimes = { card: 300, rsvps: 300, 'month:2026-8': 100, 'month:2026-11': 300 };
        data.pending = [{ id: 'waiting' }];
    });
    await vault.refresh(context(), { card: { name: 'Old background card' }, rsvps: [], months: { '2026-9': [] },
        resourceTimes: { card: 200, rsvps: 200, 'month:2026-9': 200 } });
    vault.lock(false); await vault.openTrusted();
    const saved = await vault.read();
    expect(saved.card.name).toBe('New foreground card');
    expect(saved.rsvps).toEqual([{ gathering_id: 42 }]);
    expect(saved.pending).toEqual([{ id: 'waiting' }]);
    expect(saved.months['2026-11'][0].name).toBe('New visit');
    expect(saved.months).not.toHaveProperty('2026-8');
});


const devicePin = '582694';
const savedLogin = { email: 'synthetic@example.test', password: 'Synthetic password secret' };
test('PIN encrypts the saved password and data, while logout keeps only the protected copy', async () => {
    await vault.enroll(context(), 'pin', devicePin, savedLogin);
    await vault.mutate(data => { data.card = { name: 'PRIVATE-PIN-CARD' }; data.pending = [{ id: 'waiting' }]; });
    expect(vault.record.wrapper.unlockMethod).toBe('pin');
    expect(vault.record.wrapper.key).toBeUndefined();
    expect(JSON.stringify(vault.record)).not.toMatch(/Synthetic password secret|synthetic@example.test|PRIVATE-PIN-CARD|582694/);
    expect(sessionStorage.getItem('kmp.offline.session')).not.toContain(savedLogin.password);
    vault.signOut();
    expect(sessionStorage.getItem('kmp.offline.session')).toBeNull();
    expect(await vault.openTrusted()).toBe(false);
    await expect(vault.unlock('000000')).rejects.toThrow('Unable to unlock');
    expect(vault.record).toBeTruthy();
    await vault.unlock(devicePin);
    expect((await vault.read()).login).toEqual(savedLogin);
    expect((await vault.read()).pending).toEqual([{ id: 'waiting' }]);
});

test('tab navigation restores an unlocked session but another tabs logout invalidates that session', async () => {
    await vault.enroll(context(), 'pin', devicePin, savedLogin);
    const tabSession = sessionStorage.getItem('kmp.offline.session');
    const nextPage = new MemoryVault(); nextPage.record = structuredClone(vault.record);
    expect(await nextPage.openTrusted()).toBe(true);
    expect((await nextPage.read()).login).toEqual(savedLogin);
    vault.signOut();
    sessionStorage.setItem('kmp.offline.session', tabSession);
    nextPage.key = null;
    expect(await nextPage.openTrusted()).toBe(false);
});

test('expired snapshots retain PIN login and pending requests for a fresh online update', async () => {
    const start = Date.now();
    await vault.enroll({ ...context(), serverTime: start - MAX_AGE + 1000, expiresAt: start + 1000 }, 'pin', devicePin, savedLogin);
    vault.signOut();
    const now = jest.spyOn(Date, 'now').mockReturnValue(start + 2000);
    try {
        await vault.unlock(devicePin);
        await expect(vault.read()).rejects.toThrow('Waiting RSVPs are kept');
        expect((await vault.read(true)).login).toEqual(savedLogin);
        await vault.refresh(context(), { card: { name: 'fresh' }, months: {}, rsvps: [] });
        expect((await vault.read()).card.name).toBe('fresh');
        expect((await vault.read()).login).toEqual(savedLogin);
    } finally { now.mockRestore(); }
});

test('a protected passkey requires its PRF output again after logout', async () => {
    const output = new Uint8Array(32).fill(7);
    const credential = () => ({ rawId: new Uint8Array([4]), response: {},
        getClientExtensionResults: () => ({ prf: { enabled: true, results: { first: output.slice().buffer } } }) });
    const get = jest.fn().mockImplementation(async () => credential());
    Object.defineProperty(navigator, 'credentials', { configurable: true, value: { create: jest.fn().mockImplementation(async () => credential()), get } });
    await vault.enroll(context(), 'device', '', savedLogin);
    expect(vault.record).toBeUndefined();
    await vault.continueDeviceEnrollment();
    expect(vault.record.wrapper.unlockMethod).toBe('device');
    expect(vault.record.wrapper.key).toBeUndefined();
    vault.signOut();
    expect(await vault.openTrusted()).toBe(false);
    await vault.unlock();
    expect(get).toHaveBeenCalledTimes(2);
    expect((await vault.read()).login).toEqual(savedLogin);
});

test('forgetting the device removes its encrypted login as well as the offline data', async () => {
    await vault.enroll(context(), 'pin', devicePin, savedLogin);
    await vault.clear();
    expect(vault.record).toBeNull();
    expect(sessionStorage.getItem('kmp.offline.session')).toBeNull();
    await expect(vault.unlock(devicePin)).rejects.toThrow('Connect');
});


test('missing creation support metadata is checked with real assertions before committing', async () => {
    const value = webcrypto.getRandomValues(new Uint8Array(32));
    const credential = includeOutput => ({ rawId: new Uint8Array([1]), response: {},
        getClientExtensionResults: () => includeOutput ? { prf: { results: { first: value.slice().buffer } } } : {} });
    Object.defineProperty(navigator, 'credentials', { configurable: true, value: {
        create: jest.fn().mockResolvedValue(credential(false)), get: jest.fn().mockImplementation(async () => credential(true))
    } });
    await expect(vault.enroll(context(), 'device')).resolves.toBe('wrap');
    expect(vault.record).toBeUndefined();
    await expect(vault.continueDeviceEnrollment()).resolves.toBe('verify');
    expect(vault.record).toBeUndefined();
    await expect(vault.continueDeviceEnrollment()).resolves.toBe('complete');
    vault.lock(false);
    await vault.unlock();
    expect(vault.key).not.toBeNull();
});
