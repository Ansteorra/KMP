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
});
