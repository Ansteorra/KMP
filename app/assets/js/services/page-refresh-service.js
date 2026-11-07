const regionVersions = new WeakMap();
import { dirtyWithin } from './form-state-service.js';
import { invalidateUiSession } from './ui-navigation-service.js';

let documentVersion = 0;
const deferredTopics = new Set();
window.addEventListener('kmp:navigation-ready', () => { documentVersion++; });

function deferRegion(region, topics) {
    region.dataset.kmpStale = 'true';
    region.dataset.kmpStaleTopics = [...new Set([...(region.dataset.kmpStaleTopics || '').split(' '), ...topics])].filter(Boolean).join(' ');
    topics.forEach(topic => deferredTopics.add(topic));
    let notice = document.getElementById('kmp-deferred-updates');
    if (notice) return;
    notice = document.createElement('div');
    notice.id = 'kmp-deferred-updates';
    notice.className = 'alert alert-info';
    notice.dataset.turboTemporary = '';
    notice.setAttribute('role', 'status');
    notice.textContent = 'Updates are available. Your unsaved changes have been kept. Finish or cancel your edit, then refresh. ';
    const button = document.createElement('button');
    button.type = 'button'; button.className = 'btn btn-sm btn-outline-dark'; button.textContent = 'Refresh details';
    button.addEventListener('click', reconcileDeferred);
    notice.append(button);
    (document.getElementById('flash-messages') || document.querySelector('main') || document.body).prepend(notice);
}

async function reconcileDeferred() {
    if (!deferredTopics.size) return;
    const topics = [...deferredTopics]; deferredTopics.clear();
    try {
        await refreshAfterChanges(topics, { visibleOnly: true });
        if (![...document.querySelectorAll('[data-kmp-stale="true"]')].length) {
            document.getElementById('kmp-deferred-updates')?.remove();
        }
    } catch {
        topics.forEach(topic => deferredTopics.add(topic));
        window.KMP_accessibility?.announce('Updates could not be loaded. Your changes have been kept. Please try again.');
    }
}
window.addEventListener('kmp:draft-settled', () => queueMicrotask(reconcileDeferred));
document.addEventListener('turbo-modal:saved', () => queueMicrotask(reconcileDeferred));

/** Retain the existing one-topic API for mutation callers. */
export async function refreshAfterChange(topic, options = {}) {
    return refreshAfterChanges([topic], options);
}

function eligible(region, topics, visibleOnly) {
    if (!visibleOnly || region.getClientRects().length) return true;
    region.dataset.kmpStaleTopics = [...new Set([...(region.dataset.kmpStaleTopics || '').split(' '), ...topics])].filter(Boolean).join(' ');
    return false;
}

/** Batch a committed dependency set into one region GET and one GET per distinct frame. */
export async function refreshAfterChanges(topics, { source = null, context = null, visibleOnly = false } = {}) {
    const regions = [...document.querySelectorAll('[data-refresh-on]')]
        .filter(region => topics.some(topic => region.dataset.refreshOn.split(/\s+/).includes(topic)))
        .filter(region => eligible(region, topics, visibleOnly));
    const frameRoots = [...document.querySelectorAll('[data-refresh-frame-on]')]
        .filter(region => topics.some(topic => region.dataset.refreshFrameOn.split(/\s+/).includes(topic)))
        .filter(region => eligible(region, topics, visibleOnly));
    const frames = new Set();
    for (const root of frameRoots) {
        const tables = root.querySelectorAll('turbo-frame[id$="-table"]');
        if (tables.length) tables.forEach(frame => frames.add(frame));
        else if (root.matches('turbo-frame')) frames.add(root);
        else root.querySelectorAll('turbo-frame[src]').forEach(frame => frames.add(frame));
    }
    const waits = [];
    for (const topic of topics) window.dispatchEvent(new CustomEvent('kmp:changed', {
        detail: { topic, source, context, waitUntil: promise => waits.push(promise) },
    }));
    if (regions.length) waits.push(refreshRegions(regions, topics));
    frames.forEach(frame => {
        if (dirtyWithin(frame)) deferRegion(frame, topics);
        else waits.push(refreshFrame(frame).then(refreshed => {
            if (!refreshed) { deferRegion(frame, topics); return; }
            delete frame.dataset.kmpStale;
            delete frame.dataset.kmpStaleTopics;
            frameRoots.filter(root => root === frame || root.contains(frame)).forEach(root => {
                delete root.dataset.kmpStaleTopics;
            });
        }));
    });
    await Promise.all(waits);
}

