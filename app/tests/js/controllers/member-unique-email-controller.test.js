import '../../../assets/js/controllers/member-unique-email-controller.js';
const MemberUniqueEmail = window.Controllers['member-unique-email'];

describe('MemberUniqueEmailController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <input type="email"
                   data-controller="member-unique-email"
                   data-member-unique-email-url-value="/api/check-email"
                   data-original-value="existing@example.com"
                   name="email">
        `;

        controller = new MemberUniqueEmail();
        controller.element = document.querySelector('[data-controller="member-unique-email"]');
        controller.urlValue = '/api/check-email';
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
        if (global.fetch) {
            delete global.fetch;
        }
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['member-unique-email']).toBe(MemberUniqueEmail);
    });

    test('has correct static values', () => {
        expect(MemberUniqueEmail.values).toHaveProperty('url', String);
    });

    test('connect removes oninput and oninvalid attributes', () => {
        controller.element.setAttribute('oninput', 'someFunc()');
        controller.element.setAttribute('oninvalid', 'someFunc()');
        controller.connect();
        expect(controller.element.hasAttribute('oninput')).toBe(false);
        expect(controller.element.hasAttribute('oninvalid')).toBe(false);
    });

    test('connect adds change event listener', () => {
        const addSpy = jest.spyOn(controller.element, 'addEventListener');
        controller.connect();
        expect(addSpy).toHaveBeenCalledWith('change', expect.any(Function));
    });

    test('disconnect removes change event listener', () => {
        const removeSpy = jest.spyOn(controller.element, 'removeEventListener');
        controller.disconnect();
        expect(removeSpy).toHaveBeenCalledWith('change', expect.any(Function));
    });

    test('optionsForFetch returns correct headers', () => {
        const options = controller.optionsForFetch();
        expect(options.headers['X-Requested-With']).toBe('XMLHttpRequest');
        expect(options.headers['Accept']).toBe('application/json');
    });

    test('checkEmail clears validation when email is empty', () => {
        controller.element.value = '';
        controller.element.classList.add('is-invalid');
        controller.checkEmail({ target: controller.element });
        expect(controller.element.classList.contains('is-invalid')).toBe(false);
        expect(controller.element.classList.contains('is-valid')).toBe(false);
    });

    test('checkEmail marks valid when email matches original', () => {
        controller.element.value = 'existing@example.com';
        controller.element.dataset.originalValue = 'existing@example.com';
        controller.checkEmail({ target: controller.element });
        expect(controller.element.classList.contains('is-valid')).toBe(true);
        expect(controller.element.classList.contains('is-invalid')).toBe(false);
    });

    test('checkEmail case-insensitive original comparison', () => {
        controller.element.value = 'Existing@Example.COM';
        controller.element.dataset.originalValue = 'existing@example.com';
        controller.checkEmail({ target: controller.element });
        expect(controller.element.classList.contains('is-valid')).toBe(true);
    });

    test('checkEmail fetches API for new email and marks invalid when taken', async () => {
        global.fetch = jest.fn(() =>
            Promise.resolve({ json: () => Promise.resolve(true) })
        );

        controller.element.value = 'new@example.com';
        controller.element.dataset.originalValue = 'existing@example.com';
        controller.checkEmail({ target: controller.element });

        expect(global.fetch).toHaveBeenCalledWith(
            expect.stringContaining('/api/check-email?nostack=yes&email=new%40example.com'),
            expect.any(Object)
        );

        // Flush microtask queue (fetch -> response.json -> then)
        await new Promise(r => setTimeout(r, 0));

        expect(controller.element.classList.contains('is-invalid')).toBe(true);
    });

    test('checkEmail marks valid when email is available', async () => {
        global.fetch = jest.fn(() =>
            Promise.resolve({ json: () => Promise.resolve(false) })
        );

        controller.element.value = 'available@example.com';
        controller.element.dataset.originalValue = 'existing@example.com';
        controller.checkEmail({ target: controller.element });

        await new Promise(r => setTimeout(r, 0));

        expect(controller.element.classList.contains('is-valid')).toBe(true);
        expect(controller.element.classList.contains('is-invalid')).toBe(false);
    });
});
