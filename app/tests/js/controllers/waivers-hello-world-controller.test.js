// Controller registers on window.Controllers (no default export)
import '../../../plugins/Waivers/assets/js/controllers/hello-world-controller.js';
const HelloWorldController = window.Controllers['hello-world'];

describe('Waivers HelloWorldController', () => {
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

    test('has correct static values', () => {
        expect(HelloWorldController.values).toHaveProperty('message');
        expect(HelloWorldController.values).toHaveProperty('count');
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['hello-world']).toBe(HelloWorldController);
    });

    // --- connect ---

    test('connect calls updateCounter', () => {
        const spy = jest.spyOn(controller, 'updateCounter');
        controller.connect();
        expect(spy).toHaveBeenCalled();
    });

    // --- greet ---

    test('greet displays greeting with input name', () => {
        controller.inputTarget.value = 'Alice';
        const event = { preventDefault: jest.fn() };
        controller.greet(event);

        expect(event.preventDefault).toHaveBeenCalled();
        expect(controller.outputTarget.textContent).toBe('Hello, World!, Alice!');
        expect(controller.outputTarget.classList.contains('alert-success')).toBe(true);
    });

    test('greet uses "World" when input is empty and no inputTarget', () => {
        controller.hasInputTarget = false;
        const event = { preventDefault: jest.fn() };
        controller.greet(event);

        expect(controller.outputTarget.textContent).toBe('Hello, World!, World!');
    });

    test('greet increments countValue', () => {
        controller.countValue = 5;
        const event = { preventDefault: jest.fn() };
        controller.greet(event);
        expect(controller.countValue).toBe(6);
    });

    // --- clear ---

    test('clear resets input and output', () => {
        controller.inputTarget.value = 'Alice';
        controller.outputTarget.textContent = 'Hello';
        controller.outputTarget.className = 'alert alert-success';

        const event = { preventDefault: jest.fn() };
        controller.clear(event);

        expect(controller.inputTarget.value).toBe('');
        expect(controller.outputTarget.textContent).toBe('');
        expect(controller.outputTarget.className).toBe('');
    });

    test('clear handles missing input target', () => {
        controller.hasInputTarget = false;
        const event = { preventDefault: jest.fn() };
        expect(() => controller.clear(event)).not.toThrow();
    });

    // --- updateCounter ---

    test('updateCounter sets counter text to countValue', () => {
        controller.countValue = 42;
        controller.updateCounter();
        expect(controller.counterTarget.textContent).toBe('42');
    });

    test('updateCounter does nothing when no counterTarget', () => {
        controller.hasCounterTarget = false;
        expect(() => controller.updateCounter()).not.toThrow();
    });

    // --- countValueChanged ---

    test('countValueChanged calls updateCounter', () => {
        const spy = jest.spyOn(controller, 'updateCounter');
        controller.countValueChanged();
        expect(spy).toHaveBeenCalled();
    });

    // --- showMessage ---

    test('showMessage displays message with info styling', () => {
        controller.showMessage('Custom message');
        expect(controller.outputTarget.textContent).toBe('Custom message');
        expect(controller.outputTarget.classList.contains('alert-info')).toBe(true);
    });

    test('showMessage does nothing when no outputTarget', () => {
        controller.hasOutputTarget = false;
        expect(() => controller.showMessage('test')).not.toThrow();
    });

    // --- fetchData ---

    test('fetchData returns data on success', async () => {
        global.fetch = jest.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve({ key: 'value' })
        }));

        const result = await controller.fetchData('/api/test');
        expect(result).toEqual({ key: 'value' });
    });

    test('fetchData shows error on failure', async () => {
        global.fetch = jest.fn(() => Promise.resolve({
            ok: false,
            status: 500
        }));

        const result = await controller.fetchData('/api/test');
        expect(result).toBeNull();
        expect(controller.outputTarget.textContent).toBe('Error loading data');
        expect(controller.outputTarget.classList.contains('alert-danger')).toBe(true);
    });

    test('fetchData handles network error', async () => {
        global.fetch = jest.fn(() => Promise.reject(new Error('Network error')));

        const result = await controller.fetchData('/api/test');
        expect(result).toBeNull();
    });
});