async function refreshRegions(regions, topics) {
    const root = document.documentElement;
    const versionAtStart = documentVersion;
    const path = window.location.pathname;
    const version = {};
    regions.forEach(region => regionVersions.set(region, version));
    const endpoint = document.querySelector('meta[name="kmp-ui-refresh-url"]')?.content;
    const streamMode = endpoint && topics.length === 1 && topics[0] === 'notes';
    const response = await fetch(streamMode ? endpoint : window.location.href, {
        ...(streamMode ? { method: 'POST', body: JSON.stringify({
            context_url: window.location.pathname + window.location.search, changes: topics,
        }) } : {}),
        headers: { Accept: streamMode ? 'text/vnd.turbo-stream.html' : 'text/html',
            'X-Requested-With': 'XMLHttpRequest', ...(streamMode ? {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content,
            } : {}) },
        credentials: 'same-origin', cache: 'no-store',
    });
    if (response.redirected || [401, 403, 404].includes(response.status)) { invalidateUiSession(); return; }
    if (!response.ok) throw new Error('Unable to refresh details.');
    const page = new DOMParser().parseFromString(await response.text(), 'text/html');
    if (documentVersion !== versionAtStart || document.documentElement !== root || window.location.pathname !== path || regions.some(region => !region.isConnected)) return;
    const streams = document.createElement('div');
    // Validate the entire response before changing any dependent region.
    for (const region of regions) {
        if (regionVersions.get(region) !== version) continue;
        if (dirtyWithin(region)) {
            deferRegion(region, topics);
            continue;
        }
        const replacement = streamMode
            ? page.querySelector(`turbo-stream[target="${CSS.escape(region.id)}"] template`)
            : page.getElementById(region.id);
        if (!region.id || !replacement) throw new Error('Updated details were missing.');
        delete region.dataset.kmpStaleTopics;
        delete region.dataset.kmpStale;
        const stream = document.createElement('turbo-stream');
        stream.setAttribute('action', 'update');
        stream.setAttribute('target', region.id);
        if (document.querySelector('meta[name="kmp-turbo-enabled"]')?.content === 'true') {
            stream.setAttribute('method', 'morph');
        }
        const template = document.createElement('template');
        template.innerHTML = replacement.innerHTML;
        stream.append(template);
        streams.append(stream);
    }
    const Turbo = await import('@hotwired/turbo');
    Turbo.renderStreamMessage(streams.innerHTML);
    await new Promise(resolve => requestAnimationFrame(resolve));
}

/** Reconcile an unstructured screen while retaining every loaded frame and its query. */
export async function refreshScreen() {
    const main = document.getElementById('main-content');
    if (!main) throw new Error('This screen has no refresh boundary.');
    const version = documentVersion;
    const url = window.location.href;
    const response = await fetch(url, {
        headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest', 'X-KMP-UI-Refresh': 'screen' },
        credentials: 'same-origin', cache: 'no-store',
    });
    if (response.redirected || [401, 403, 404].includes(response.status)) { invalidateUiSession(); return true; }
    if (!response.ok) throw new Error('Unable to refresh this screen.');
    const page = new DOMParser().parseFromString(await response.text(), 'text/html');
    const replacement = page.getElementById('main-content');
    if (!replacement) throw new Error('Updated screen was missing.');
    if (version !== documentVersion || url !== window.location.href || !main.isConnected) return true;
    // Work may have started after the request. Defer instead of painting over it.
    if (dirtyWithin(document.body) || document.querySelector('.modal.show')) return false;
    const keepFrames = event => { if (event.target.matches('turbo-frame')) event.preventDefault(); };
    document.addEventListener('turbo:before-morph-element', keepFrames);
    try {
        const streams = document.createElement('div');
        const targets = [main, document.getElementById('kmp-modal-host')].filter(Boolean);
        for (const target of targets) {
            const fresh = page.getElementById(target.id);
            if (!fresh) throw new Error('Updated screen boundary was missing.');
            const stream = document.createElement('turbo-stream');
            stream.setAttribute('action', 'update'); stream.setAttribute('method', 'morph'); stream.setAttribute('target', target.id);
            const template = document.createElement('template'); template.innerHTML = fresh.innerHTML; stream.append(template);
            streams.append(stream);
        }
        const Turbo = await import('@hotwired/turbo');
        Turbo.renderStreamMessage(streams.innerHTML);
        await new Promise(resolve => requestAnimationFrame(resolve));
    } finally { document.removeEventListener('turbo:before-morph-element', keepFrames); }
    window.dispatchEvent(new Event('kmp:screen-refreshed'));
    const frameSelector = 'turbo-frame[src], turbo-frame[data-grid-src]';
    const frames = [...main.querySelectorAll(frameSelector)].filter(frame => !frame.querySelector(frameSelector));
    await Promise.all(frames.map(frame => {
        if (!frame.hasAttribute('complete') && !frame.dataset.gridSrc) return;
        if (!frame.getClientRects().length) { frame.dataset.kmpStaleScreen = 'true'; return; }
        return refreshFrame(frame).then(refreshed => { if (!refreshed) frame.dataset.kmpStaleScreen = 'true'; });
    }));
    return true;
}

