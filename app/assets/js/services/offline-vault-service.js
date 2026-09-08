/** Encrypted, expiring, origin/actor-bound storage. Unlock keys never leave memory. */
export const VAULT_DB = 'kmp-offline-vault';
export const MAX_AGE = 7 * 24 * 60 * 60 * 1000;
const VERSION = 1;
const ITERATIONS = 600000;
const encoder = new TextEncoder();
const bytes = value => encoder.encode(value);
const random = length => crypto.getRandomValues(new Uint8Array(length));
const b64 = value => {
    const data = new Uint8Array(value);
    let string = '';
    for (let offset = 0; offset < data.length; offset += 8192) string += String.fromCharCode(...data.subarray(offset, offset + 8192));
    return btoa(string);
};
const unb64 = value => Uint8Array.from(atob(value), c => c.charCodeAt(0));
const announce = () => window.dispatchEvent(new CustomEvent('kmp:offline-state'));

/** Purge ownerless pre-vault databases rather than guessing who owns their rows. */
export async function purgeLegacyOfflineStorage() {
    localStorage.removeItem('kmp.quickLogin.config');
    localStorage.removeItem('kmp.quickLogin.deviceId');
    if (typeof caches !== 'undefined') {
        await Promise.all((await caches.keys()).filter(name =>
            name === 'offline-cache-activity-card' || name.startsWith('kmp-mobile-v')
        ).map(name => caches.delete(name)));
    }
    if (typeof indexedDB === 'undefined') return;
    const removed = await Promise.all(['kmp-rsvp-cache', 'kmp-offline-queue'].map(name => new Promise((resolve, reject) => {
        let migrated = false;
        const request = indexedDB.open(name, 2);
        request.onupgradeneeded = event => {
            migrated = event.oldVersion > 0;
            const db = request.result;
            [...db.objectStoreNames].forEach(store => db.deleteObjectStore(store));
        };
        request.onsuccess = () => { request.result.close(); resolve(migrated); };
        request.onerror = () => reject(new Error('Offline storage cleanup failed.'));
        request.onblocked = () => reject(new Error('Close other KMP tabs to finish the security update.'));
    })));
    if (removed.some(Boolean)) sessionStorage.setItem('kmp.offline.migrated', '1');
    return removed.some(Boolean);
}

export class OfflineVaultService {
    constructor() {
        this.key = null;
        this.activeId = null;
        this.generation = 0;
        this.dbPromise = null;
        this.operations = Promise.resolve();
        this.channel = typeof BroadcastChannel === 'undefined' ? null : new BroadcastChannel('kmp-offline-vault');
        if (this.channel) this.channel.onmessage = () => this.lock(false);
    }

    async db() {
        if (!this.dbPromise) this.dbPromise = new Promise((resolve, reject) => {
            const request = indexedDB.open(VAULT_DB, VERSION);
            request.onupgradeneeded = () => request.result.createObjectStore('vault');
            request.onsuccess = () => {
                const db = request.result;
                db.onversionchange = () => { this.lock(); db.close(); this.dbPromise = null; };
                resolve(db);
            };
            request.onerror = () => { this.dbPromise = null; reject(new Error('Offline storage is unavailable.')); };
        });
        return this.dbPromise;
    }

    async stored() {
        const db = await this.db();
        return new Promise((resolve, reject) => {
            const request = db.transaction('vault').objectStore('vault').get('current');
            request.onsuccess = () => resolve(request.result || null);
            request.onerror = () => reject(new Error('Unable to read offline storage.'));
        });
    }

    async commit(record, revision = null) {
        const db = await this.db();
        return new Promise((resolve, reject) => {
            const tx = db.transaction('vault', 'readwrite');
            const store = tx.objectStore('vault');
            const request = store.get('current');
            request.onsuccess = () => {
                if (revision !== null && request.result?.revision !== revision) { tx.abort(); return; }
                if (record) store.put(record, 'current'); else store.delete('current');
            };
            tx.oncomplete = resolve;
            tx.onerror = tx.onabort = () => reject(new Error('Offline data changed in another tab. Unlock and retry.'));
        });
    }

    valid(record) {
        return record?.version === VERSION && typeof record.owner === 'string' && typeof record.epoch === 'string'
            && Number.isFinite(record.expiresAt) && Number.isFinite(record.verifiedAt)
            && record.expiresAt > Date.now() && record.verifiedAt <= Date.now() + 300000
            && record.expiresAt - record.verifiedAt <= MAX_AGE;
    }

