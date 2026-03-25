import '../../../assets/js/controllers/image-zoom-controller.js';
const ImageZoomController = window.Controllers['image-zoom'];

describe('ImageZoomController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="image-zoom"
                 data-image-zoom-min-scale-value="1"
                 data-image-zoom-max-scale-value="8"
                 style="width: 400px; height: 300px;">
                <img data-image-zoom-target="image" src="test.jpg"
                     style="width: 200px; height: 150px;">
            </div>
        `;

        controller = new ImageZoomController();
        controller.element = document.querySelector('[data-controller="image-zoom"]');

        const img = document.querySelector('[data-image-zoom-target="image"]');
        controller.imageTarget = img;
        controller.hasImageTarget = true;
        controller.minScaleValue = 1;
        controller.maxScaleValue = 8;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    // --- Static properties ---

    test('has correct static targets', () => {
        expect(ImageZoomController.targets).toEqual(['image']);
    });

    test('has correct static values', () => {
        expect(ImageZoomController.values).toHaveProperty('minScale');
        expect(ImageZoomController.values).toHaveProperty('maxScale');
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['image-zoom']).toBe(ImageZoomController);
    });

    // --- Connect ---

    test('connect initializes state variables', () => {
        controller.connect();
        expect(controller.scale).toBe(1);
        expect(controller.translateX).toBe(0);
        expect(controller.translateY).toBe(0);
        expect(controller.dragging).toBe(false);
        expect(controller.initialPinchDistance).toBeNull();
    });

    test('connect sets container styles', () => {
        controller.connect();
        expect(controller.element.style.overflow).toBe('hidden');
        expect(controller.element.style.cursor).toBe('grab');
        expect(controller.element.style.touchAction).toBe('none');
    });

    test('connect sets image styles', () => {
        controller.connect();
        const img = controller.imageTarget;
        expect(img.style.transformOrigin).toBe('0 0');
        expect(img.style.userSelect).toBe('none');
        expect(img.draggable).toBe(false);
    });

    test('connect registers event listeners on container', () => {
        const addSpy = jest.spyOn(controller.element, 'addEventListener');
        controller.connect();
        const eventTypes = addSpy.mock.calls.map(c => c[0]);
        expect(eventTypes).toContain('wheel');
        expect(eventTypes).toContain('pointerdown');
        expect(eventTypes).toContain('pointermove');
        expect(eventTypes).toContain('pointerup');
        expect(eventTypes).toContain('pointerleave');
        expect(eventTypes).toContain('dblclick');
        expect(eventTypes).toContain('touchstart');
        expect(eventTypes).toContain('touchmove');
        expect(eventTypes).toContain('touchend');
    });

    // --- Disconnect ---

    test('disconnect removes event listeners', () => {
        controller.connect();
        const removeSpy = jest.spyOn(controller.element, 'removeEventListener');
        const imgRemoveSpy = jest.spyOn(controller.imageTarget, 'removeEventListener');
        controller.disconnect();
        const containerEvents = removeSpy.mock.calls.map(c => c[0]);
        expect(containerEvents).toContain('wheel');
        expect(containerEvents).toContain('pointerdown');
        expect(containerEvents).toContain('dblclick');
        expect(imgRemoveSpy).toHaveBeenCalledWith('load', expect.any(Function));
    });

    // --- _resetView ---

    test('_resetView resets scale and translation', () => {
        controller.connect();
        controller.scale = 4;
        controller.translateX = 100;
        controller.translateY = 50;

        controller._resetView();

        expect(controller.scale).toBe(1);
        expect(controller.translateX).toBe(0);
        expect(controller.translateY).toBe(0);
    });

    // --- _onDblClick ---

    test('_onDblClick calls _resetView', () => {
        controller.connect();
        const resetSpy = jest.spyOn(controller, '_resetView');
        controller._onDblClick();
        expect(resetSpy).toHaveBeenCalled();
    });

    // --- _onWheel ---

    test('_onWheel prevents default and zooms', () => {
        controller.connect();
        const zoomSpy = jest.spyOn(controller, '_zoomAt').mockImplementation(() => {});
        const event = {
            preventDefault: jest.fn(),
            deltaY: -100,
            clientX: 200,
            clientY: 150
        };
        // Mock getBoundingClientRect
        controller.element.getBoundingClientRect = jest.fn().mockReturnValue({
            left: 0, top: 0, width: 400, height: 300
        });

        controller._onWheel(event);

        expect(event.preventDefault).toHaveBeenCalled();
        expect(zoomSpy).toHaveBeenCalledWith(200, 150, 1.1);
    });

    test('_onWheel zooms out when deltaY is positive', () => {
        controller.connect();
        const zoomSpy = jest.spyOn(controller, '_zoomAt').mockImplementation(() => {});
        controller.element.getBoundingClientRect = jest.fn().mockReturnValue({
            left: 0, top: 0, width: 400, height: 300
        });
        const event = { preventDefault: jest.fn(), deltaY: 100, clientX: 200, clientY: 150 };

        controller._onWheel(event);

        expect(zoomSpy).toHaveBeenCalledWith(200, 150, 0.9);
    });

    // --- _onPointerDown / _onPointerMove / _onPointerUp ---

    test('_onPointerDown starts dragging for mouse', () => {
        controller.connect();
        const event = {
            pointerType: 'mouse',
            clientX: 100,
            clientY: 100,
            pointerId: 1
        };
        controller.element.setPointerCapture = jest.fn();

        controller._onPointerDown(event);

        expect(controller.dragging).toBe(true);
        expect(controller.lastX).toBe(100);
        expect(controller.lastY).toBe(100);
        expect(controller.element.style.cursor).toBe('grabbing');
    });

    test('_onPointerDown ignores touch events', () => {
        controller.connect();
        const event = { pointerType: 'touch', clientX: 100, clientY: 100, pointerId: 1 };
        controller.element.setPointerCapture = jest.fn();

        controller._onPointerDown(event);

        expect(controller.dragging).toBe(false);
    });

    test('_onPointerMove updates translation when dragging', () => {
        controller.connect();
        controller.dragging = true;
        controller.lastX = 100;
        controller.lastY = 100;
        jest.spyOn(controller, '_clampTranslation').mockImplementation(() => {});
        jest.spyOn(controller, '_applyTransform').mockImplementation(() => {});

        const event = { pointerType: 'mouse', clientX: 120, clientY: 130 };
        controller._onPointerMove(event);

        expect(controller.translateX).toBe(20);
        expect(controller.translateY).toBe(30);
    });

    test('_onPointerMove does nothing when not dragging', () => {
        controller.connect();
        controller.dragging = false;
        const initialTx = controller.translateX;

        const event = { pointerType: 'mouse', clientX: 120, clientY: 130 };
        controller._onPointerMove(event);

        expect(controller.translateX).toBe(initialTx);
    });

    test('_onPointerUp stops dragging', () => {
        controller.connect();
        controller.dragging = true;
        controller._onPointerUp({ pointerType: 'mouse' });
        expect(controller.dragging).toBe(false);
        expect(controller.element.style.cursor).toBe('grab');
    });

    // --- _zoomAt ---

    test('_zoomAt respects min and max scale', () => {
        controller.connect();
        jest.spyOn(controller, '_clampTranslation').mockImplementation(() => {});
        jest.spyOn(controller, '_applyTransform').mockImplementation(() => {});

        // Zoom in past max
        controller.scale = 7.5;
        controller._zoomAt(200, 150, 2);
        expect(controller.scale).toBeLessThanOrEqual(8);

        // Zoom out past min
        controller.scale = 1.1;
        controller._zoomAt(200, 150, 0.01);
        expect(controller.scale).toBeGreaterThanOrEqual(1);
    });

    // --- _pinchDistance ---

    test('_pinchDistance calculates distance between two touches', () => {
        controller.connect();
        const touches = [
            { clientX: 0, clientY: 0 },
            { clientX: 3, clientY: 4 }
        ];
        expect(controller._pinchDistance(touches)).toBe(5);
    });

    // --- Touch events ---

    test('_onTouchStart sets up pinch zoom for two fingers', () => {
        controller.connect();
        const event = {
            touches: [
                { clientX: 0, clientY: 0 },
                { clientX: 10, clientY: 0 }
            ],
            preventDefault: jest.fn()
        };

        controller._onTouchStart(event);

        expect(controller.initialPinchDistance).toBe(10);
        expect(controller.initialPinchScale).toBe(1);
        expect(event.preventDefault).toHaveBeenCalled();
    });

    test('_onTouchStart starts single finger drag when zoomed', () => {
        controller.connect();
        controller.scale = 2;
        const event = {
            touches: [{ clientX: 50, clientY: 50 }],
            preventDefault: jest.fn()
        };

        controller._onTouchStart(event);

        expect(controller.dragging).toBe(true);
        expect(controller.lastX).toBe(50);
        expect(controller.lastY).toBe(50);
    });

    test('_onTouchEnd resets pinch distance', () => {
        controller.connect();
        controller.initialPinchDistance = 100;
        controller.dragging = true;

        controller._onTouchEnd({ touches: [] });

        expect(controller.initialPinchDistance).toBeNull();
        expect(controller.dragging).toBe(false);
    });

    // --- Modal support ---

    test('connect listens for modal shown event when inside modal', () => {
        document.body.innerHTML = `
            <div class="modal">
                <div data-controller="image-zoom" style="width: 400px; height: 300px;">
                    <img data-image-zoom-target="image" src="test.jpg" style="width: 200px; height: 150px;">
                </div>
            </div>
        `;

        const ctrl = new ImageZoomController();
        ctrl.element = document.querySelector('[data-controller="image-zoom"]');
        ctrl.imageTarget = document.querySelector('[data-image-zoom-target="image"]');
        ctrl.minScaleValue = 1;
        ctrl.maxScaleValue = 8;

        const modal = document.querySelector('.modal');
        const addSpy = jest.spyOn(modal, 'addEventListener');

        ctrl.connect();

        expect(addSpy).toHaveBeenCalledWith('shown.bs.modal', expect.any(Function));
    });
});
