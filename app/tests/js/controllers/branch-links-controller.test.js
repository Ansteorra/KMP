import '../../../assets/js/controllers/branch-links-controller.js';
const BranchLinksController = window.Controllers['branch-links'];

describe('BranchLinksController', () => {
    let controller;

    beforeEach(() => {
        // Mock KMP_utils.sanitizeUrl
        window.KMP_utils.sanitizeUrl = jest.fn(url => url);

        document.body.innerHTML = `
            <div data-controller="branch-links">
                <input data-branch-links-target="new" type="url" value="">
                <div data-branch-links-target="linkType" data-value="link" class="bi-link"></div>
                <button data-action="click->branch-links#add">Add</button>
                <div data-branch-links-target="displayList"></div>
                <input data-branch-links-target="formValue" type="hidden" name="links" value="">
            </div>
        `;

        controller = new BranchLinksController();
        controller.element = document.querySelector('[data-controller="branch-links"]');
        controller.newTarget = document.querySelector('[data-branch-links-target="new"]');
        controller.formValueTarget = document.querySelector('[data-branch-links-target="formValue"]');
        controller.displayListTarget = document.querySelector('[data-branch-links-target="displayList"]');
        controller.linkTypeTarget = document.querySelector('[data-branch-links-target="linkType"]');
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    // --- Static properties ---

    test('has correct static targets', () => {
        expect(BranchLinksController.targets).toEqual(expect.arrayContaining(['new', 'formValue', 'displayList', 'linkType']));
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['branch-links']).toBe(BranchLinksController);
    });

    // --- initialize ---

    test('initialize sets empty items array', () => {
        controller.initialize();
        expect(controller.items).toEqual([]);
    });

    // --- connect ---

    test('connect loads existing links from formValue', () => {
        const items = [{ url: 'https://example.com', type: 'link' }];
        controller.formValueTarget.value = JSON.stringify(items);

        controller.connect();

        expect(controller.items).toEqual(items);
        expect(controller.displayListTarget.children.length).toBe(1);
    });

    test('connect handles empty formValue', () => {
        controller.items = [];
        controller.formValueTarget.value = '';
        controller.connect();
        expect(controller.items).toEqual([]);
    });

    // --- add ---

    test('add creates new link and updates display', () => {
        controller.initialize();
        controller.newTarget.value = 'https://example.com';
        controller.newTarget.checkValidity = jest.fn().mockReturnValue(true);
        controller.linkTypeTarget.dataset.value = 'link';

        controller.add({ preventDefault: jest.fn() });

        expect(controller.items.length).toBe(1);
        expect(controller.items[0].url).toBe('https://example.com');
        expect(controller.items[0].type).toBe('link');
        expect(controller.formValueTarget.value).toBe(JSON.stringify(controller.items));
        expect(controller.displayListTarget.children.length).toBe(1);
        expect(controller.newTarget.value).toBe('');
    });

    test('add resets link type after adding', () => {
        controller.initialize();
        controller.newTarget.value = 'https://example.com';
        controller.newTarget.checkValidity = jest.fn().mockReturnValue(true);
        controller.linkTypeTarget.dataset.value = 'facebook';
        controller.linkTypeTarget.classList.add('bi-facebook');

        controller.add({ preventDefault: jest.fn() });

        expect(controller.linkTypeTarget.dataset.value).toBe('link');
        expect(controller.linkTypeTarget.classList.contains('bi-link')).toBe(true);
        expect(controller.linkTypeTarget.classList.contains('bi-facebook')).toBe(false);
    });

    test('add prevents duplicate links', () => {
        controller.initialize();
        controller.items = [{ url: 'https://example.com', type: 'link' }];
        controller.newTarget.value = 'https://example.com';
        controller.newTarget.checkValidity = jest.fn().mockReturnValue(true);
        controller.linkTypeTarget.dataset.value = 'link';

        controller.add({ preventDefault: jest.fn() });

        expect(controller.items.length).toBe(1);
    });

    test('add allows same URL with different type', () => {
        controller.initialize();
        controller.items = [{ url: 'https://example.com', type: 'link' }];
        controller.newTarget.value = 'https://example.com';
        controller.newTarget.checkValidity = jest.fn().mockReturnValue(true);
        controller.linkTypeTarget.dataset.value = 'facebook';

        controller.add({ preventDefault: jest.fn() });

        expect(controller.items.length).toBe(2);
    });

    test('add validates input and reports validity on failure', () => {
        controller.initialize();
        controller.newTarget.value = 'invalid';
        controller.newTarget.checkValidity = jest.fn().mockReturnValue(false);
        controller.newTarget.reportValidity = jest.fn();

        controller.add({ preventDefault: jest.fn() });

        expect(controller.newTarget.reportValidity).toHaveBeenCalled();
        expect(controller.items.length).toBe(0);
    });

    test('add does nothing with empty value', () => {
        controller.initialize();
        controller.newTarget.value = '';
        controller.newTarget.checkValidity = jest.fn().mockReturnValue(true);

        controller.add({ preventDefault: jest.fn() });

        expect(controller.items.length).toBe(0);
    });

    // --- remove ---

    test('remove removes item and updates display', () => {
        controller.initialize();
        controller.items = [
            { url: 'https://a.com', type: 'link' },
            { url: 'https://b.com', type: 'facebook' }
        ];

        // Create display items
        controller.items.forEach(item => controller.createListItem(item));

        const removeBtn = controller.displayListTarget.querySelector('button');
        removeBtn.getAttribute = jest.fn().mockReturnValue(JSON.stringify({ url: 'https://a.com', type: 'link' }));

        controller.remove({
            preventDefault: jest.fn(),
            target: removeBtn
        });

        expect(controller.items.length).toBe(1);
        expect(controller.items[0].url).toBe('https://b.com');
    });

    // --- setLinkType ---

    test('setLinkType updates icon and data-value', () => {
        controller.linkTypeTarget.dataset.value = 'link';
        controller.linkTypeTarget.classList.add('bi-link');

        controller.setLinkType({
            preventDefault: jest.fn(),
            target: { getAttribute: jest.fn().mockReturnValue('facebook') }
        });

        expect(controller.linkTypeTarget.dataset.value).toBe('facebook');
        expect(controller.linkTypeTarget.classList.contains('bi-facebook')).toBe(true);
        expect(controller.linkTypeTarget.classList.contains('bi-link')).toBe(false);
    });

    // --- createListItem ---

    test('createListItem creates input group with icon and remove button', () => {
        controller.createListItem({ url: 'https://test.com', type: 'twitter' });

        const group = controller.displayListTarget.querySelector('.input-group');
        expect(group).toBeTruthy();
        expect(group.querySelector('.bi-twitter')).toBeTruthy();
        expect(group.querySelector('.btn-danger')).toBeTruthy();
        expect(group.textContent).toContain('https://test.com');
    });
});
