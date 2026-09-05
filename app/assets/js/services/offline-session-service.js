import vault, { purgeLegacyOfflineStorage } from './offline-vault-service.js';

/** Keep logout/account/impersonation transitions effective across pages and tabs. */
export function startOfflineSessionObserver() {
    const shell = !!document.querySelector('meta[name="kmp-offline-shell"]');
    const meta = document.querySelector('meta[name="kmp-offline-session"]');
    const inspect = async () => {
        await purgeLegacyOfflineStorage();
        if (shell || !meta) return;
        let context = null;
        try { context = JSON.parse(meta.content); } catch { /* Invalid context clears storage. */ }
        const record = await vault.metadata();
        if (record && (!context || context.impersonating || context.owner !== record.owner || context.epoch !== record.epoch)) await vault.clear();
    };
    inspect().catch(() => vault.lock());
    // Existing desktop tabs must also replace a previously registered unsafe mobile worker.
    navigator.serviceWorker?.getRegistration?.('/').then(registration => registration?.update()).catch(() => {});
    window.addEventListener('pagehide', () => vault.lock());
    window.addEventListener('pageshow', event => {
        if (!event.persisted) return;
        vault.lock();
        if (!shell && meta) location.replace(navigator.onLine ? location.href : '/offline');
    });
    document.addEventListener('submit', event => {
        const path = new URL(event.target.action || location.href, location.origin).pathname;
        if (/\/members\/(?:logout|impersonate|stop-impersonating)(?:\/|$)/i.test(path)) vault.clear().catch(() => vault.lock());
    }, true);
    document.addEventListener('click', event => {
        const link = event.target.closest?.('a[href]');
        if (link && /\/members\/logout(?:\/|$)/i.test(new URL(link.href, location.origin).pathname)) vault.clear().catch(() => vault.lock());
    }, true);
    navigator.serviceWorker?.addEventListener('message', event => {
        if (event.data?.type === 'OFFLINE_SECURITY_UPDATE') inspect().catch(() => vault.lock());
    });
    // A random signal reveals no actor or data and also reaches browsers without BroadcastChannel.
    window.addEventListener('storage', event => {
        if (event.key === 'kmp.offline.lock') vault.lock(false);
    });
}