    async metadata() {
        const record = await this.stored();
        if (record && !this.valid(record)) { await this.clear(); return null; }
        return record;
    }

    aad(record, kind) {
        return bytes(JSON.stringify([location.origin, VERSION, record.id, record.owner, record.epoch, record.verifiedAt, record.expiresAt, kind]));
    }

    async crypt(key, value, record, kind) {
        const iv = random(12);
        const encrypted = await crypto.subtle.encrypt({ name: 'AES-GCM', iv, additionalData: this.aad(record, kind) }, key, value);
        return { iv: b64(iv), data: b64(encrypted) };
    }

    async decrypt(key, value, record, kind) {
        return crypto.subtle.decrypt({ name: 'AES-GCM', iv: unb64(value.iv), additionalData: this.aad(record, kind) }, key, unb64(value.data));
    }

    async passphraseKey(passphrase, salt, iterations = ITERATIONS) {
        if (iterations !== ITERATIONS) throw new Error('Unsupported offline key format.');
        const material = await crypto.subtle.importKey('raw', bytes(passphrase), 'PBKDF2', false, ['deriveKey']);
        return crypto.subtle.deriveKey({ name: 'PBKDF2', hash: 'SHA-256', salt: unb64(salt), iterations }, material,
            { name: 'AES-GCM', length: 256 }, false, ['encrypt', 'decrypt']);
    }

    /** Bound OS prompts even when a browser ignores WebAuthn's timeout hint. */
    async credentialRequest(method, options) {
        if (!navigator.credentials?.[method]) throw new Error('Device unlock is unavailable. Use an offline PIN.');
        const controller = new AbortController();
        this.promptController = controller;
        let timer;
        try {
            return await Promise.race([
                navigator.credentials[method]({ ...options, signal: controller.signal }),
                new Promise((resolve, reject) => {
                    controller.signal.addEventListener('abort', () => reject(new Error('Device unlock cancelled or timed out. Retry or use an offline PIN.')), { once: true });
                    timer = setTimeout(() => controller.abort(), 45000);
                })
            ]);
        } finally {
            clearTimeout(timer);
            if (this.promptController === controller) this.promptController = null;
        }
    }

    async deviceKey(wrapper) {
        const credential = await this.credentialRequest('get', { publicKey: {
            challenge: random(32), rpId: location.hostname, userVerification: 'required', timeout: 60000,
            allowCredentials: [{ type: 'public-key', id: unb64(wrapper.credentialId), transports: wrapper.transports }],
            extensions: { prf: { eval: { first: unb64(wrapper.input) } } }
        } });
        const result = credential?.getClientExtensionResults()?.prf?.results?.first;
        if (!result || result.byteLength !== 32 || b64(credential.rawId) !== wrapper.credentialId) {
            throw new Error('Device encryption is unavailable. Use an offline PIN.');
        }
        return this.prfKey(result, wrapper);
    }

    async prfKey(result, wrapper) {
        const material = await crypto.subtle.importKey('raw', result, 'HKDF', false, ['deriveKey']);
        const key = await crypto.subtle.deriveKey({ name: 'HKDF', hash: 'SHA-256', salt: unb64(wrapper.input),
            info: bytes('KMP offline key wrapping v1') }, material, { name: 'AES-GCM', length: 256 }, false, ['encrypt', 'decrypt']);
        new Uint8Array(result).fill(0);
        return key;
    }

