import MobileControllerBase from '../../../assets/js/controllers/mobile-controller-base.js';
import '../../../assets/js/controllers/mobile-offline-overlay-controller.js';
const MobileOfflineOverlayController = window.Controllers['mobile-offline-overlay'];

describe('MobileOfflineOverlayController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="mobile-offline-overlay"
                 data-mobile-offline-overlay-auth-card-url-value="/members/1/card">
            </div>
        `;

        MobileControllerBase.setOnlineState(true, false);
        MobileControllerBase.connectionListeners = new Set();

        controller = new MobileOfflineOverlayController();
        controller.element = document.querySelector('[data-controller="mobile-offline-overlay"]');
        controller.authCardUrlValue = '/members/1/card';
        controller.hasAuthCardUrlValue = true;
        controller._boundHandlers = new Map();
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
        // Clean up any overlays
        document.querySelectorAll('.mobile-offline-overlay').forEach(el => el.remove());
        document.body.style.overflow = '';
    });

    // --- Static properties ---

    test('has correct static values', () => {
        expect(MobileOfflineOverlayController.values).toHaveProperty('authCardUrl', String);
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['mobile-offline-overlay']).toBe(MobileOfflineOverlayController);
    });

    // --- showOverlay ---

    test('showOverlay creates overlay element', () => {
        controller.overlay = null;
        controller.showOverlay();

        const overlay = document.querySelector('.mobile-offline-overlay');
        expect(overlay).toBeTruthy();
        expect(overlay.getAttribute('role')).toBe('dialog');
        expect(overlay.getAttribute('aria-modal')).toBe('true');
        expect(overlay.innerHTML).toContain("You're Offline");
        expect(overlay.innerHTML).toContain('/members/1/card');
    });

    test('showOverlay disables body scroll', () => {
        controller.overlay = null;
        controller.showOverlay();
        expect(document.body.style.overflow).toBe('hidden');
    });

    test('showOverlay does not create duplicate overlays', () => {
        controller.overlay = null;
        controller.showOverlay();
        controller.showOverlay();

        const overlays = document.querySelectorAll('.mobile-offline-overlay');
        expect(overlays.length).toBe(1);
    });

    // --- hideOverlay ---

    test('hideOverlay removes overlay and re-enables scroll', () => {
        controller.overlay = null;
        controller.showOverlay();

        controller.hideOverlay();

        expect(document.querySelector('.mobile-offline-overlay')).toBeNull();
        expect(controller.overlay).toBeNull();
        expect(document.body.style.overflow).toBe('');
    });

    test('hideOverlay does nothing when no overlay exists', () => {
        controller.overlay = null;
        controller.hideOverlay();
        // Should not throw
        expect(controller.overlay).toBeNull();
    });

    // --- onConnectionStateChanged ---

    test('onConnectionStateChanged shows overlay when offline', () => {
        controller.overlay = null;
        const showSpy = jest.spyOn(controller, 'showOverlay');
        controller.onConnectionStateChanged(false);
        expect(showSpy).toHaveBeenCalled();
    });

    test('onConnectionStateChanged hides overlay when online', () => {
        controller.overlay = null;
        controller.showOverlay();
        const hideSpy = jest.spyOn(controller, 'hideOverlay');
        controller.onConnectionStateChanged(true);
        expect(hideSpy).toHaveBeenCalled();
    });

    // --- onConnect ---

    test('onConnect shows overlay when initially offline', () => {
        MobileControllerBase.setOnlineState(false, false);
        const showSpy = jest.spyOn(controller, 'showOverlay');

        controller.onConnect();

        expect(showSpy).toHaveBeenCalled();
    });

    test('onConnect registers connection status listener', () => {
        const addSpy = jest.spyOn(document, 'addEventListener');
        controller.onConnect();
        expect(addSpy).toHaveBeenCalledWith('connection-status-changed', expect.any(Function));
    });

    // --- onDisconnect ---

    test('onDisconnect removes event listener and hides overlay', () => {
        controller.onConnect();
        const removeSpy = jest.spyOn(document, 'removeEventListener');
        const hideSpy = jest.spyOn(controller, 'hideOverlay');

        controller.onDisconnect();

        expect(removeSpy).toHaveBeenCalledWith('connection-status-changed', expect.any(Function));
        expect(hideSpy).toHaveBeenCalled();
    });

    // --- handleConnectionStatusEvent ---

    test('handleConnectionStatusEvent updates auth card URL', () => {
        controller.handleConnectionStatusEvent({
            detail: { authCardUrl: '/members/42/card' }
        });
        expect(controller.authCardUrlValue).toBe('/members/42/card');
    });
});
