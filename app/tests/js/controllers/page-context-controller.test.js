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
    });

});
