import { installUiNavigation, updateUiHistory, goBack, usesNativeNavigation, visitDocument } from '../../../assets/js/services/ui-navigation-service.js';

describe('shared Turbo navigation ownership', () => {
    let runtime;
    beforeAll(() => {
        runtime = { session: { drive: false, history: {
            started: true, currentIndex: 0,
            push: jest.fn((url, restorationIdentifier) => history.pushState({ turbo: { restorationIdentifier, restorationIndex: 1 } }, '', url)),
            replace: jest.fn((url, restorationIdentifier) => history.replaceState({ turbo: {
                restorationIdentifier, restorationIndex: history.state?.turbo?.restorationIndex ?? 0,
            } }, '', url)),
            start: jest.fn(), stop: jest.fn(),
        } }, cache: { clear: jest.fn() }, visit: jest.fn() };
        installUiNavigation(runtime);
    });
    beforeEach(() => {
        document.head.innerHTML = '<meta name="kmp-turbo-enabled" content="true"><meta name="kmp-navigation-context" content="current"><meta name="kmp-navigation-context-url" content="/navigation/context">';
        const rules = document.createElement('meta'); rules.name = 'kmp-native-navigation';
        rules.content = JSON.stringify({ controllers: ['auth', 'passkeys', 'workflow-definitions', 'workflows', 'platform-admin'],
            actions: ['login', 'logout', 'password', 'impersonat', 'mfa', 'passkey', 'offline', 'download', 'export', 'upload'],
            paths: ['/events'], extensions: ['pdf', 'csv', 'xlsx', 'zip'] });
        document.head.append(rules);
        document.body.innerHTML = '<main><button id="place">Current task</button></main>';
        history.replaceState({ custom: 'kept', turbo: { restorationIdentifier: 'original', restorationIndex: 0 } }, '', '/members/view/member');
        document.dispatchEvent(new Event('turbo:load'));
        jest.clearAllMocks();
    });

    test('committed local writes invalidate cached documents before Back can restore older data', () => {
        runtime.cache.clear.mockClear();
        window.dispatchEvent(new Event('kmp:mutation-committed'));
        expect(runtime.cache.clear).toHaveBeenCalledTimes(1);
    });

    test('compatible programmatic visits use Drive while identity, editor and file boundaries stay native', () => {
        visitDocument('/members/view/1?tab=roles');
        expect(runtime.visit).toHaveBeenCalledWith('/members/view/1?tab=roles', {});
        for (const path of ['/members/logout', '/members/impersonate/2', '/members/setup-mfa', '/workflow-definitions/edit/1',
            '/platform-admin/tenants', '/waivers/gathering-waivers/upload', '/members/export', '/report.pdf', '/events', 'https://outside.test/']) {
            expect(usesNativeNavigation(path)).toBe(true);
        }
        document.body.innerHTML = '<form method="post" action="/members/impersonate/2"></form>';
        document.forms[0].dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        expect(document.forms[0]).toHaveAttribute('data-turbo', 'false');
    });

    test('the Drive fallback retains explicit frame-form opt-ins', () => {
        document.querySelector('meta[name="kmp-turbo-enabled"]').content = 'false';
        document.body.innerHTML = '<form method="post" action="/members/edit/1" data-turbo="true"></form>';
        document.forms[0].dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        expect(document.forms[0]).toHaveAttribute('data-turbo', 'true');
    });

    test('snapshots clear credentials and temporary feedback while retaining the flash target', () => {
        document.body.innerHTML = '<main><div id="flash-messages"><div role="alert">Previous save</div></div><input type="password" value="temporary-password"></main>';
        document.dispatchEvent(new Event('turbo:before-cache'));
        expect(document.getElementById('flash-messages')).toBeEmptyDOMElement();
        expect(document.querySelector('input')).toHaveValue('');
        delete document.body.dataset.kmpSnapshot; delete document.body.dataset.kmpContext;
    });
    afterEach(() => {
        document.head.innerHTML = '';
        document.documentElement.removeAttribute('data-kmp-cache-pending');
    });

    test('tab history preserves Turbo and caller state while giving each entry its own restoration identity', () => {
        updateUiHistory('/members/view/member?tab=awards');
        expect(runtime.session.history.push).toHaveBeenCalledTimes(1);
        expect(history.state.custom).toBe('kept');
        expect(history.state.turbo.restorationIdentifier).not.toBe('original');
        expect(history.state.kmp.documentId).toBeTruthy();
        const identifier = history.state.turbo.restorationIdentifier;
        updateUiHistory('/members/view/member?tab=notes', { replace: true });
        expect(history.state.turbo.restorationIdentifier).toBe(identifier);
    });

    test('same-document Back restores UI without letting Turbo start a competing visit', () => {
        updateUiHistory('/members/view/member?tab=notes');
        const state = history.state;
        const nativeRestore = jest.fn();
        const localRestore = jest.fn();
        window.addEventListener('popstate', nativeRestore);
        window.addEventListener('kmp:ui-history-restored', localRestore);
        window.dispatchEvent(new PopStateEvent('popstate', { state }));
        expect(localRestore).toHaveBeenCalledTimes(1);
        expect(nativeRestore).not.toHaveBeenCalled();
        window.dispatchEvent(new PopStateEvent('popstate', { state: { turbo: { restorationIdentifier: 'another-document' } } }));
        expect(nativeRestore).toHaveBeenCalledTimes(1);
        window.removeEventListener('popstate', nativeRestore);
        window.removeEventListener('kmp:ui-history-restored', localRestore);
    });

    test('cached snapshots wait for fresh session validation before rendering', async () => {
        const body = document.body.cloneNode(true);
        body.dataset.kmpSnapshot = 'true'; body.dataset.kmpContext = 'current';
        const render = jest.fn();
        global.fetch = jest.fn().mockResolvedValue({ ok: true, json: async () => ({ context: 'current' }) });
        const event = new CustomEvent('turbo:before-render', { cancelable: true, detail: { newBody: body, render } });
        document.dispatchEvent(event);
        expect(event.defaultPrevented).toBe(false);
        const rendering = event.detail.render(document.body, body);
        expect(render).not.toHaveBeenCalled();
        await rendering;
        expect(render).toHaveBeenCalledTimes(1);
        expect(fetch).toHaveBeenCalledWith('/navigation/context', expect.objectContaining({ cache: 'no-store' }));
    });

    test('a superseded cached renderer settles without painting or blocking the following restore', async () => {
        let validate;
        global.fetch = jest.fn().mockImplementation(() => new Promise(resolve => { validate = resolve; }));
        const body = document.body.cloneNode(true);
        body.dataset.kmpSnapshot = 'true'; body.dataset.kmpContext = 'current';
        const render = jest.fn();
        const old = new CustomEvent('turbo:before-render', { detail: { newBody: body, render } });
        document.dispatchEvent(old);
        const oldRender = old.detail.render(document.body, body);
        document.dispatchEvent(new CustomEvent('turbo:visit', { detail: { url: location.href, action: 'restore' } }));
        validate({ ok: true, json: async () => ({ context: 'current' }) });
        await oldRender;
        expect(render).not.toHaveBeenCalled();
        const freshBody = document.createElement('body');
        const fresh = new CustomEvent('turbo:before-render', { detail: { newBody: freshBody, render } });
        document.dispatchEvent(fresh);
        await fresh.detail.render(document.body, freshBody);
        expect(render).toHaveBeenCalledTimes(1);
        expect(document.documentElement).not.toHaveAttribute('data-kmp-cache-pending');
    });

    test('fallback keeps existing history behavior and rejects foreign destinations', () => {
        document.querySelector('meta[name="kmp-turbo-enabled"]').content = 'false';
        updateUiHistory('/members/view/member?tab=notes');
        expect(runtime.session.history.push).not.toHaveBeenCalled();
        expect(() => updateUiHistory('https://attacker.example/path')).toThrow('origin');
    });

    test('app Back uses real browser history and skips the current record tab/filter entries', () => {
        const go = jest.spyOn(history, 'go').mockImplementation(() => {});
        history.replaceState({ turbo: { restorationIndex: 8 }, kmp: { previousDocumentIndex: 3, uiDepth: 2 } }, '', '/members/view/member?tab=notes');
        expect(goBack()).toBe(true);
        expect(go).toHaveBeenCalledWith(-5);
        go.mockRestore();
    });

    test('a directly opened page keeps its real fallback link instead of sending users outside the app', () => {
        const referrer = jest.spyOn(document, 'referrer', 'get').mockReturnValue('https://external.example/');
        const go = jest.spyOn(history, 'go').mockImplementation(() => {});
        history.replaceState({}, '', '/members/view/member');
        expect(goBack()).toBe(false);
        expect(go).not.toHaveBeenCalled();
        referrer.mockRestore(); go.mockRestore();
    });

    test('new page controller initialization cannot overwrite the document return index', () => {
        history.replaceState({ turbo: { restorationIndex: 3 }, kmp: { documentId: 'list', uiDepth: 2 } }, '', '/members');
        document.dispatchEvent(new Event('turbo:load'));
        document.dispatchEvent(new CustomEvent('turbo:visit', { detail: {
            url: 'http://localhost/members/view/1', action: 'advance',
        } }));
        // Turbo moves to the record; its newly connected grid syncs local state first.
        history.replaceState({ turbo: { restorationIndex: 4 } }, '', '/members/view/1');
        updateUiHistory('/members/view/1?tab=roles', { replace: true });
        document.dispatchEvent(new Event('turbo:load'));
        expect(history.state.kmp.documentId).not.toBe('list');
        expect(history.state.kmp.previousDocumentIndex).toBe(3);
        const go = jest.spyOn(history, 'go').mockImplementation(() => {});
        expect(goBack()).toBe(true);
        expect(go).toHaveBeenCalledWith(-1);
        go.mockRestore();
    });

    test('Back during an unfinished document visit reaches Turbo so its pending response is canceled', () => {
        const source = history.state;
        document.dispatchEvent(new CustomEvent('turbo:visit', { detail: {
            url: 'http://localhost/awards/recommendations/view/1', action: 'advance',
        } }));
        const restore = jest.fn();
        window.addEventListener('popstate', restore);
        window.dispatchEvent(new PopStateEvent('popstate', { state: source }));
        expect(restore).toHaveBeenCalledTimes(1);
        window.removeEventListener('popstate', restore);
    });
});
