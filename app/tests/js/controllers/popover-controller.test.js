import '../../../assets/js/controllers/popover-controller.js';
const PopoverController = window.Controllers['popover'];

// Mock Bootstrap Popover
const mockPopoverInstance = {
    show: jest.fn(),
    hide: jest.fn(),
    toggle: jest.fn(),
    dispose: jest.fn()
};

beforeAll(() => {
    window.bootstrap.Popover = jest.fn().mockImplementation(() => mockPopoverInstance);
    window.bootstrap.Popover.Default = {
        allowList: { a: ['href', 'title'], div: ['class'] }
    };
});

describe('PopoverController', () => {
    let controller;

    beforeEach(() => {
        jest.clearAllMocks();

        document.body.innerHTML = `
            <button data-controller="popover"
                    data-bs-toggle="popover"
                    data-bs-trigger="click"
                    data-bs-html="true"
                    data-bs-content="<div>Content</div>">
                Open Popover
            </button>
        `;

        controller = new PopoverController();
        controller.element = document.querySelector('[data-controller="popover"]');
        controller.placementValue = 'auto';
        controller.triggerValue = 'click';
        controller.htmlValue = true;
        controller.customClassValue = '';
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    // --- Static properties ---

    test('has correct static values', () => {
        expect(PopoverController.values).toHaveProperty('placement');
        expect(PopoverController.values).toHaveProperty('trigger');
        expect(PopoverController.values).toHaveProperty('html');
        expect(PopoverController.values).toHaveProperty('customClass');
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['popover']).toBe(PopoverController);
    });

    // --- connect ---

    test('connect initializes Bootstrap popover', () => {
        controller.connect();

        expect(window.bootstrap.Popover).toHaveBeenCalledWith(
            controller.element,
            expect.objectContaining({
                placement: 'auto',
                trigger: 'click',
                html: true,
                allowList: expect.objectContaining({
                    button: ['type', 'class', 'aria-label']
                })
            })
        );
        expect(controller.popover).toBe(mockPopoverInstance);
    });

    test('connect includes customClass when provided', () => {
        controller.customClassValue = 'my-custom-popover';
        controller.connect();

        expect(window.bootstrap.Popover).toHaveBeenCalledWith(
            controller.element,
            expect.objectContaining({ customClass: 'my-custom-popover' })
        );
    });

    test('connect sets up close button handler', () => {
        const addSpy = jest.spyOn(document, 'addEventListener');
        controller.connect();
        expect(addSpy).toHaveBeenCalledWith('click', expect.any(Function));
        expect(addSpy).toHaveBeenCalledWith('keydown', expect.any(Function));
    });

    // --- disconnect ---

    test('disconnect disposes popover and removes handler', () => {
        controller.connect();
        const removeSpy = jest.spyOn(document, 'removeEventListener');

        controller.disconnect();

        expect(mockPopoverInstance.dispose).toHaveBeenCalled();
        expect(controller.popover).toBeNull();
        expect(removeSpy).toHaveBeenCalledWith('click', expect.any(Function));
        expect(removeSpy).toHaveBeenCalledWith('keydown', expect.any(Function));
    });

    test('disconnect handles null popover gracefully', () => {
        controller.popover = null;
        controller.handleCloseClick = jest.fn();
        controller.disconnect();
        // Should not throw
    });

    // --- show / hide / toggle ---

    test('show calls popover.show', () => {
        controller.connect();
        controller.show();
        expect(mockPopoverInstance.show).toHaveBeenCalled();
    });

    test('hide calls popover.hide', () => {
        controller.connect();
        controller.hide();
        expect(mockPopoverInstance.hide).toHaveBeenCalled();
    });

    test('toggle calls popover.toggle', () => {
        controller.connect();
        controller.toggle();
        expect(mockPopoverInstance.toggle).toHaveBeenCalled();
    });

    test('show does nothing when popover is null', () => {
        controller.popover = null;
        controller.show();
        expect(mockPopoverInstance.show).not.toHaveBeenCalled();
    });

    // --- handleCloseClick ---

    test('handleCloseClick hides popover when close button clicked', () => {
        controller.connect();

        // Create popover element in DOM
        const popover = document.createElement('div');
        popover.className = 'popover';
        popover.id = 'popover-1';
        const closeBtn = document.createElement('button');
        closeBtn.className = 'btn-close';
        popover.appendChild(closeBtn);
        document.body.appendChild(popover);

        controller.element.setAttribute('aria-describedby', 'popover-1');

        const event = {
            target: closeBtn,
            preventDefault: jest.fn(),
            stopPropagation: jest.fn()
        };

        controller.handleCloseClick(event);

        expect(mockPopoverInstance.hide).toHaveBeenCalled();
        expect(event.preventDefault).toHaveBeenCalled();
        expect(event.stopPropagation).toHaveBeenCalled();
    });

    test('handleCloseClick ignores non-close-button clicks', () => {
        controller.connect();
        const event = {
            target: document.body,
            preventDefault: jest.fn(),
            stopPropagation: jest.fn()
        };

        controller.handleCloseClick(event);

        expect(mockPopoverInstance.hide).not.toHaveBeenCalled();
    });

    test('handleCloseClick ignores close button from different popover', () => {
        controller.connect();

        const popover = document.createElement('div');
        popover.className = 'popover';
        popover.id = 'popover-2';
        const closeBtn = document.createElement('button');
        closeBtn.className = 'btn-close';
        popover.appendChild(closeBtn);
        document.body.appendChild(popover);

        controller.element.setAttribute('aria-describedby', 'popover-1');

        const event = {
            target: closeBtn,
            preventDefault: jest.fn(),
            stopPropagation: jest.fn()
        };

        controller.handleCloseClick(event);

        expect(mockPopoverInstance.hide).not.toHaveBeenCalled();
    });

    test('Escape hides popover and restores focus to trigger', () => {
        controller.connect();
        controller.element.setAttribute('aria-describedby', 'popover-1');
        const event = { key: 'Escape', preventDefault: jest.fn() };

        controller.handleEscapeKey(event);

        expect(mockPopoverInstance.hide).toHaveBeenCalled();
        expect(document.activeElement).toBe(controller.element);
        expect(event.preventDefault).toHaveBeenCalled();
    });

    test('Escape does nothing when this popover is not shown', () => {
        controller.connect();
        const event = { key: 'Escape', preventDefault: jest.fn() };

        controller.handleEscapeKey(event);

        expect(mockPopoverInstance.hide).not.toHaveBeenCalled();
        expect(event.preventDefault).not.toHaveBeenCalled();
    });
});
