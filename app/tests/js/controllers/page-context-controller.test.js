import '../../../assets/js/controllers/page-context-controller.js';

const PageContextController = window.Controllers['page-context'];

describe('PageContextController', () => {
    let controller;

    beforeEach(() => {
        const root = document.createElement('div');
        root.setAttribute('data-controller', 'page-context');
        const input = document.createElement('input');
        input.setAttribute('name', 'page_context_url');
        input.value = '/old';
        root.appendChild(input);
        document.body.appendChild(root);

        window.history.replaceState({}, '', '/awards/recommendations?search=token');

        controller = new PageContextController();
        controller.element = root;
        controller.connect();
    });

    afterEach(() => {
        controller.disconnect();
        document.body.replaceChildren();
    });

    test('connect syncs hidden fields from the current URL', () => {
        const input = document.querySelector('input[name="page_context_url"]');
        expect(input.value).toBe('/awards/recommendations?search=token');
    });

    test('grid-view:navigated updates hidden fields', () => {
        window.history.replaceState({}, '', '/awards/bestowals?filter=open');
        window.dispatchEvent(new CustomEvent('grid-view:navigated'));

        const input = document.querySelector('input[name="page_context_url"]');
        expect(input.value).toBe('/awards/bestowals?filter=open');
    });
    test('native submit refreshes validated context while preserving signed static returns', () => {
        document.body.insertAdjacentHTML('beforeend', `
            <form id="native-bulk-form">
                <input name="page_context_url" value="/bulk-old">
                <input name="current_page" value="/signed-return">
                <input name="page_context_url" value="/static-return" data-page-context-static="true">
            </form>
        `);
        const query = '?page=3&limit=50&filter%5Bstatus%5D%5B%5D=open';
        window.history.replaceState({}, '', '/awards/bestowals' + query);
        document.getElementById('native-bulk-form').dispatchEvent(new Event('submit', { bubbles: true }));

        expect(document.querySelector('#native-bulk-form input[name="page_context_url"]').value)
            .toBe('/awards/bestowals' + query);
        expect(document.querySelector('input[value="/signed-return"]').value).toBe('/signed-return');
        expect(document.querySelector('[data-page-context-static]').value).toBe('/static-return');
    });

    test('frame loads synchronize fields inserted with a lazy modal', () => {
        document.body.insertAdjacentHTML('beforeend', '<input name="page_context_url" value="/lazy-modal">');
        document.dispatchEvent(new Event('turbo:frame-load'));
        expect(document.querySelector('input[value="/lazy-modal"]').value)
            .toBe('/awards/recommendations?search=token');
    });

    test('disconnect removes native submission and frame listeners', () => {
        controller.disconnect();
        const input = document.querySelector('input[name="page_context_url"]');
        input.value = '/kept';
        document.dispatchEvent(new Event('turbo:frame-load'));
        document.dispatchEvent(new Event('submit', { bubbles: true }));
        expect(input.value).toBe('/kept');
    });

    test('same-origin Turbo requests use the live grid URL as their referrer', () => {
        const currentUrl = '/awards/recommendations?page=3&limit=50&filter%5Bstatus%5D%5B%5D=open';
        window.history.pushState({}, '', currentUrl);
        const options = { referrer: 'http://localhost/awards/recommendations' };
        document.dispatchEvent(new CustomEvent('turbo:before-fetch-request', {
            detail: { url: new URL('/awards/recommendations/view/1', window.location.origin), fetchOptions: options },
        }));

        expect(options.referrer).toBe(window.location.origin + currentUrl);
    });

    test('external Turbo requests keep their original referrer', () => {
        const options = { referrer: 'http://localhost/original' };
        document.dispatchEvent(new CustomEvent('turbo:before-fetch-request', {
            detail: { url: new URL('https://example.test/away'), fetchOptions: options },
        }));

        expect(options.referrer).toBe('http://localhost/original');
    });

    test('disconnect stops overriding Turbo request referrers', () => {
        controller.disconnect();
        const options = { referrer: 'http://localhost/original' };
        document.dispatchEvent(new CustomEvent('turbo:before-fetch-request', {
            detail: { url: new URL('/awards/recommendations/view/1', window.location.origin), fetchOptions: options },
        }));

        expect(options.referrer).toBe('http://localhost/original');
    });

    test('native modal submissions keep the originating embedded grid query and detail tab', () => {
        window.history.replaceState({}, '', '/gatherings/view/public-id?tab=gathering-bestowals');
        document.body.insertAdjacentHTML('beforeend', `
            <section role="tabpanel">
                <button id="add-bestowal">Add Bestowal</button>
                <div data-controller="grid-view">
                    <turbo-frame id="gathering-bestowals-grid-1-table"
                        data-grid-current-src="/awards/bestowals/gathering-bestowals-grid-data/1?page=3&amp;limit=50&amp;filter%5Bstatus%5D%5B%5D=open"></turbo-frame>
                </div>
            </section>
            <div class="modal" id="native-add-modal">
                <form id="embedded-native-form"><input name="page_context_url" value="/old"></form>
            </div>
        `);
        const shown = new Event('show.bs.modal', { bubbles: true });
        shown.relatedTarget = document.getElementById('add-bestowal');
        document.getElementById('native-add-modal').dispatchEvent(shown);
        document.getElementById('embedded-native-form').dispatchEvent(new Event('submit', { bubbles: true }));
        const context = new URL(document.querySelector('#embedded-native-form input').value, window.location.origin);

        expect(context.pathname).toBe('/gatherings/view/public-id');
        expect(context.searchParams.get('tab')).toBe('gathering-bestowals');
        expect(context.searchParams.get('page')).toBe('3');
        expect(context.searchParams.get('limit')).toBe('50');
        expect(context.searchParams.getAll('filter[status][]')).toEqual(['open']);
        expect(context.searchParams.get('grid_context')).toBe('gathering-bestowals-grid-1');
        expect(window.location.search).toBe(context.search);
    });

    function embeddedGrid() {
        window.history.replaceState({ retained: true }, '', '/members/view/member-id?tab=authorizations#member-details');
        document.body.insertAdjacentHTML('beforeend', `
            <section role="tabpanel" id="embedded-tab">
                <div id="embedded-grid" data-controller="grid-view" data-grid-view-sync-url-value="false">
                    <turbo-frame id="member-auth-grid-table"
                        src="/activities/authorizations/member-authorizations-grid-data?page=1"
                        data-grid-current-src="/activities/authorizations/member-authorizations-grid-data?member_id=7&amp;branch_id=9&amp;gathering_id=11&amp;frame_id=member-auth-grid&amp;page=3&amp;limit=50&amp;search=armored&amp;sort=activity&amp;direction=desc&amp;system_view=current&amp;filter%5Bstatus%5D%5B%5D=open&amp;filter%5Bstatus%5D%5B%5D=pending">
                        <a id="record-link" href="/activities/authorizations/view/authorization-id" data-turbo-frame="_top">Record</a>
                        <form id="embedded-form" action="/activities/authorizations/retract/authorization-id" data-turbo-frame="_top"><button type="submit">Retract</button></form>
                    </turbo-frame>
                </div>
            </section>
        `);
        // Prevent jsdom navigation after the controller has prepared the origin.
        document.getElementById('record-link').addEventListener('click', event => event.preventDefault());
        return document.getElementById('record-link');
    }

    function expectEmbeddedOrigin() {
        const origin = new URL(window.location.href);
        expect(origin.pathname).toBe('/members/view/member-id');
        expect(origin.hash).toBe('#member-details');
        expect(origin.searchParams.get('tab')).toBe('authorizations');
        expect(origin.searchParams.get('page')).toBe('3');
        expect(origin.searchParams.get('limit')).toBe('50');
        expect(origin.searchParams.get('search')).toBe('armored');
        expect(origin.searchParams.get('sort')).toBe('activity');
        expect(origin.searchParams.get('direction')).toBe('desc');
        expect(origin.searchParams.get('system_view')).toBe('current');
        expect(origin.searchParams.getAll('filter[status][]')).toEqual(['open', 'pending']);
        expect(origin.searchParams.get('grid_context')).toBe('member-auth-grid');
        ['frame_id', 'member_id', 'branch_id', 'gathering_id'].forEach(key => expect(origin.searchParams.has(key)).toBe(false));
        expect(window.history.state).toEqual({ retained: true });
    }

    test('a same-window embedded record link promotes the rendered query before its Turbo request', () => {
        const link = embeddedGrid();
        link.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, button: 0, detail: 0 }));
        expectEmbeddedOrigin();
        const options = {};
        document.dispatchEvent(new CustomEvent('turbo:before-fetch-request', {
            detail: { url: new URL(link.href), fetchOptions: options },
        }));
        expect(options.referrer).toBe(window.location.href);
    });

    test.each([
        ['new tab', link => { link.target = '_blank'; }, {}],
        ['modifier click', () => {}, { ctrlKey: true }],
        ['external origin', link => { link.href = 'https://example.test/view/record'; }, {}],
        ['download', link => { link.setAttribute('download', ''); }, {}],
        ['pagination', link => { link.parentElement.classList.add('paginator'); }, {}],
        ['modal link', link => { link.dataset.bsToggle = 'modal'; }, {}],
        ['same frame', link => { link.dataset.turboFrame = 'member-auth-grid-table'; }, {}],
        ['primary grid', () => {
            document.getElementById('embedded-grid').dataset.gridViewSyncUrlValue = 'true';
            document.getElementById('embedded-tab').removeAttribute('role');
        }, {}],
    ])('%s keeps the browser origin unchanged', (_name, change, clickOptions) => {
        const link = embeddedGrid();
        const original = window.location.href;
        change(link);
        link.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, button: 0, ...clickOptions }));
        expect(window.location.href).toBe(original);
    });

    test.each(['submit', 'page-context:before-submit'])('%s prepares native embedded forms without requiring a hidden context field', type => {
        embeddedGrid();
        const form = document.getElementById('embedded-form');
        if (type === 'submit') {
            form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        } else {
            document.dispatchEvent(new CustomEvent(type, { detail: { form } }));
        }
        expectEmbeddedOrigin();
    });

    test.each(['external', 'new-tab', 'turbo-modal'])('%s form submissions retain the current host URL', mode => {
        embeddedGrid();
        const form = document.getElementById('embedded-form');
        const original = window.location.href;
        if (mode === 'external') form.action = 'https://example.test/submit';
        if (mode === 'new-tab') form.target = '_blank';
        if (mode === 'turbo-modal') form.dataset.controller = 'turbo-modal';
        form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        expect(window.location.href).toBe(original);
    });

    test('disconnect removes embedded departure listeners', () => {
        const link = embeddedGrid();
        const original = window.location.href;
        controller.disconnect();
        link.click();
        document.dispatchEvent(new CustomEvent('page-context:before-submit', {
            detail: { form: document.getElementById('embedded-form') },
        }));
        expect(window.location.href).toBe(original);
    });

    test('automatic Turbo modal requests use the unique originating frame grid without changing browser history', () => {
        window.history.replaceState({}, '', '/gathering-activities/view/activity-id?tab=awards');
        document.body.insertAdjacentHTML('beforeend', `
            <turbo-frame id="activity-awards-activity-id">
                <button id="add-activity-award">Add Award</button>
                <div data-controller="grid-view" data-grid-view-sync-url-value="false">
                    <turbo-frame id="activity-awards-grid-table" data-grid-current-src="/awards/awards/activity-awards-grid-data/activity-id?page=3&amp;limit=50&amp;search=service&amp;frame_id=activity-awards-grid"></turbo-frame>
                </div>
            </turbo-frame>
            <form id="activity-award-form" data-controller="turbo-modal" data-turbo="true">
                <div class="modal" id="activity-award-modal"></div>
            </form>
        `);
        const original = window.location.href;
        const shown = new Event('show.bs.modal', { bubbles: true });
        shown.relatedTarget = document.getElementById('add-activity-award');
        document.getElementById('activity-award-modal').dispatchEvent(shown);
        const form = document.getElementById('activity-award-form');
        form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        const options = {};
        form.dispatchEvent(new CustomEvent('turbo:before-fetch-request', {
            bubbles: true,
            detail: { url: new URL('/awards/awards/add-activity-to-gathering-activity/activity-id', window.location.origin), fetchOptions: options },
        }));
        const referrer = new URL(options.referrer);
        expect(referrer.pathname).toBe('/gathering-activities/view/activity-id');
        expect(referrer.searchParams.get('tab')).toBe('awards');
        expect(referrer.searchParams.get('page')).toBe('3');
        expect(referrer.searchParams.get('limit')).toBe('50');
        expect(referrer.searchParams.get('search')).toBe('service');
        expect(referrer.searchParams.get('grid_context')).toBe('activity-awards-grid');
        expect(referrer.searchParams.has('frame_id')).toBe(false);
        expect(window.location.href).toBe(original);
    });
});
