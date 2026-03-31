// Template plugin's hello-world controller (identical code to Waivers, different plugin path)
import '../../../plugins/Template/assets/js/controllers/hello-world-controller.js';
const HelloWorldController = window.Controllers['hello-world'];

describe('Template HelloWorldController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="hello-world"
                 data-hello-world-message-value="Hello, World!"
                 data-hello-world-count-value="0">
                <input data-hello-world-target="input" type="text" value="">
                <div data-hello-world-target="output"></div>
                <span data-hello-world-target="counter">0</span>
            </div>
        `;

        controller = new HelloWorldController();
        controller.element = document.querySelector('[data-controller="hello-world"]');

        // Wire up targets
        controller.inputTarget = document.querySelector('[data-hello-world-target="input"]');
        controller.outputTarget = document.querySelector('[data-hello-world-target="output"]');
        controller.counterTarget = document.querySelector('[data-hello-world-target="counter"]');

        // Wire up has* checks
        controller.hasInputTarget = true;
        controller.hasOutputTarget = true;
        controller.hasCounterTarget = true;

        // Wire up values
        controller.messageValue = 'Hello, World!';
        controller.countValue = 0;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
        delete global.fetch;
    });

    // --- Static properties ---

    test('has correct static targets', () => {
        expect(HelloWorldController.targets).toEqual(
            expect.arrayContaining(['input', 'output', 'counter'])
        );
    });

    test('has correct static values with message and count', () => {
        expect(HelloWorldController.values).toHaveProperty('message');
        expect(HelloWorldController.values).toHaveProperty('count');
    });

    test('registers on window.Controllers as hello-world', () => {
        expect(window.Controllers['hello-world']).toBe(HelloWorldController);
    });

    // --- connect ---

    test('connect calls updateCounter', () => {
        const spy = jest.spyOn(controller, 'updateCounter');
        controller.connect();
        expect(spy).toHaveBeenCalled();
    });

    // --- greet ---

    test('greet builds greeting from input and message value', () => {
        controller.inputTarget.value = 'Bob';
        const event = { preventDefault: jest.fn() };
        controller.greet(event);

        expect(controller.outputTarget.textContent).toBe('Hello, World!, Bob!');
        expect(controller.outputTarget.classList.contains('alert')).toBe(true);
        expect(controller.outputTarget.classList.contains('alert-success')).toBe(true);
    });

    test('greet defaults to "World" when no input target', () => {
        controller.hasInputTarget = false;
        const event = { preventDefault: jest.fn() };
        controller.greet(event);

        expect(controller.outputTarget.textContent).toBe('Hello, World!, World!');
    });

    test('greet increments count value', () => {
        controller.countValue = 0;
        const event = { preventDefault: jest.fn() };
        controller.greet(event);
        expect(controller.countValue).toBe(1);
    });

    test('greet does nothing to output if no outputTarget', () => {
        controller.hasOutputTarget = false;
        controller.countValue = 0;
        const event = { preventDefault: jest.fn() };
        controller.greet(event);
        // Should still increment count
        expect(controller.countValue).toBe(1);
    });

    // --- clear ---

    test('clear empties input and output', () => {
        controller.inputTarget.value = 'some text';
        controller.outputTarget.textContent = 'output text';
        controller.outputTarget.className = 'alert alert-success mt-3';

        const event = { preventDefault: jest.fn() };
        controller.clear(event);

        expect(controller.inputTarget.value).toBe('');
        expect(controller.outputTarget.textContent).toBe('');
        expect(controller.outputTarget.className).toBe('');
    });

    // --- updateCounter ---

    test('updateCounter sets counter text content', () => {
        controller.countValue = 99;
        controller.updateCounter();
        expect(controller.counterTarget.textContent).toBe('99');
    });

    test('updateCounter skips when no counter target', () => {
        controller.hasCounterTarget = false;
        expect(() => controller.updateCounter()).not.toThrow();
    });

    // --- showMessage ---

    test('showMessage displays message with alert-info styling', () => {
        controller.showMessage('Info message');
        expect(controller.outputTarget.textContent).toBe('Info message');
        expect(controller.outputTarget.classList.contains('alert-info')).toBe(true);
    });

    // --- fetchData ---

    test('fetchData returns parsed JSON on success', async () => {
        global.fetch = jest.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve({ data: 'test' })
        }));

        const result = await controller.fetchData('/api/data');
        expect(result).toEqual({ data: 'test' });
    });

    test('fetchData returns null and shows error on HTTP error', async () => {
        global.fetch = jest.fn(() => Promise.resolve({
            ok: false,
            status: 404
        }));

        const result = await controller.fetchData('/api/data');
        expect(result).toBeNull();
        expect(controller.outputTarget.textContent).toBe('Error loading data');
    });

    test('fetchData returns null on network error', async () => {
        global.fetch = jest.fn(() => Promise.reject(new Error('Failed to fetch')));

        const result = await controller.fetchData('/api/data');
        expect(result).toBeNull();
    });

    // --- messageValueChanged ---

    test('messageValueChanged logs the new value', () => {
        controller.messageValue = 'New Message';
        controller.messageValueChanged();
        // console.log is mocked in setup, just verify no throw
        expect(console.log).toHaveBeenCalled();
    });
});