function refreshFrame(frame) {
    const versionAtStart = documentVersion;
    const src = frame.dataset.gridCurrentSrc || frame.getAttribute('src') || frame.dataset.gridSrc;
    if (!src || !frame.isConnected) return Promise.resolve(true);
    return new Promise((resolve, reject) => {
        let blocked = false;
        const guardDraft = event => {
            if (event.target !== frame) return;
            const render = event.detail.render;
            event.detail.render = async (...args) => {
                if (versionAtStart !== documentVersion || !frame.isConnected) return;
                if (dirtyWithin(frame)) { blocked = true; return; }
                const focused = frame.contains(document.activeElement) ? document.activeElement : null;
                const stream = document.createElement('turbo-stream');
                if (document.querySelector('meta[name="kmp-turbo-enabled"]')?.content === 'true'
                    && typeof stream.render === 'function') {
                    stream.setAttribute('action', 'update'); stream.setAttribute('method', 'morph');
                    stream.setAttribute('target', frame.id);
                    const template = document.createElement('template'); template.innerHTML = args[1].innerHTML;
                    stream.append(template);
                    await stream.render();
                    if (focused && (!focused.isConnected || !focused.getClientRects().length)) {
                        const fallback = [...frame.querySelectorAll('button:not([disabled]), a[href], input:not([type="hidden"])')]
                            .find(element => element.getClientRects().length)
                            || document.querySelector('[role="tab"][aria-selected="true"]');
                        fallback?.focus({ preventScroll: true });
                    }
                    return;
                }
                return render(...args);
            };
        };
        document.addEventListener('turbo:before-frame-render', guardDraft);
        const finish = event => {
            if (event && event.target !== frame) return;
            clearTimeout(timer);
            frame.removeEventListener('turbo:frame-load', loaded);
            frame.removeEventListener('turbo:frame-missing', failed);
            frame.removeEventListener('turbo:fetch-request-error', failed);
            document.removeEventListener('turbo:before-frame-render', guardDraft);
            if (!frame.isConnected || versionAtStart !== documentVersion) resolve(true);
            else if (event?.type === 'turbo:frame-load') resolve(!blocked);
            else reject(new Error('Unable to refresh the list.'));
        };
        const loaded = event => finish(event);
        const failed = event => finish(event);
        const timer = setTimeout(() => finish(), 10000);
        frame.addEventListener('turbo:frame-load', loaded);
        frame.addEventListener('turbo:frame-missing', failed);
        frame.addEventListener('turbo:fetch-request-error', failed);
        if (frame.getAttribute('src') === src) frame.reload();
        else frame.setAttribute('src', src);
    });
}

/** A committed save must never be presented as a failed write when a refresh fails. */
export function showRefreshFailure(retry) {
    const container = document.getElementById('flash-messages') || document.querySelector('main') || document.body;
    const alert = document.createElement('div');
    alert.className = 'alert alert-warning';
    alert.setAttribute('role', 'alert');
    alert.textContent = 'Saved, but some details could not be refreshed. Do not submit the save again. ';
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-sm btn-outline-dark';
    button.textContent = 'Refresh details';
    button.addEventListener('click', async () => {
        button.disabled = true;
        try {
            await retry();
            const focused = document.activeElement === button;
            alert.remove();
            if (focused) document.querySelector('[role="tab"][aria-selected="true"]')?.focus({ preventScroll: true });
        } catch {
            button.disabled = false;
            window.KMP_accessibility?.announce('Details could not be refreshed. Please try again.', { assertive: true });
        }
    });
    alert.append(button);
    container.prepend(alert);
}

/** Finish Bootstrap's transition before replacing dialog-dependent content. */
export function hideModalAndWait(modal) {
    const instance = window.bootstrap?.Modal.getInstance(modal);
    if (!instance || !modal.classList.contains('show')) return Promise.resolve();
    return new Promise(resolve => {
        const done = () => {
            clearTimeout(timer);
            modal.removeEventListener('hidden.bs.modal', done);
            resolve();
        };
        const timer = setTimeout(done, 1000);
        modal.addEventListener('hidden.bs.modal', done, { once: true });
        instance.hide();
    });
}

/** Reveal invalidated content without reloading tabs whose data is already current. */
function reconcileVisibleFrames() {
    document.querySelectorAll('turbo-frame[data-kmp-stale-screen]').forEach(frame => {
        if (!frame.getClientRects().length || dirtyWithin(frame)) return;
        delete frame.dataset.kmpStaleScreen;
        refreshFrame(frame).then(refreshed => {
            if (!refreshed) frame.dataset.kmpStaleScreen = 'true';
        }).catch(() => {
            frame.dataset.kmpStaleScreen = 'true';
            window.KMP_accessibility?.announce('Updated list could not be loaded. Please try again.');
        });
    });
}
window.addEventListener('kmp:draft-settled', () => queueMicrotask(reconcileVisibleFrames));
document.addEventListener('shown.bs.tab', () => {
    const topics = new Set();
    document.querySelectorAll('[data-kmp-stale-topics]').forEach(region => {
        if (region.getClientRects().length) region.dataset.kmpStaleTopics.split(' ').forEach(topic => topics.add(topic));
    });
    if (topics.size) refreshAfterChanges([...topics], { visibleOnly: true }).catch(() => {
        window.KMP_accessibility?.announce('Updated details could not be loaded. Please try again.');
    });
    reconcileVisibleFrames();
});
