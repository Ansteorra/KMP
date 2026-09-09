import { shortSiteTitle } from './app-branding-service.js';
import { unavailablePasskey, forgetPasskeyFailure } from './passkey-support-service.js';

/** Encrypted, origin/actor-bound storage. Trusted browsers retain a nonextractable wrapping key. */
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
        request.onblocked = () => reject(new Error(`Close other ${shortSiteTitle()} tabs to finish the security update.`));
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
        if (this.channel) this.channel.onmessage = event => {
            if (event.data === 'cleared' || event.data === 'locked') this.lock(false);
            else announce();
        };
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
        const generation = this.generation;
        const db = await this.db();
        return new Promise((resolve, reject) => {
            const tx = db.transaction('vault', 'readwrite');
            const store = tx.objectStore('vault');
            const request = store.get('current');
            request.onsuccess = () => {
                if ((record && generation !== this.generation) || (revision !== null && request.result?.revision !== revision)) { tx.abort(); return; }
                if (record) store.put(record, 'current'); else store.delete('current');
            };
            tx.oncomplete = resolve;
            tx.onerror = tx.onabort = () => reject(new Error('Offline data changed in another tab. Unlock and retry.'));
        });
    }

    valid(record) {
        return record?.version === VERSION && typeof record.owner === 'string' && typeof record.epoch === 'string'
            && Number.isFinite(record.expiresAt) && Number.isFinite(record.verifiedAt)
            && (record.wrapper?.method === 'trusted' || record.expiresAt > Date.now()) && record.verifiedAt <= Date.now() + 300000
            && record.expiresAt > record.verifiedAt && record.expiresAt - record.verifiedAt <= MAX_AGE;
    }

    async metadata() {
        if (document.cookie.split(';').some(item => item.trim() === 'kmp_offline_lock=1')) {
            document.cookie = 'kmp_offline_lock=; Max-Age=0; Path=/; SameSite=Strict';
            this.signOut();
        }
        if (document.cookie.split(';').some(item => item.trim() === 'kmp_offline_clear=1')) {
            await this.clear();
            document.cookie = 'kmp_offline_clear=; Max-Age=0; Path=/; SameSite=Strict';
        }
        const record = await this.stored();
        if (record && (localStorage.getItem('kmp.offline.revoked') === '1' || !this.valid(record))) { await this.clear(); return null; }
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

    async passphraseKey(passphrase, salt, iterations = ITERATIONS, exportable = false) {
        if (iterations !== ITERATIONS) throw new Error('Unsupported offline key format.');
        const material = await crypto.subtle.importKey('raw', bytes(passphrase), 'PBKDF2', false, ['deriveKey']);
        return crypto.subtle.deriveKey({ name: 'PBKDF2', hash: 'SHA-256', salt: unb64(salt), iterations }, material,
            { name: 'AES-GCM', length: 256 }, exportable, ['encrypt', 'decrypt']);
    }

    /** Bound OS prompts even when a browser ignores WebAuthn's timeout hint. */
    async credentialRequest(method, options) {
        if (!navigator.credentials?.[method]) throw new Error('Device unlock is unavailable. Choose a PIN instead.');
        const controller = new AbortController();
        this.promptController = controller;
        let timer;
        try {
            return await Promise.race([
                navigator.credentials[method]({ ...options, signal: controller.signal }),
                new Promise((resolve, reject) => {
                    controller.signal.addEventListener('abort', () => reject(new Error('Device unlock cancelled or timed out. Retry or choose a PIN instead.')), { once: true });
                    timer = setTimeout(() => controller.abort(), 120000);
                })
            ]);
        } finally {
            clearTimeout(timer);
            if (this.promptController === controller) this.promptController = null;
        }
    }

    async deviceKey(wrapper) {
        const credential = await this.credentialRequest('get', { publicKey: {
            challenge: random(32), rpId: location.hostname, userVerification: 'required', timeout: 120000,
            allowCredentials: [{ type: 'public-key', id: unb64(wrapper.credentialId), transports: wrapper.transports }],
            extensions: { prf: { eval: { first: unb64(wrapper.input) } } }
        } });
        const result = credential?.getClientExtensionResults()?.prf?.results?.first;
        if (!result || result.byteLength !== 32 || b64(credential.rawId) !== wrapper.credentialId) {
            throw unavailablePasskey(`This passkey cannot protect ${shortSiteTitle()}’s offline information. Use a ${shortSiteTitle()} PIN on this device.`);
        }
        return this.prfKey(result, wrapper);
    }

    async prfKey(result, wrapper) {
        const material = await crypto.subtle.importKey('raw', result, 'HKDF', false, ['deriveKey']);
        const key = await crypto.subtle.deriveKey({ name: 'HKDF', hash: 'SHA-256', salt: unb64(wrapper.input),
            info: bytes('KMP offline key wrapping v1') }, material, { name: 'AES-GCM', length: 256 }, !!wrapper.unlockMethod, ['encrypt', 'decrypt']);
        new Uint8Array(result).fill(0);
        return key;
    }

    async enroll(context, method, passphrase = '', login = null) {
        const generation = this.generation;
        if (!context?.owner || !context?.epoch || context.impersonating || !navigator.onLine) throw new Error('Sign in online to enable offline access.');
        if (method === 'pin' && !/^\d{6,12}$/.test(passphrase)) throw new Error('Choose a PIN with 6–12 digits.');
        if (method === 'passphrase' && (passphrase.length < 15 || passphrase.length > 128 || /^\d+$/.test(passphrase))) {
            throw new Error('Use at least 15 characters, such as four unrelated words.');
        }
        if (login) await this.verifyContext(context);
        const previous = login ? await this.metadata() : null;
        if (previous && !this.key) throw new Error('Unlock your saved information before changing device protection.');
        const payload = login ? { ...(previous ? await this.read(true) : { card: null, months: {}, rsvps: [], pending: [] }), login } : null;
        const record = { version: VERSION, id: crypto.randomUUID(), revision: crypto.randomUUID(), owner: context.owner,
            epoch: context.epoch, verifiedAt: context.serverTime, expiresAt: context.expiresAt };
        if (!this.valid(record)) throw new Error('Check the device clock before enabling offline access.');
        if (previous) {
            record.verifiedAt = previous.verifiedAt; record.expiresAt = previous.expiresAt;
            record.snapshotSaved = previous.snapshotSaved;
        }
        let wrapper;
        let wrappingKey;
        if (method === 'device') {
            if (!navigator.credentials?.create || !crypto.subtle) throw new Error('Choose a PIN on this device.');
            const input = b64(random(32));
            const credential = await this.credentialRequest('create', { publicKey: {
                challenge: random(32), rp: { id: location.hostname, name: `${shortSiteTitle()} offline access` },
                user: { id: random(32), name: `${shortSiteTitle()} offline access`, displayName: `${shortSiteTitle()} offline access` },
                pubKeyCredParams: [{ type: 'public-key', alg: -7 }, { type: 'public-key', alg: -257 }],
                authenticatorSelection: { authenticatorAttachment: 'platform', residentKey: 'preferred', userVerification: 'required' },
                attestation: 'none', timeout: 120000, extensions: { prf: { eval: { first: unb64(input) } } }
            } });
            if (!credential) throw new Error(`Passkey setup was not completed. Try again or choose a ${shortSiteTitle()} PIN.`);
            const prf = credential.getClientExtensionResults()?.prf;
            // Support metadata alone is not proof. Some providers only return usable output on get().
            console.info('[KMP device setup]', { stage: 'passkey-created', prfEnabled: prf?.enabled === true,
                hasPrfOutput: prf?.results?.first?.byteLength === 32 });
            if (prf?.enabled === false) throw unavailablePasskey(`Your browser saved a passkey, but it cannot use it to protect ${shortSiteTitle()}’s offline information. Use a ${shortSiteTitle()} PIN on this device.`);
            wrapper = { method: login ? 'trusted' : method, ...(login ? { unlockMethod: 'device' } : {}), input, credentialId: b64(credential.rawId), transports: credential.response.getTransports?.() || ['internal'] };
            if (generation !== this.generation) throw new Error('Offline setup was cancelled by a session change.');
            // Each assertion needs its own tap on Safari. Never chain OS prompts.
            const result = prf?.results?.first;
            const pending = { record, wrapper, generation, payload, revision: previous?.revision ?? null };
            if (result?.byteLength === 32) {
                const key = await this.prfKey(result, wrapper);
                await this.sealRecord(record, wrapper, key, payload);
            }
            if (generation !== this.generation) throw new Error('Offline setup was cancelled by a session change.');
            this.pendingDevice = pending;
            return record.payload ? 'verify' : 'wrap';
        } else if (method === 'passphrase' || method === 'pin') {
            wrapper = { method: login ? 'trusted' : method, ...(login ? { unlockMethod: 'pin' } : {}), salt: b64(random(32)), iterations: ITERATIONS };
            wrappingKey = await this.passphraseKey(passphrase, wrapper.salt, ITERATIONS, !!login);
        } else throw new Error('Choose an offline unlock method.');
        await this.sealRecord(record, wrapper, wrappingKey, payload);
        localStorage.removeItem('kmp.offline.revoked');
        await this.activateRecord(record, wrappingKey, generation, previous?.revision ?? null);
    }

    /** Called only after the user chooses to trust this personal browser. */
    async trust(context) {
        const generation = this.generation;
        await this.verifyContext(context);
        if (!context?.owner || !context?.epoch || context.impersonating || !navigator.onLine) throw new Error('Sign in to trust this device.');
        const existing = await this.metadata();
        if (existing && !this.key) throw new Error('Open your previously saved information first so waiting RSVPs can be kept.');
        const payload = existing ? await this.read(true) : { card: null, months: {}, rsvps: [], pending: [] };
        const wrappingKey = await crypto.subtle.generateKey({ name: 'AES-GCM', length: 256 }, false, ['encrypt', 'decrypt']);
        const record = { version: VERSION, id: crypto.randomUUID(), revision: crypto.randomUUID(), owner: context.owner,
            epoch: context.epoch, verifiedAt: context.serverTime, expiresAt: context.expiresAt };
        if (!this.valid(record)) throw new Error('Check the device clock before trusting this device.');
        // Conversion does not extend snapshot validity without a complete fresh download.
        if (existing) {
            record.verifiedAt = existing.verifiedAt; record.expiresAt = existing.expiresAt;
            record.snapshotSaved = existing.snapshotSaved;
        }
        await this.sealRecord(record, { method: 'trusted', key: wrappingKey }, wrappingKey);
        const raw = await this.decrypt(wrappingKey, record.wrapper.sealed, record, 'key');
        const key = await crypto.subtle.importKey('raw', raw, 'AES-GCM', false, ['encrypt', 'decrypt']);
        new Uint8Array(raw).fill(0);
        record.payload = await this.crypt(key, bytes(JSON.stringify(payload)), record, 'payload');
        if (generation !== this.generation) throw new Error('Device trust was cancelled by a session change.');
        localStorage.removeItem('kmp.offline.revoked');
        await this.activateRecord(record, wrappingKey, generation, existing?.revision);
        this.trusted = true;
    }

    /** Reopen only deliberately trusted records; never trigger a credential prompt. */
    async openTrusted() {
        if (this.clearing) return false;
        const record = await this.metadata();
        if (record?.wrapper.method !== 'trusted') return false;
        const meta = document.querySelector('meta[name="kmp-offline-session"]');
        if (meta) {
            let context;
            try { context = JSON.parse(meta.content); } catch { await this.clear(); return false; }
            if (context && (context.impersonating || context.owner !== record.owner || context.epoch !== record.epoch)) {
                await this.clear(); return false;
            }
        }
        if (record.wrapper.unlockMethod && this.key) {
            let session;
            try { session = JSON.parse(sessionStorage.getItem('kmp.offline.session') || 'null'); } catch { /* Locked below. */ }
            if (!session || session.until <= Date.now() || session.lock !== localStorage.getItem('kmp.offline.lock')) this.lock(false);
        }
        if (this.key && this.activeId === record.id) return true;
        if (record.wrapper.unlockMethod) return this.restoreSession(record);
        if (localStorage.getItem('kmp.offline.signedOut') === '1' && !JSON.parse(meta?.content || 'null')?.owner) return false;
        if (!this.opening) this.opening = this.unlock('', record).finally(() => { this.opening = null; });
        await this.opening;
        return true;
    }

    async sealRecord(record, wrapper, wrappingKey, payload = null) {
        const raw = random(32);
        try {
            const key = await crypto.subtle.importKey('raw', raw, 'AES-GCM', false, ['encrypt', 'decrypt']);
            record.wrapper = { ...wrapper, sealed: await this.crypt(wrappingKey, raw, record, 'key') };
            record.payload = await this.crypt(key, bytes(JSON.stringify(payload || { card: null, months: {}, rsvps: [], pending: [] })), record, 'payload');
        } finally { raw.fill(0); }
    }

    /** Called directly from a new user gesture; incomplete setup stays only in memory. */
    async continueDeviceEnrollment() {
        const pending = this.pendingDevice;
        if (!pending || pending.generation !== this.generation) throw new Error('Start device setup again.');
        const wrappingKey = await this.deviceKey(pending.wrapper);
        if (pending.generation !== this.generation) throw new Error('Offline setup was cancelled by a session change.');
        if (!pending.record.payload) {
            await this.sealRecord(pending.record, pending.wrapper, wrappingKey, pending.payload);
            return 'verify';
        }
        localStorage.removeItem('kmp.offline.revoked');
        await this.activateRecord(pending.record, wrappingKey, pending.generation, pending.revision);
        this.pendingDevice = null;
        forgetPasskeyFailure();
        return 'complete';
    }

    async activateRecord(record, wrappingKey, generation, revision = null) {
        const raw = await this.decrypt(wrappingKey, record.wrapper.sealed, record, 'key');
        const key = await crypto.subtle.importKey('raw', raw, 'AES-GCM', false, ['encrypt', 'decrypt']);
        new Uint8Array(raw).fill(0);
        await this.decrypt(key, record.payload, record, 'payload');
        if (generation !== this.generation || !this.valid(record)) throw new Error('Offline setup was cancelled or expired.');
        await this.commit(record, revision);
        if (generation !== this.generation) {
            await this.commit(null, record.revision).catch(() => {});
            throw new Error('Offline setup was cancelled.');
        }
        this.key = key; this.wrappingKey = wrappingKey; this.activeId = record.id;
        this.trusted = record.wrapper.method === 'trusted';
        await this.rememberSession(record, wrappingKey);
        this.channel?.postMessage('changed');
        announce();
    }

    async unlock(passphrase = '', preparedRecord = null) {
        const generation = this.generation;
        const record = preparedRecord || await this.metadata();
        if (!record || !this.valid(record)) throw new Error('Connect and sign in to refresh offline access.');
        const method = record.wrapper.unlockMethod || record.wrapper.method;
        const attemptsKey = `kmp.offline.attempts.${record.id}`;
        let attempts = {};
        try { attempts = JSON.parse(localStorage.getItem(attemptsKey) || '{}'); } catch { /* Invalid local hint. */ }
        if (method === 'pin' && attempts.until > Date.now()) throw new Error('Too many PIN attempts. Wait a minute and try again.');
        try {
            const wrappingKey = method === 'trusted' ? record.wrapper.key : method === 'device' ? await this.deviceKey(record.wrapper)
                : await this.passphraseKey(passphrase, record.wrapper.salt, record.wrapper.iterations, !!record.wrapper.unlockMethod);
            const raw = await this.decrypt(wrappingKey, record.wrapper.sealed, record, 'key');
            const key = await crypto.subtle.importKey('raw', raw, 'AES-GCM', false, ['encrypt', 'decrypt']);
            new Uint8Array(raw).fill(0);
            await this.decrypt(key, record.payload, record, 'payload');
            // Logout/another enrollment may have occurred while the OS prompt was open.
            const latest = await this.stored();
            if (latest?.id !== record.id || !this.valid(latest) || generation !== this.generation) throw new Error('Offline data changed.');
            this.key = key; this.wrappingKey = wrappingKey; this.activeId = record.id;
            this.trusted = record.wrapper.method === 'trusted';
            await this.rememberSession(record, wrappingKey);
            localStorage.removeItem(attemptsKey);
            localStorage.removeItem('kmp.offline.signedOut');
            announce();
        } catch {
            if (method === 'pin') localStorage.setItem(attemptsKey, JSON.stringify({ count: (attempts.count || 0) + 1,
                until: (attempts.count || 0) >= 4 ? Date.now() + 60000 : 0 }));
            this.lock(); throw new Error('Unable to unlock. Check your PIN or passkey and try again.');
        }
    }

    /** Keep this tab unlocked across page navigation, bounded by logout and a twelve-hour session. */
    async rememberSession(record, wrappingKey) {
        if (!record.wrapper.unlockMethod) return;
        const generation = this.generation;
        const raw = await crypto.subtle.exportKey('raw', wrappingKey);
        try {
            if (generation !== this.generation) throw new Error('Device was locked.');
            sessionStorage.setItem('kmp.offline.session', JSON.stringify({ id: record.id,
                key: b64(raw), until: Date.now() + 12 * 60 * 60 * 1000,
                lock: localStorage.getItem('kmp.offline.lock') }));
        } finally { new Uint8Array(raw).fill(0); }
    }

    async restoreSession(record) {
        const generation = this.generation;
        try {
            const session = JSON.parse(sessionStorage.getItem('kmp.offline.session') || 'null');
            if (!session || session.id !== record.id || session.until <= Date.now() ||
                session.lock !== localStorage.getItem('kmp.offline.lock')) return false;
            const wrappingKey = await crypto.subtle.importKey('raw', unb64(session.key), 'AES-GCM', true, ['encrypt', 'decrypt']);
            const raw = await this.decrypt(wrappingKey, record.wrapper.sealed, record, 'key');
            const key = await crypto.subtle.importKey('raw', raw, 'AES-GCM', false, ['encrypt', 'decrypt']);
            new Uint8Array(raw).fill(0);
            if (generation !== this.generation) return false;
            this.key = key; this.wrappingKey = wrappingKey; this.activeId = record.id; this.trusted = true;
            announce();
            return true;
        } catch { sessionStorage.removeItem('kmp.offline.session'); return false; }
    }

    signOut() {
        localStorage.setItem('kmp.offline.signedOut', '1');
        this.lock();
    }

    lock(broadcast = true) {
        sessionStorage.removeItem('kmp.offline.session');
        this.generation++;
        this.promptController?.abort();
        this.pendingDevice = null;
        this.key = null; this.wrappingKey = null; this.activeId = null; this.trusted = false;
        window.dispatchEvent(new CustomEvent('kmp:offline-revoked'));
        if (broadcast) {
            this.channel?.postMessage('locked');
            try { localStorage.setItem('kmp.offline.lock', crypto.randomUUID()); } catch { /* Storage may be disabled. */ }
        }
        announce();
    }

    async clear() {
        if (this.clearing) return this.clearing;
        sessionStorage.removeItem('kmp.offline.session');
        this.generation++;
        this.promptController?.abort();
        this.pendingDevice = null;
        this.key = null; this.wrappingKey = null; this.activeId = null; this.trusted = false;
        window.dispatchEvent(new CustomEvent('kmp:offline-revoked'));
        this.channel?.postMessage('cleared');
        try { localStorage.setItem('kmp.offline.revoked', '1'); localStorage.setItem('kmp.offline.lock', crypto.randomUUID()); } catch { /* Storage may be disabled. */ }
        this.clearing = this.commit(null).finally(() => { this.clearing = null; announce(); });
        return this.clearing;
    }

    async read(allowExpired = false) {
        const record = await this.metadata();
        if (record && record.expiresAt <= Date.now() && !allowExpired) throw new Error('Connect and sign in to update your saved information. Waiting RSVPs are kept.');
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
        const payload = await this.read(true);
        // A foreground response may be newer than a background snapshot still downloading.
        if (snapshot.resourceTimes) {
            const times = { ...snapshot.resourceTimes };
            snapshot = { ...snapshot, months: { ...snapshot.months }, resourceTimes: times };
            const snapshotStarted = Math.min(...Object.values(times));
            for (const [resource, observedAt] of Object.entries(payload.resourceTimes || {})) {
                if (!(resource in times) && observedAt < snapshotStarted) continue;
                if (observedAt <= (times[resource] || 0)) continue;
                if (resource === 'card' || resource === 'rsvps') snapshot[resource] = payload[resource];
                else if (resource.startsWith('month:')) snapshot.months[resource.slice(6)] = payload.months[resource.slice(6)];
                times[resource] = observedAt;
            }
        }
        const revision = record.revision;
        record.verifiedAt = context.serverTime; record.expiresAt = context.expiresAt;
        record.snapshotSaved = !!snapshot.card;
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
