/** One owner for document navigation and same-document UI history. */
let turbo;
let documentId;
let installed = false;
let validating;
let pendingNavigation;
let renderVersion = 0;
let contextPending = false;
const meta = name => document.querySelector(`meta[name="${name}"]`)?.content;
export const driveEnabled = () => meta('kmp-turbo-enabled') === 'true';

export function usesNativeNavigation(destination) {
    const url = new URL(destination, window.location.href);
    if (!driveEnabled() || url.origin !== window.location.origin) return true;
    let rules;
    try { rules = JSON.parse(meta('kmp-native-navigation')); } catch { return true; }
    if (!rules) return true;
    const path = url.pathname.toLowerCase();
    return rules.controllers.some(controller => path.split('/').includes(controller))
        || (rules.paths || []).some(prefix => path === prefix || path.startsWith(prefix + '/'))
        || rules.actions.some(action => path.includes(action))
        || rules.extensions.some(extension => path.endsWith(`.${extension}`));
}

/** Programmatic visits follow the same Drive/native contract as ordinary links. */
export function visitDocument(destination, options = {}) {
    if (!usesNativeNavigation(destination) && turbo) turbo.visit(destination, options);
    else window.location.assign(destination);
}

export function updateUiHistory(url, { replace = false, nestedTabs } = {}) {
    const destination = new URL(url, window.location.href);
    if (destination.origin !== window.location.origin) throw new Error('UI history must stay on this origin.');
    const previous = window.history.state || {};
    const local = { ...previous.kmp, documentId, uiDepth: (previous.kmp?.uiDepth || 0) + (replace ? 0 : 1) };
    if (nestedTabs) local.nestedTabs = { ...previous.kmp?.nestedTabs, ...nestedTabs };
    if (driveEnabled() && turbo) {
        const identifier = replace ? previous.turbo?.restorationIdentifier : crypto.randomUUID();
        turbo.session.history[replace ? 'replace' : 'push'](destination, identifier);
        if (turbo.session.view) turbo.session.view.lastRenderedLocation = destination;
        window.history.replaceState({ ...previous, ...window.history.state, kmp: local }, '', destination);
    } else {
        const state = turbo || nestedTabs ? { ...previous, kmp: local } : previous;
        window.history[replace ? 'replaceState' : 'pushState'](state, '', destination);
    }
}

/** Capture before Turbo's bubble listener; only local UI entries skip document restoration. */
function restoreLocal(event) {
    if (!driveEnabled() || event.state?.kmp?.documentId !== documentId) return;
    // Back can arrive after Turbo advances the URL but before its new document
    // renders. Turbo must see that popstate to cancel the unfinished visit.
    if (pendingNavigation?.documentId && pendingNavigation.documentId !== documentId) return;
    event.stopImmediatePropagation();
    const history = turbo.session.history;
    history.location = new URL(window.location.href);
    history.restorationIdentifier = event.state.turbo?.restorationIdentifier;
    history.currentIndex = event.state.turbo?.restorationIndex ?? history.currentIndex;
    if (turbo.session.view) turbo.session.view.lastRenderedLocation = history.location;
    window.dispatchEvent(new CustomEvent('kmp:ui-history-restored'));
    window.dispatchEvent(new CustomEvent('page-context:sync'));
}

function activatePage() {
    const enabled = driveEnabled();
    turbo.session.drive = enabled;
    if (enabled) {
        if (!turbo.session.history.started) {
            const previousState = window.history.state;
            turbo.session.history.start();
            window.history.replaceState({ ...previousState, ...window.history.state }, '', window.location.href);
        }
        // Controllers may synchronize a new page's grids before turbo:load. The
        // pending document visit still owns its return index and document identity.
        const local = { uiDepth: 0, ...window.history.state?.kmp, ...pendingNavigation };
        documentId = local.documentId || crypto.randomUUID();
        window.history.replaceState({ ...window.history.state, kmp: { ...local, documentId } }, '', window.location.href);
        document.querySelectorAll('[data-kmp-back]').forEach(link => {
            link.dataset.turbo = 'true';
        });
    } else {
        turbo.session.history.stop();
        documentId = window.history.state?.kmp?.documentId || crypto.randomUUID();
        window.history.replaceState({ ...window.history.state, kmp: {
            ...window.history.state?.kmp, documentId, uiDepth: window.history.state?.kmp?.uiDepth || 0,
        } }, '', window.location.href);
    }
    pendingNavigation = null;
    if (!contextPending) document.documentElement.removeAttribute('data-kmp-cache-pending');
    window.dispatchEvent(new CustomEvent('kmp:navigation-ready'));
}

/** Return through real history, skipping only UI-only entries belonging to this screen. */
export function goBack() {
    const state = window.history.state;
    const previous = state?.kmp?.previousDocumentIndex;
    const current = state?.turbo?.restorationIndex;
    if (driveEnabled() && Number.isInteger(previous) && Number.isInteger(current) && previous < current) {
        window.history.go(previous - current);
        return true;
    }
    if (document.referrer) {
        const referrer = new URL(document.referrer);
        if (referrer.origin === window.location.origin && !/login|logout|password|impersonat|mfa/i.test(referrer.pathname)) {
            window.history.go(-((state?.kmp?.uiDepth || 0) + 1));
            return true;
        }
    }
    return false;
}

