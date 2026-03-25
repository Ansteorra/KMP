import '../../../assets/js/controllers/security-debug-controller.js';
const SecurityDebugController = window.Controllers['security-debug'];

describe('SecurityDebugController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="security-debug">
                <button data-security-debug-target="toggleBtn"
                        data-action="click->security-debug#toggle">
                    Show Security Info
                </button>
                <div data-security-debug-target="panel" style="display: none;">
                    <p>Security debug info here</p>
                </div>
            </div>
        `;

        controller = new SecurityDebugController();
        controller.element = document.querySelector('[data-controller="security-debug"]');
        controller.panelTarget = document.querySelector('[data-security-debug-target="panel"]');
        controller.toggleBtnTarget = document.querySelector('[data-security-debug-target="toggleBtn"]');
        controller.hasPanelTarget = true;
        controller.hasToggleBtnTarget = true;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['security-debug']).toBe(SecurityDebugController);
    });

    test('has correct static targets', () => {
        expect(SecurityDebugController.targets).toEqual(
            expect.arrayContaining(['panel', 'toggleBtn'])
        );
    });

    test('initialize sets isVisible to false', () => {
        controller.initialize();
        expect(controller.isVisible).toBe(false);
    });

    test('toggle shows panel when hidden', () => {
        controller.initialize();
        const event = { preventDefault: jest.fn() };
        controller.toggle(event);
        expect(controller.panelTarget.style.display).toBe('block');
        expect(controller.isVisible).toBe(true);
    });

    test('toggle hides panel when visible', () => {
        controller.isVisible = true;
        const event = { preventDefault: jest.fn() };
        controller.toggle(event);
        expect(controller.panelTarget.style.display).toBe('none');
        expect(controller.isVisible).toBe(false);
    });

    test('toggle prevents default', () => {
        controller.initialize();
        const event = { preventDefault: jest.fn() };
        controller.toggle(event);
        expect(event.preventDefault).toHaveBeenCalled();
    });

    test('show sets panel visible and updates button text', () => {
        controller.initialize();
        controller.show();
        expect(controller.panelTarget.style.display).toBe('block');
        expect(controller.toggleBtnTarget.textContent).toBe('Hide Security Info');
        expect(controller.isVisible).toBe(true);
    });

    test('hide sets panel hidden and updates button text', () => {
        controller.isVisible = true;
        controller.hide();
        expect(controller.panelTarget.style.display).toBe('none');
        expect(controller.toggleBtnTarget.textContent).toBe('Show Security Info');
        expect(controller.isVisible).toBe(false);
    });

    test('show scrolls panel into view', () => {
        jest.useFakeTimers();
        controller.panelTarget.scrollIntoView = jest.fn();
        controller.initialize();
        controller.show();
        jest.advanceTimersByTime(100);
        expect(controller.panelTarget.scrollIntoView).toHaveBeenCalledWith({ behavior: 'smooth', block: 'nearest' });
        jest.useRealTimers();
    });

    test('show handles missing toggleBtn target', () => {
        controller.hasToggleBtnTarget = false;
        controller.initialize();
        expect(() => controller.show()).not.toThrow();
        expect(controller.panelTarget.style.display).toBe('block');
    });

    test('hide handles missing toggleBtn target', () => {
        controller.hasToggleBtnTarget = false;
        controller.isVisible = true;
        expect(() => controller.hide()).not.toThrow();
        expect(controller.panelTarget.style.display).toBe('none');
    });
});