    async enroll(context, method, passphrase = '') {
        const generation = this.generation;
        if (!context?.owner || !context?.epoch || context.impersonating || !navigator.onLine) throw new Error('Sign in online to enable offline access.');
        if (method === 'passphrase' && (passphrase.length < 15 || passphrase.length > 128 || /^\d+$/.test(passphrase))) {
            throw new Error('Use a passphrase of 15–128 characters, not a numeric PIN.');
        }
        if (method === 'pin' && !/^[0-9]{6,8}$/.test(passphrase)) throw new Error('Use a PIN of 6–8 digits.');
        const record = { version: VERSION, id: crypto.randomUUID(), revision: crypto.randomUUID(), owner: context.owner,
            epoch: context.epoch, verifiedAt: context.serverTime, expiresAt: context.expiresAt };
        if (!this.valid(record)) throw new Error('Check the device clock before enabling offline access.');
        let wrapper;
        let wrappingKey;
        if (method === 'device') {
            if (!navigator.credentials?.create || !crypto.subtle) throw new Error('Use an offline PIN on this device.');
            const input = b64(random(32));
            const credential = await this.credentialRequest('create', { publicKey: {
                challenge: random(32), rp: { id: location.hostname, name: 'KMP offline access' },
                user: { id: random(32), name: 'KMP offline access', displayName: 'KMP offline access' },
                pubKeyCredParams: [{ type: 'public-key', alg: -7 }, { type: 'public-key', alg: -257 }],
                authenticatorSelection: { authenticatorAttachment: 'platform', residentKey: 'preferred', userVerification: 'required' },
                attestation: 'none', timeout: 60000, extensions: { prf: { eval: { first: unb64(input) } } }
            } });
            if (!credential?.getClientExtensionResults()?.prf?.enabled) throw new Error('Device encryption is unavailable. Use an offline PIN.');
            wrapper = { method, input, credentialId: b64(credential.rawId), transports: credential.response.getTransports?.() || ['internal'] };
            if (generation !== this.generation) throw new Error('Offline setup was cancelled by a session change.');
            // Each assertion needs its own tap on Safari. Never chain OS prompts.
            const result = credential.getClientExtensionResults()?.prf?.results?.first;
            const pending = { record, wrapper, generation };
            if (result?.byteLength === 32) {
                const key = await this.prfKey(result, wrapper);
                await this.sealRecord(record, wrapper, key);
            }
            if (generation !== this.generation) throw new Error('Offline setup was cancelled by a session change.');
            this.pendingDevice = pending;
            return record.payload ? 'verify' : 'wrap';
        } else if (method === 'passphrase' || method === 'pin') {
            wrapper = { method, salt: b64(random(32)), iterations: ITERATIONS };
            wrappingKey = await this.passphraseKey(passphrase, wrapper.salt);
        } else throw new Error('Choose an offline unlock method.');
        await this.sealRecord(record, wrapper, wrappingKey);
        await this.activateRecord(record, wrappingKey, generation);
    }

    async sealRecord(record, wrapper, wrappingKey) {
        const raw = random(32);
        try {
            const key = await crypto.subtle.importKey('raw', raw, 'AES-GCM', false, ['encrypt', 'decrypt']);
            record.wrapper = { ...wrapper, sealed: await this.crypt(wrappingKey, raw, record, 'key') };
            record.payload = await this.crypt(key, bytes(JSON.stringify({ card: null, months: {}, rsvps: [], pending: [] })), record, 'payload');
        } finally { raw.fill(0); }
    }

    /** Called directly from a new user gesture; incomplete setup stays only in memory. */
    async continueDeviceEnrollment() {
        const pending = this.pendingDevice;
        if (!pending || pending.generation !== this.generation) throw new Error('Start device setup again.');
        const wrappingKey = await this.deviceKey(pending.wrapper);
        if (pending.generation !== this.generation) throw new Error('Offline setup was cancelled by a session change.');
        if (!pending.record.payload) {
            await this.sealRecord(pending.record, pending.wrapper, wrappingKey);
            return 'verify';
        }
        await this.activateRecord(pending.record, wrappingKey, pending.generation);
        this.pendingDevice = null;
        return 'complete';
    }

    async activateRecord(record, wrappingKey, generation) {
        const raw = await this.decrypt(wrappingKey, record.wrapper.sealed, record, 'key');
        const key = await crypto.subtle.importKey('raw', raw, 'AES-GCM', false, ['encrypt', 'decrypt']);
        new Uint8Array(raw).fill(0);
        await this.decrypt(key, record.payload, record, 'payload');
        if (generation !== this.generation || !this.valid(record)) throw new Error('Offline setup was cancelled or expired.');
        await this.commit(record);
        if (generation !== this.generation) { await this.commit(null); throw new Error('Offline setup was cancelled.'); }
        this.key = key; this.wrappingKey = wrappingKey; this.activeId = record.id;
        this.channel?.postMessage('changed');
        announce();
    }