async function validateContext(expected, version) {
    try {
        validating ??= fetch(meta('kmp-navigation-context-url'), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin', cache: 'no-store',
            signal: AbortSignal.timeout(5000),
        }).then(async response => {
            if (!response.ok || response.redirected) throw new Error('Session validation failed.');
            return response.json();
        }).finally(() => { validating = null; });
        const current = await validating;
        if (version !== renderVersion) return false;
        if (current.context !== expected) throw new Error('The session context changed.');
        return true;
    } catch {
        if (version !== renderVersion) return false;
        invalidateUiSession();
        return false;
    }
}

/** A tenant-wide access signal only invalidates this user's changed authorization. */
export function revalidateUiSession() {
    return validateContext(meta('kmp-navigation-context'), renderVersion);
}

/** Revalidate through the normal document boundary before retaining identity-dependent content. */
export function invalidateUiSession() {
    document.documentElement.setAttribute('data-kmp-cache-pending', '');
    turbo?.cache.clear();
    window.dispatchEvent(new Event('kmp:session-invalidated'));
    window.location.reload();
}

function validateSnapshot(event) {
    if (!driveEnabled() || !event.detail?.newBody) return;
    const cached = document.documentElement.hasAttribute('data-turbo-preview')
        || event.detail.newBody.hasAttribute('data-kmp-snapshot');
    const version = ++renderVersion;
    if (cached) {
        contextPending = true;
        document.documentElement.setAttribute('data-kmp-cache-pending', '');
    }
    const expected = event.detail.newBody.dataset.kmpContext;
    const reveal = () => {
        contextPending = false;
        document.documentElement.removeAttribute('data-kmp-cache-pending');
    };
    const render = event.detail.render;
    if (typeof render === 'function') {
        // Await through Turbo's renderer rather than pausing with resume: a
        // superseded visit must finish its render promise without painting stale
        // content, otherwise a rapid Back leaves the following restore blocked.
        event.detail.render = async (...args) => {
            if (version !== renderVersion) return;
            if (cached && !await validateContext(expected, version)) return;
            if (version !== renderVersion) return;
            await render(...args);
            if (version === renderVersion) reveal();
        };
    } else if (cached) {
        // Native BFCache restoration already owns its body and has no renderer.
        event.preventDefault();
        validateContext(expected, version).then(valid => {
            if (valid && version === renderVersion) { reveal(); event.detail.resume(); }
        });
    }
}

export function installUiNavigation(runtime) {
    if (installed) return;
    installed = true;
    turbo = runtime;
    // Window-target popstate listeners run in registration order. Register the
    // UI broker before restarting Turbo's observer so it cannot restore first.
    turbo.session.history.stop();
    window.addEventListener('popstate', restoreLocal, true);
    document.addEventListener('click', event => {
        const link = event.target.closest?.('a[href]');
        if (driveEnabled() && link && usesNativeNavigation(link.href)) link.dataset.turbo = 'false';
    }, true);
    document.addEventListener('submit', event => {
        if (driveEnabled() && event.target instanceof HTMLFormElement
            && usesNativeNavigation(event.submitter?.getAttribute('formaction') || event.target.action)) {
            event.target.dataset.turbo = 'false';
        }
    }, true);
    window.addEventListener('kmp:mutation-committed', () => turbo.cache.clear());
    document.addEventListener('turbo:load', activatePage);
    document.addEventListener('turbo:before-render', validateSnapshot);
    window.addEventListener('pagehide', () => {
        if (!driveEnabled()) return;
        document.body.dataset.kmpSnapshot = 'true';
        document.body.dataset.kmpContext = meta('kmp-navigation-context') || '';
        document.documentElement.setAttribute('data-kmp-cache-pending', '');
    });
    window.addEventListener('pageshow', event => {
        if (event.persisted && driveEnabled()) validateSnapshot({
            preventDefault() {}, detail: { newBody: document.body, resume: activatePage },
        });
    });
    document.addEventListener('turbo:visit', event => {
        renderVersion++;
        const destination = new URL(event.detail.url, window.location.href);
        pendingNavigation = event.detail.action === 'restore' ? null
            : destination.pathname === window.location.pathname ? window.history.state?.kmp
                : { documentId: crypto.randomUUID(),
                    previousDocumentIndex: window.history.state?.turbo?.restorationIndex, uiDepth: 0 };
    });
    document.addEventListener('click', event => {
        const link = event.target.closest('[data-kmp-back]');
        if (!driveEnabled() || !link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        if (goBack()) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }, true);
    document.addEventListener('turbo:before-cache', () => {
        document.body.dataset.kmpSnapshot = 'true';
        document.body.dataset.kmpContext = meta('kmp-navigation-context') || '';
        document.querySelectorAll('input[type="password"], input[type="file"]').forEach(input => { input.value = ''; });
        document.getElementById('flash-messages')?.replaceChildren();
        document.querySelectorAll('[data-turbo-temporary], .tooltip, .popover, .modal-backdrop').forEach(node => node.remove());
    });
    activatePage();
}
