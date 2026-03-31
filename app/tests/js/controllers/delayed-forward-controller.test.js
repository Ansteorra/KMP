require('../../../assets/js/controllers/delayed-forward-controller.js');
const DelayForwardController = window.Controllers['delay-forward'];

describe('DelayForwardController', () => {
    let controller;

    beforeEach(() => {
        jest.useFakeTimers();

        document.body.innerHTML = `
            <div data-controller="delayed-forward"
                 data-delayed-forward-url-value="/dashboard"
                 data-delayed-forward-delay-ms-value="3000">
            </div>
        `;

        controller = new DelayForwardController();
        controller.element = document.querySelector('[data-controller="delayed-forward"]');
        controller.urlValue = '/dashboard';
        controller.delayMsValue = 3000;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.useRealTimers();
        jest.restoreAllMocks();
    });

    test('registers on window.Controllers as delay-forward', () => {
        expect(window.Controllers['delay-forward']).toBe(DelayForwardController);
    });

    test('has correct static values', () => {
        expect(DelayForwardController.values).toHaveProperty('url', String);
        expect(DelayForwardController.values).toHaveProperty('delayMs', Number);
    });

    test('connect initializes timeout to null and calls forward', () => {
        const forwardSpy = jest.spyOn(controller, 'forward').mockImplementation(() => {});
        controller.connect();
        expect(forwardSpy).toHaveBeenCalled();
    });

    test('forward sets a timeout', () => {
        controller.forward();
        expect(controller.timeout).not.toBeNull();
    });

    test('forward redirects after delay', () => {
        controller.forward();
        jest.advanceTimersByTime(3000);
        // Controller logs before redirect
        expect(console.log).toHaveBeenCalledWith('Forwarding to /dashboard');
    });

    test('forward does not redirect before delay', () => {
        console.log.mockClear();
        controller.forward();
        jest.advanceTimersByTime(2999);
        expect(console.log).not.toHaveBeenCalledWith(expect.stringContaining('Forwarding to'));
    });

    test('forward clears existing timeout before setting new one', () => {
        controller.forward();
        const firstTimeout = controller.timeout;
        controller.forward();
        expect(controller.timeout).not.toBe(firstTimeout);
        // Only one timer should be active
        expect(jest.getTimerCount()).toBe(1);
    });

    test('disconnect clears timeout', () => {
        controller.forward();
        expect(jest.getTimerCount()).toBe(1);
        controller.disconnect();
        expect(jest.getTimerCount()).toBe(0);
    });

    test('disconnect handles null timeout gracefully', () => {
        controller.timeout = null;
        expect(() => controller.disconnect()).not.toThrow();
    });

    test('redirect uses correct URL value', () => {
        controller.urlValue = '/members/list';
        controller.delayMsValue = 3000;
        controller.forward();
        jest.advanceTimersByTime(3000);
        expect(console.log).toHaveBeenCalledWith('Forwarding to /members/list');
    });
});
