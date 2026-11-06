jest.mock('@hotwired/turbo', () => ({ renderStreamMessage: jest.fn() }));
jest.mock('../../../assets/js/services/ui-navigation-service.js', () => ({ invalidateUiSession: jest.fn() }));
import { renderStreamMessage } from '@hotwired/turbo';
import { refreshAfterChange, refreshAfterChanges, refreshScreen, showRefreshFailure } from '../../../assets/js/services/page-refresh-service.js';
import { installFormState, rememberForms } from '../../../assets/js/services/form-state-service.js';

describe('dependent page updates', () => {
    beforeAll(() => installFormState());
    beforeEach(() => {
        renderStreamMessage.mockClear();
        window.history.replaceState({}, '', '/gatherings/view/record?tab=schedule&page=3#place');
        document.body.innerHTML = `<div id="count" data-refresh-on="schedule">1</div>
            <section id="overview" data-refresh-on="schedule attendance">Old schedule</section>
            <input id="draft" value="Keep my note">
            <button id="nav-schedule-tab" aria-selected="true">Schedule</button>
            <div id="flash-messages"></div>`;
        global.fetch = jest.fn();
        jest.spyOn(window, 'requestAnimationFrame').mockImplementation(callback => { callback(); return 1; });
        renderStreamMessage.mockImplementation(html => {
            const template = document.createElement('template'); template.innerHTML = html;
            template.content.querySelectorAll('turbo-stream').forEach(stream => {
                document.getElementById(stream.getAttribute('target')).innerHTML = stream.querySelector('template').innerHTML;
            });
        });
    });
    afterEach(() => { jest.restoreAllMocks(); document.body.innerHTML = ''; });

    test('updates declared dependencies together without replacing tabs, drafts or history', async () => {
        fetch.mockResolvedValue({ ok: true, redirected: false,
            text: async () => '<div id="count">2</div><section id="overview">New schedule</section><input id="draft" value="Discarded">' });
        const tab = document.getElementById('nav-schedule-tab');
        await refreshAfterChange('schedule');
        expect(document.getElementById('count')).toHaveTextContent('2');
        expect(document.getElementById('overview')).toHaveTextContent('New schedule');
        expect(document.getElementById('draft')).toHaveValue('Keep my note');
        expect(document.getElementById('nav-schedule-tab')).toBe(tab);
        expect(window.location.search).toBe('?tab=schedule&page=3');
        expect(window.location.hash).toBe('#place');
        expect(fetch).toHaveBeenCalledWith(window.location.href, expect.objectContaining({
            headers: expect.objectContaining({ 'X-Requested-With': 'XMLHttpRequest' }), credentials: 'same-origin',
        }));
    });

    test('rejects incomplete responses before applying any dependent update', async () => {
        fetch.mockResolvedValue({ ok: true, text: async () => '<div id="count">2</div>' });
        await expect(refreshAfterChange('schedule')).rejects.toThrow('missing');
        expect(document.getElementById('count')).toHaveTextContent('1');
    });

    test('a screen read cannot overwrite a draft begun while its response was in flight', async () => {
        document.body.innerHTML = '<main id="main-content"><form method="post"><input name="subject" value="Original"></form></main>';
        rememberForms();
        let finish;
        fetch.mockImplementationOnce(() => new Promise(resolve => { finish = resolve; }));
        const refresh = refreshScreen();
        const input = document.querySelector('input');
        input.value = 'Draft started after notification'; input.dispatchEvent(new Event('input', { bubbles: true }));
        finish({ ok: true, text: async () => '<main id="main-content">Fresh content</main>' });
        expect(await refresh).toBe(false);
        expect(input).toHaveValue('Draft started after notification');
        expect(renderStreamMessage).not.toHaveBeenCalled();
    });

    test('an unstructured refresh validates its boundary before updating the screen', async () => {
        document.body.innerHTML = '<main id="main-content">Current details</main>';
        fetch.mockResolvedValue({ ok: true, text: async () => '<main>Incorrect boundary</main>' });
        await expect(refreshScreen()).rejects.toThrow('missing');
        expect(document.getElementById('main-content')).toHaveTextContent('Current details');
        expect(renderStreamMessage).not.toHaveBeenCalled();
    });

    test('reloads a dependent grid at its live query without replacing its shell', async () => {
        document.body.innerHTML = '<section data-refresh-frame-on="schedule"><turbo-frame id="awards-table" src="/awards/grid?page=3&limit=10&search=needle"></turbo-frame></section>';
        const frame = document.querySelector('turbo-frame');
        frame.dataset.gridCurrentSrc = frame.getAttribute('src');
        frame.reload = jest.fn(() => queueMicrotask(() => frame.dispatchEvent(new Event('turbo:frame-load'))));
        await refreshAfterChange('schedule');
        expect(frame.reload).toHaveBeenCalledTimes(1);
        expect(frame.getAttribute('src')).toContain('page=3&limit=10&search=needle');
        expect(fetch).not.toHaveBeenCalled();
        expect(document.querySelector('turbo-frame')).toBe(frame);
    });

    test('a draft begun during a background frame request vetoes painting without leaving the renderer paused', async () => {
        document.body.innerHTML = '<section data-refresh-frame-on="schedule"><turbo-frame id="awards-table" data-grid-src="/awards/grid?page=3"><form method="post"><input name="note" value="Original"></form></turbo-frame></section>';
        const frame = document.querySelector('turbo-frame');
        rememberForms();
        const paint = jest.fn();
        const start = jest.spyOn(frame, 'setAttribute').mockImplementation((name, value) => {
            Element.prototype.setAttribute.call(frame, name, value);
            if (name !== 'src') return;
            queueMicrotask(() => {
                const input = frame.querySelector('input');
                input.value = 'Draft begun during request'; input.dispatchEvent(new Event('input', { bubbles: true }));
                const event = new CustomEvent('turbo:before-frame-render', { bubbles: true, cancelable: true, detail: { render: paint } });
                frame.dispatchEvent(event);
                event.detail.render();
                expect(event.defaultPrevented).toBe(false);
                frame.dispatchEvent(new Event('turbo:frame-load'));
            });
        });
        await refreshAfterChange('schedule');
        expect(paint).not.toHaveBeenCalled();
        expect(frame.querySelector('input')).toHaveValue('Draft begun during request');
        expect(frame).toHaveAttribute('data-kmp-stale', 'true');
        start.mockRestore();
    });

    test('screen morphing preserves loaded frames, refreshes only the visible leaf query and invalidates hidden lists', async () => {
        document.body.innerHTML = '<main id="main-content"><turbo-frame id="outer" src="/initial"><turbo-frame id="visible-table" data-grid-src="/grid?page=3&search=retained">Current rows</turbo-frame></turbo-frame><turbo-frame id="hidden-table" data-grid-src="/other">Hidden rows</turbo-frame></main>';
        const outer = document.getElementById('outer');
        const visible = document.getElementById('visible-table');
        const hidden = document.getElementById('hidden-table');
        jest.spyOn(visible, 'getClientRects').mockReturnValue([{}]);
        fetch.mockResolvedValue({ ok: true, text: async () => '<main id="main-content"><turbo-frame id="outer" src="/initial">Unloaded placeholder</turbo-frame></main>' });
        visible.reload = jest.fn();
        jest.spyOn(visible, 'setAttribute').mockImplementation((name, value) => {
            Element.prototype.setAttribute.call(visible, name, value);
            if (name === 'src') queueMicrotask(() => visible.dispatchEvent(new Event('turbo:frame-load')));
        });
        renderStreamMessage.mockImplementationOnce(html => {
            expect(html).toContain('method="morph"');
            const morph = new CustomEvent('turbo:before-morph-element', { bubbles: true, cancelable: true });
            outer.dispatchEvent(morph);
            expect(morph.defaultPrevented).toBe(true);
        });
        expect(await refreshScreen()).toBe(true);
        expect(visible).toHaveAttribute('src', '/grid?page=3&search=retained');
        expect(document.getElementById('visible-table')).toBe(visible);
        expect(outer).toHaveAttribute('src', '/initial');
        expect(hidden).toHaveAttribute('data-kmp-stale-screen', 'true');
        expect(hidden).not.toHaveAttribute('src');
    });

    test('waits for a page-owned list refresh and offers a read-only retry after failure', async () => {
        document.body.innerHTML = '<div id="flash-messages"></div>';
        let finish;
        const done = jest.fn(event => event.detail.waitUntil(new Promise(resolve => { finish = resolve; })));
        window.addEventListener('kmp:changed', done);
        let settled = false;
        const updated = refreshAfterChange('attendance').then(() => { settled = true; });
        await Promise.resolve();
        expect(settled).toBe(false);
        finish(); await updated;
        window.removeEventListener('kmp:changed', done);
        const retry = jest.fn().mockResolvedValue();
        showRefreshFailure(retry);
        expect(document.getElementById('flash-messages')).toHaveTextContent('Do not submit the save again');
        document.querySelector('button').click();
        await Promise.resolve();
        expect(retry).toHaveBeenCalledTimes(1);
        expect(document.querySelector('[role="alert"]')).toBeNull();
    });
    test('a newer attendance refresh cannot be overwritten by an older schedule response', async () => {
        let finishOld;
        fetch.mockImplementationOnce(() => new Promise(resolve => { finishOld = resolve; }));
        const older = refreshAfterChange('schedule');
        fetch.mockResolvedValueOnce({ ok: true, text: async () => '<section id="overview">Newest attendance</section>' });
        await refreshAfterChange('attendance');
        finishOld({ ok: true, text: async () => '<div id="count">2</div><section id="overview">Old attendance</section>' });
        await older;
        expect(document.getElementById('overview')).toHaveTextContent('Newest attendance');
        expect(document.getElementById('count')).toHaveTextContent('2');
    });

    test('a batch refreshes each dependency once and leaves dirty regions intact with a retry control', async () => {
        document.getElementById('overview').innerHTML = '<form method="post"><input name="note" value="Original"></form>';
        rememberForms();
        const input = document.querySelector('[name="note"]');
        input.value = 'Unsaved'; input.dispatchEvent(new Event('input', { bubbles: true }));
        fetch.mockResolvedValue({ ok: true, text: async () => '<div id="count">2</div><section id="overview">Fresh summary</section>' });
        await refreshAfterChanges(['schedule', 'attendance']);
        expect(fetch).toHaveBeenCalledTimes(1);
        expect(input).toHaveValue('Unsaved');
        expect(document.getElementById('count')).toHaveTextContent('2');
        expect(document.getElementById('overview')).toHaveAttribute('data-kmp-stale', 'true');
        expect(document.getElementById('kmp-deferred-updates')).toHaveTextContent('Your unsaved changes have been kept');
        expect(document.querySelector('#kmp-deferred-updates button')).toHaveAccessibleName('Refresh details');
    });

});
