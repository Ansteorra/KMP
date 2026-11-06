import '../../../assets/js/controllers/detail-tabs-controller.js';
const DetailTabsController = window.Controllers['detail-tabs'];

describe('detail tab navigation', () => {
    let controller, originalBootstrap, show;
    const button = id => document.getElementById(`nav-${id}-tab`);
    const activate = id => button(id).dispatchEvent(new Event('shown.bs.tab'));

    beforeEach(() => {
        window.history.replaceState({ context: 'retained' }, '', '/members/view/1');
        document.body.innerHTML = `
            <div data-controller="detail-tabs">
                <nav role="tablist">
                    <button data-detail-tabs-target="tabBtn" id="nav-info-tab" data-tab-order="10">Info</button>
                    <button data-detail-tabs-target="tabBtn" id="nav-history-tab" data-tab-order="20">History</button>
                    <button data-detail-tabs-target="tabBtn" id="nav-awards-tab" data-tab-order="5">Awards</button>
                </nav>
                <turbo-frame id="history-frame" src="/history" loading="lazy"></turbo-frame>
            </div>`;
        controller = new DetailTabsController();
        controller.element = document.querySelector('[data-controller="detail-tabs"]');
        controller.tabBtnTargets = [...document.querySelectorAll('[data-detail-tabs-target="tabBtn"]')];
        controller.updateUrlValue = true;
        originalBootstrap = window.bootstrap;
        show = jest.fn(tab => {
            controller.tabBtnTargets.forEach(candidate => {
                const active = candidate === tab;
                candidate.classList.toggle('active', active);
                candidate.setAttribute('aria-selected', String(active));
                candidate.tabIndex = active ? 0 : -1;
            });
            tab.dispatchEvent(new Event('shown.bs.tab'));
        });
        window.bootstrap = { Tab: { getOrCreateInstance: tab => ({ show: () => show(tab) }) } };
        controller.tabBtnTargets.forEach(tab => controller.tabBtnTargetConnected(tab));
        controller.connect();
    });

    afterEach(() => {
        controller.disconnect();
        window.bootstrap = originalBootstrap;
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    test('defaults to the first tab in core/plugin order', () => {
        expect(button('awards')).toHaveAttribute('aria-selected', 'true');
        expect(controller.getFirstTabByOrder()).toBe(button('awards'));
        controller.tabBtnTargets = [];
        expect(controller.getFirstTabByOrder()).toBeNull();
    });

    test('activation preserves grid queries, hashes, and history state', () => {
        window.history.replaceState({ context: 'retained' }, '', '/members/view/1?search=needle&page=3&grid_context=roles#details');
        activate('history');
        expect(window.location.search).toBe('?search=needle&page=3&grid_context=roles&tab=history');
        expect(window.location.hash).toBe('#details');
        expect(window.history.state).toEqual({ context: 'retained' });
        activate('awards');
        expect(window.location.search).toBe('?search=needle&page=3&grid_context=roles');
    });

    test('repeat activation creates no extra history entries or frame reloads', () => {
        const push = jest.spyOn(window.history, 'pushState');
        const frame = document.getElementById('history-frame');
        frame.loaded = Promise.resolve();
        frame.reload = jest.fn();
        activate('history');
        activate('history');
        expect(push).toHaveBeenCalledTimes(1);
        expect(frame.reload).not.toHaveBeenCalled();
    });

    test('back/forward restores selection without pushing history or stealing focus', () => {
        activate('history');
        button('info').focus();
        const push = jest.spyOn(window.history, 'pushState');
        window.history.replaceState({}, '', '/members/view/1?tab=info');
        window.dispatchEvent(new PopStateEvent('popstate'));
        expect(button('info')).toHaveAttribute('aria-selected', 'true');
        expect(document.activeElement).toBe(button('info'));
        expect(push).not.toHaveBeenCalled();
    });

    test('unavailable deep-linked tabs fall back to the first visible tab', () => {
        window.history.replaceState({}, '', '/members/view/1?tab=removed-plugin');
        controller.restoreTab();
        expect(button('awards')).toHaveAttribute('aria-selected', 'true');
    });

    test('keyboard Bootstrap activation synchronizes form page context', () => {
        const sync = jest.fn();
        window.addEventListener('page-context:sync', sync);
        activate('history');
        expect(new URL(window.location.href).searchParams.get('tab')).toBe('history');
        expect(sync).toHaveBeenCalledTimes(1);
        window.removeEventListener('page-context:sync', sync);
    });

    test('nested tabs retain selection without overwriting the main detail tab', () => {
        window.history.replaceState({}, '', '/members/view/1?tab=gatherings');
        const host = document.createElement('div');
        host.dataset.controller = 'detail-tabs';
        controller.element.before(host);
        host.append(controller.element);
        button('history').classList.add('active');
        const push = jest.spyOn(window.history, 'pushState');
        controller.restoreTab();
        activate('info');
        expect(show).toHaveBeenLastCalledWith(button('history'));
        expect(window.location.search).toBe('?tab=gatherings');
        expect(push).toHaveBeenCalledTimes(1);
        expect(window.history.state.kmp.nestedTabs['nav-awards-tab']).toBe('info');
        controller.restoreTab();
        expect(show).toHaveBeenLastCalledWith(button('info'));
    });

    test('local-only groups do not update the host URL', () => {
        controller.updateUrlValue = false;
        activate('history');
        expect(window.location.search).toBe('');
    });

    test('disconnect removes activation and browser history listeners', () => {
        controller.disconnect();
        const push = jest.spyOn(window.history, 'pushState');
        show.mockClear();
        activate('history');
        window.dispatchEvent(new PopStateEvent('popstate'));
        expect(push).not.toHaveBeenCalled();
        expect(show).not.toHaveBeenCalled();
    });
});
