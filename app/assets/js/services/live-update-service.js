import { maintainLiveConnection } from './live-connection-service.js';
import { driveEnabled, invalidateUiSession, revalidateUiSession } from './ui-navigation-service.js';
import { dirtyWithin } from './form-state-service.js';
import { refreshAfterChanges, refreshScreen } from './page-refresh-service.js';

/** One stream per document; messages never contain user-specific record markup. */
export function installLiveUpdates(Turbo) {
    let abort;
    let poll;
    let path;
    let leaseContext;
    let pending = new Set();
    let refreshTimer;
    let refreshInFlight = Promise.resolve();
    let lastStatus;
    let connectionStatus;
    const meta = name => document.querySelector(`meta[name="${name}"]`)?.content;
    const csrf = () => meta('csrf-token');
    const contextUrl = () => window.location.pathname + window.location.search;

    function stop() {
        abort?.abort(); clearInterval(poll); clearTimeout(refreshTimer);
        abort = null; poll = null; pending.clear();
    }

    function status(message) {
        lastStatus = message;
        let region = document.getElementById('kmp-live-status');
        if (!region) {
            region = document.createElement('div');
            region.id = 'kmp-live-status'; region.setAttribute('role', 'status');
            region.className = 'small text-muted px-3 py-1';
            region.dataset.turboTemporary = '';
            (document.querySelector('main') || document.body).prepend(region);
        }
        if (region.textContent !== message) region.textContent = message;
    }

    function schedule(changes = ['record']) {
        if (changes.includes('permissions')) {
            const originPath = path;
            revalidateUiSession().then(valid => {
                if (valid && originPath === window.location.pathname) {
                    schedule([...changes.filter(change => change !== 'permissions'), 'record']);
                }
            });
            return;
        }
        changes.forEach(change => pending.add(change));
        clearTimeout(refreshTimer);
        refreshTimer = setTimeout(() => {
            const current = [...pending]; pending.clear();
            const originPath = path;
            refreshInFlight = refreshInFlight.catch(() => {}).then(async () => {
                if (originPath !== window.location.pathname) return;
                const declared = current.filter(change => document.querySelector(
                    `[data-refresh-on~="${CSS.escape(change)}"], [data-refresh-frame-on~="${CSS.escape(change)}"]`,
                ));
                if (declared.length === current.length) {
                    await refreshAfterChanges(declared, { visibleOnly: true });
                    if (connectionStatus) status(connectionStatus);
                } else {
                    let refreshed = false;
                    if (!dirtyWithin(document.body) && !document.querySelector('.modal.show')) refreshed = await refreshScreen();
                    if (!refreshed) {
                        // Known clean summaries can advance even while an unrelated editor blocks the screen.
                        if (declared.length) await refreshAfterChanges(declared, { visibleOnly: true });
                        current.forEach(change => pending.add(change));
                        status('Updates are available. Finish or cancel your current edit to refresh them.');
                        const button = document.createElement('button');
                        button.type = 'button'; button.className = 'btn btn-sm btn-outline-secondary ms-2';
                        button.textContent = 'Refresh details';
                        button.addEventListener('click', () => schedule([...pending]));
                        document.getElementById('kmp-live-status')?.append(button);
                    } else if (connectionStatus) status(connectionStatus);
                }
            }).catch(() => {
                if (originPath !== window.location.pathname) return;
                current.forEach(change => pending.add(change));
                status('Some details could not be refreshed. Updates will retry automatically.');
                clearTimeout(refreshTimer);
                refreshTimer = setTimeout(() => schedule([...pending]), 30000);
            });
        }, 150);
    }

    Turbo.StreamActions['kmp-invalidate'] = function () {
        const context = this.getAttribute('context');
        if (context && new URL(context, window.location.origin).pathname !== window.location.pathname) return;
        let changes;
        try { changes = JSON.parse(this.getAttribute('changes') || '["record"]'); } catch { return; }
        if (!Array.isArray(changes) || !changes.length || changes.length > 64
            || !changes.every(change => typeof change === 'string' && /^[a-z-]+$/.test(change))) return;
        // A signal may beat its mutation response, or that response may be lost.
        // Reconcile committed changes even for the actor; receipt of a request ID
        // alone does not prove every dependent pane has been refreshed.
        Turbo.cache.clear();
        schedule(changes);
    };

    function connect(controller) {
        const subscriptionUrl = meta('kmp-live-subscriptions-url');
        if (!subscriptionUrl || !driveEnabled()) return;
        maintainLiveConnection({ signal: controller.signal,
            authorize: async signal => {
                const subscription = await fetch(subscriptionUrl, {
                    method: 'POST', credentials: 'same-origin', cache: 'no-store', signal,
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrf() },
                    body: JSON.stringify({ context_url: contextUrl() }),
                });
                if (subscription.redirected || [401, 403, 404].includes(subscription.status)) { invalidateUiSession(); return null; }
                if (subscription.status === 400) return null;
                if (!subscription.ok) throw new Error('Subscription unavailable');
                const ticket = await subscription.json();
                if (controller.signal.aborted || path !== window.location.pathname) return null;
                if (ticket.context !== meta('kmp-navigation-context')) { invalidateUiSession(); return null; }
                return ticket;
            },
            onEvent: event => {
                if (!controller.signal.aborted && path === window.location.pathname) Turbo.renderStreamMessage(event.data);
            },
            onStatus: state => {
                if (state === 'connected') {
                    clearInterval(poll); poll = null; connectionStatus = 'Live updates connected.';
                } else {
                    connectionStatus = 'Live updates are reconnecting. Details will be checked periodically.';
                    poll ??= setInterval(() => { if (!document.hidden) schedule(); }, 30000);
                }
                status(connectionStatus);
            },
        });
    }

    window.addEventListener('kmp:navigation-ready', () => {
        const nextContext = meta('kmp-navigation-context');
        if (abort && !abort.signal.aborted && path === window.location.pathname && leaseContext === nextContext) return;
        stop(); path = window.location.pathname;
        leaseContext = nextContext;
        abort = new AbortController(); connect(abort);
    });
    document.addEventListener('turbo:before-visit', event => {
        queueMicrotask(() => {
            if (!event.defaultPrevented && new URL(event.detail.url, window.location.href).pathname !== path) stop();
        });
    });
    window.addEventListener('pagehide', stop);
    window.addEventListener('kmp:session-invalidated', stop);
    document.addEventListener('turbo:render', () => {
        if (abort && !abort.signal.aborted && lastStatus && meta('kmp-live-subscriptions-url')) status(lastStatus);
    });
    window.addEventListener('kmp:screen-refreshed', () => {
        if (lastStatus && meta('kmp-live-subscriptions-url')) status(lastStatus);
    });
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && driveEnabled() && meta('kmp-live-subscriptions-url')) schedule();
    });
    document.addEventListener('hidden.bs.modal', () => { if (pending.size) schedule([...pending]); });
    document.addEventListener('turbo-modal:saved', () => { if (pending.size) schedule([...pending]); });
    window.addEventListener('kmp:draft-settled', () => { if (pending.size) schedule([...pending]); });
}
