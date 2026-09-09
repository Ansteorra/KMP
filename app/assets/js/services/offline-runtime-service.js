import { shortSiteTitle } from './app-branding-service.js';
import vault from './offline-vault-service.js';
import { ensureDeviceSession } from './device-login-service.js';
import { currentOfflineContext, refreshOfflineSnapshot } from './offline-data-service.js';
import rsvps from './rsvp-cache-service.js';

export const offlineStatus = { message: '', ready: false };
let updating;
let lastUpdate = 0;
const announce = message => {
    offlineStatus.message = message;
    window.dispatchEvent(new CustomEvent('kmp:offline-progress'));
};

export function reportOfflineSaveFailure() {
    lastUpdate = 0;
    offlineStatus.ready = false;
    announce(`Your latest information could not be saved on this device. ${shortSiteTitle()} will retry automatically.`);
}

export async function prepareOfflineShell() {
    if (!('serviceWorker' in navigator)) throw new Error('This browser does not support offline access.');
    const registration = await navigator.serviceWorker.register('/sw.js', { updateViaCache: 'none' });
    const worker = registration.installing || registration.waiting || registration.active;
    if (!worker) throw new Error('Offline setup is not ready. Please try again.');
    if (worker?.state !== 'activated') await new Promise((resolve, reject) => {
        const timer = setTimeout(() => { worker?.removeEventListener('statechange', changed); reject(new Error('Offline update is not ready. Reconnect and reload.')); }, 60000);
        const changed = () => {
            if (worker.state === 'activated') { clearTimeout(timer); worker.removeEventListener('statechange', changed); resolve(); }
            if (worker.state === 'redundant') { clearTimeout(timer); worker.removeEventListener('statechange', changed); reject(new Error('Offline update failed. Reload online.')); }
        };
        worker?.addEventListener('statechange', changed);
        changed();
    });
    await new Promise((resolve, reject) => {
        const channel = new MessageChannel();
        const timer = setTimeout(() => { channel.port1.close(); reject(new Error('Offline preparation timed out. Reload online.')); }, 60000);
        channel.port1.onmessage = event => {
            clearTimeout(timer); channel.port1.close();
            if (event.data?.ready) resolve(); else reject(new Error('Offline assets could not be saved. Reconnect and retry.'));
        };
        registration.active.postMessage({ type: 'PREPARE_OFFLINE' }, [channel.port2]);
    });
}

/** One foreground coordinator shared by ordinary pages and the offline shell. */
export async function updateTrustedDevice(force = false) {
    // A reconnect can arrive while a request made before reconnection is still failing.
    if (updating) return force ? updating.then(() => updateTrustedDevice()) : updating;
    updating = (async () => {
        if (!await vault.openTrusted()) return;
        if (!navigator.onLine) { announce('You’re offline. Changes will send when you reconnect.'); return; }
        if (!force && Date.now() - lastUpdate < 300000) return;
        announce('Saving your information for offline use…');
        try {
            await ensureDeviceSession();
            await prepareOfflineShell();
            const pending = await rsvps.getPendingCount();
            if (pending) {
                const result = await rsvps.syncPendingRsvps();
                if (result.failed || result.skipped) throw new Error(`Your RSVPs are waiting to send. ${shortSiteTitle()} will retry automatically.`);
            }
            await refreshOfflineSnapshot();
            lastUpdate = Date.now();
            offlineStatus.ready = true;
            window.dispatchEvent(new CustomEvent('kmp:offline-source', { detail: { offline: false } }));
            announce('Saved on this device. Ready offline.');
        } catch (error) {
            offlineStatus.ready = false;
            announce(error.message?.includes('Sign in')
                ? 'Sign in to update and send waiting RSVPs. Your saved information is kept.'
                : `Waiting for a connection. Your saved information and unsent RSVPs are kept; ${shortSiteTitle()} will retry.`);
        }
    })().finally(() => { updating = null; });
    return updating;
}

/** Explicit user choice; browser storage persistence is best effort, not an extra setup step. */
export async function trustThisDevice() {
    const generation = vault.generation;
    await currentOfflineContext();
    await prepareOfflineShell();
    const context = await currentOfflineContext();
    if (generation !== vault.generation) throw new Error('Your sign-in changed. Try trusting this device again.');
    await vault.trust(context);
    navigator.storage?.persist?.().catch(() => false);
    await updateTrustedDevice(true);
}
