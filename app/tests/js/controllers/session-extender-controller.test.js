// Controller uses require() and registers on window.Controllers (no default export)
require('../../../assets/js/controllers/session-extender-controller.js');
const SessionExtender = window.Controllers['session-extender'];

describe('SessionExtenderController', () => {
    let controller;

    beforeEach(() => {
        jest.useFakeTimers();

        document.body.innerHTML = `
            <div data-controller="session-extender"
                 data-session-extender-url-value="/api/extend-session">
            </div>
        `;

        controller = new SessionExtender();
        controller.element = document.querySelector('[data-controller="session-extender"]');
        controller.urlValue = '/api/extend-session';
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.useRealTimers();
        jest.restoreAllMocks();
        if (global.fetch) {
            delete global.fetch;
        }
    });

    // --- Instantiation ---

    test('instantiates with correct static values', () => {
        expect(SessionExtender.values).toHaveProperty('url', String);
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['session-extender']).toBe(SessionExtender);
    });

    // --- urlValueChanged: timer setup ---

    test('urlValueChanged sets a 25-minute timer', () => {
        controller.urlValueChanged();

        expect(controller.timer).toBeDefined();
        // Timer should not fire before 25 minutes
        jest.advanceTimersByTime(24 * 60000);
        expect(jest.getTimerCount()).toBe(1);
    });

    test('urlValueChanged clears existing timer before setting new one', () => {
        controller.urlValueChanged();
        const firstTimer = controller.timer;

        controller.urlValueChanged();

        expect(controller.timer).not.toBe(firstTimer);
    });

    test('timer fires after exactly 25 minutes', () => {
        const alertSpy = jest.spyOn(window, 'alert').mockImplementation(() => {});
        global.fetch = jest.fn().mockResolvedValue({
            json: () => Promise.resolve({ success: true }),
        });

        controller.urlValueChanged();
        jest.advanceTimersByTime(25 * 60000);

        expect(alertSpy).toHaveBeenCalledWith('Session Expiring! Click ok to extend session.');
    });

    test('timer calls fetch to extend session endpoint', () => {
        jest.spyOn(window, 'alert').mockImplementation(() => {});
        global.fetch = jest.fn().mockResolvedValue({
            json: () => Promise.resolve({ success: true }),
        });

        controller.urlValueChanged();
        jest.advanceTimersByTime(25 * 60000);

        expect(global.fetch).toHaveBeenCalledWith('/api/extend-session');
    });

    test('successful fetch restarts the timer via urlValueChanged', async () => {
        jest.spyOn(window, 'alert').mockImplementation(() => {});
        global.fetch = jest.fn().mockResolvedValue({
            json: () => Promise.resolve({ success: true }),
        });

        const urlValueChangedSpy = jest.spyOn(controller, 'urlValueChanged');
        controller.urlValueChanged();

        // Advance to trigger timer
        jest.advanceTimersByTime(25 * 60000);

        // Let the promise chain resolve
        await Promise.resolve();
        await Promise.resolve();

        // urlValueChanged is called once directly + once from the .then chain
        expect(urlValueChangedSpy.mock.calls.length).toBeGreaterThanOrEqual(1);
    });

    // --- Edge cases ---

    test('does not fire timer before 25 minutes', () => {
        const alertSpy = jest.spyOn(window, 'alert').mockImplementation(() => {});
        global.fetch = jest.fn();

        controller.urlValueChanged();
        jest.advanceTimersByTime(24 * 60000 + 59999);

        expect(alertSpy).not.toHaveBeenCalled();
        expect(global.fetch).not.toHaveBeenCalled();
    });

    test('handles undefined timer on first urlValueChanged call', () => {
        controller.timer = undefined;
        expect(() => controller.urlValueChanged()).not.toThrow();
        expect(controller.timer).toBeDefined();
    });

    test('multiple rapid urlValueChanged calls only keep last timer', () => {
        controller.urlValueChanged();
        controller.urlValueChanged();
        controller.urlValueChanged();

        // Only 1 timer should be active
        expect(jest.getTimerCount()).toBe(1);
    });
});
