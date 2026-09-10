import vault, { purgeLegacyOfflineStorage } from './offline-vault-service.js';
import { updateTrustedDevice } from './offline-runtime-service.js';

/** Trust survives navigation and session timeout; explicit exit and actor changes remove it. */
export function startOfflineSessionObserver() {
    const shell = !!document.querySelector('meta[name="kmp-offline-shell"]');
    const inspect = async () => {
        await purgeLegacyOfflineStorage();
        const meta = document.querySelector('meta[name="kmp-offline-session"]');
        let context = null;
        try { context = meta ? JSON.parse(meta.content) : null; } catch { await vault.clear(); return; }
        if (!shell && context && !context.impersonating) sessionStorage.removeItem('kmp.offline.resume');
        const record = await vault.metadata();
        if (!record) return;
        if (context && (context.impersonating || context.owner !== record.owner || context.epoch !== record.epoch)) {
            await vault.clear(); return;
        }
        if (!context && record.wrapper.method !== 'trusted' && !shell) { await vault.clear(); return; }
        // Deliberate browser trust permits local opening even when a nominal connection is unusable.
        // The coordinator verifies the live account before refreshing or sending any requests.
        await updateTrustedDevice();
    };
    const retry = () => inspect().catch(() => vault.lock(false));
    retry();
    navigator.serviceWorker?.getRegistration?.('/').then(registration => registration?.update()).catch(() => {});
    window.addEventListener('pagehide', () => { if (!vault.trusted) vault.lock(); });
    window.addEventListener('pageshow', event => {
        if (!event.persisted) return;
        vault.lock(false);
        if (!shell) location.replace(navigator.onLine ? location.href : '/offline');
        else retry();
    });
    window.addEventListener('online', retry);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) retry(); });
    setInterval(() => { if (!document.hidden && navigator.onLine) retry(); }, 60000);
    window.addEventListener('rsvp-cache:rsvp-queued', () => updateTrustedDevice(true).catch(() => {}));
    const exitPath = path => /\/members\/(?:logout|impersonate|stop-impersonating)(?:\/|$)/i.test(path);
    document.addEventListener('submit', event => {
        const path = new URL(event.target.action || location.href, location.origin).pathname;
        if (/\/members\/logout\/?$/i.test(path)) vault.signOut();
        else if (exitPath(path)) vault.clear().catch(() => vault.lock());
    }, true);
    document.addEventListener('click', event => {
        const link = event.target.closest?.('a[href]');
        if (!link) return;
        const path = new URL(link.href, location.origin).pathname;
        if (/\/members\/logout\/?$/i.test(path)) vault.signOut();
        else if (exitPath(path)) vault.clear().catch(() => vault.lock());
    }, true);
    navigator.serviceWorker?.addEventListener('message', event => {
        if (event.data?.type === 'OFFLINE_SECURITY_UPDATE') retry();
    });
    window.addEventListener('storage', event => {
        if (event.key === 'kmp.offline.lock' || event.key === 'kmp.offline.revoked') vault.lock(false);
    });
}