    async unlock(passphrase = '', preparedRecord = null) {
        const generation = this.generation;
        const record = preparedRecord || await this.metadata();
        if (!record || !this.valid(record)) throw new Error('Connect and sign in to refresh offline access.');
        try {
            const wrappingKey = record.wrapper.method === 'device' ? await this.deviceKey(record.wrapper)
                : await this.passphraseKey(passphrase, record.wrapper.salt, record.wrapper.iterations);
            const raw = await this.decrypt(wrappingKey, record.wrapper.sealed, record, 'key');
            const key = await crypto.subtle.importKey('raw', raw, 'AES-GCM', false, ['encrypt', 'decrypt']);
            new Uint8Array(raw).fill(0);
            await this.decrypt(key, record.payload, record, 'payload');
            // Logout/another enrollment may have occurred while the OS prompt was open.
            const latest = await this.stored();
            if (latest?.id !== record.id || !this.valid(latest) || generation !== this.generation) throw new Error('Offline data changed.');
            this.key = key; this.wrappingKey = wrappingKey; this.activeId = record.id;
            announce();
        } catch { this.lock(); throw new Error('Unable to unlock. Check your unlock method or reconnect to reset offline access.'); }
    }

    lock(broadcast = true) {
        this.generation++;
        this.promptController?.abort();
        this.pendingDevice = null;
        this.key = null; this.wrappingKey = null; this.activeId = null;
        if (broadcast) {
            this.channel?.postMessage('locked');
            try { localStorage.setItem('kmp.offline.lock', crypto.randomUUID()); } catch { /* Storage may be disabled. */ }
        }
        announce();
    }

    async clear() {
        if (this.clearing) return this.clearing;
        this.generation++;
        this.promptController?.abort();
        this.pendingDevice = null;
        this.key = null; this.wrappingKey = null; this.activeId = null;
        this.channel?.postMessage('cleared');
        try { localStorage.setItem('kmp.offline.lock', crypto.randomUUID()); } catch { /* Storage may be disabled. */ }
        this.clearing = this.commit(null).finally(() => { this.clearing = null; announce(); });
        return this.clearing;
    }

    async read() {
        const record = await this.metadata();
        if (!record || !this.key || record.id !== this.activeId) throw new Error('Unlock offline access first.');
        const key = this.key;
        const result = await this.decrypt(key, record.payload, record, 'payload');
        if (key !== this.key || record.id !== this.activeId) throw new Error('Offline access was locked.');
        return JSON.parse(new TextDecoder().decode(result));
    }

    async mutate(change) {
        const operation = this.operations.catch(() => {}).then(async () => {
            const record = await this.metadata();
            if (!record || !this.key || record.id !== this.activeId) throw new Error('Unlock offline access first.');
            const key = this.key;
            const payload = JSON.parse(new TextDecoder().decode(await this.decrypt(key, record.payload, record, 'payload')));
            await change(payload);
            if (key !== this.key) throw new Error('Offline access was locked.');
            const revision = record.revision;
            record.revision = crypto.randomUUID();
            record.payload = await this.crypt(key, bytes(JSON.stringify(payload)), record, 'payload');
            await this.commit(record, revision);
            announce();
        });
        this.operations = operation;
        return operation;
    }

    async refresh(context, snapshot) {
        await this.verifyContext(context);
        const record = await this.metadata();
        if (!record || !this.key || !this.wrappingKey || record.id !== this.activeId) throw new Error('Unlock offline access first.');
        const key = this.key;
        const wrappingKey = this.wrappingKey;
        const raw = await this.decrypt(wrappingKey, record.wrapper.sealed, record, 'key');
        const payload = await this.read();
        const revision = record.revision;
        record.verifiedAt = context.serverTime; record.expiresAt = context.expiresAt;
        record.revision = crypto.randomUUID();
        record.wrapper.sealed = await this.crypt(wrappingKey, raw, record, 'key');
        new Uint8Array(raw).fill(0);
        record.payload = await this.crypt(key, bytes(JSON.stringify({ ...payload, ...snapshot })), record, 'payload');
        if (key !== this.key) throw new Error('Offline access was locked.');
        await this.commit(record, revision);
        announce();
    }

    async verifyContext(context) {
        const record = await this.metadata();
        if (!context || context.impersonating || (record && (record.owner !== context.owner || record.epoch !== context.epoch))) {
            await this.clear();
            throw new Error('Sign in as the original member and enable offline access again.');
        }
        return context;
    }
}

const offlineVault = new OfflineVaultService();
export default offlineVault;
