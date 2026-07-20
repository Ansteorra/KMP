// Mock rsvp-cache-service before import
jest.mock('../../../assets/js/services/rsvp-cache-service.js', () => ({
    __esModule: true,
    default: {
        init: jest.fn().mockResolvedValue(undefined),
        updateCachedRsvp: jest.fn().mockResolvedValue(undefined),
        removeCachedRsvp: jest.fn().mockResolvedValue(undefined)
    }
}));

import MobileControllerBase from '../../../assets/js/controllers/mobile-controller-base.js';
import '../../../assets/js/controllers/my-rsvps-controller.js';
const MyRsvpsController = window.Controllers['my-rsvps'];

describe('MyRsvpsController', () => {
    let controller;

    beforeEach(() => {
        MobileControllerBase.setOnlineState(true, false);
        MobileControllerBase.connectionListeners = new Set();

        // Mock navigator.onLine
        Object.defineProperty(navigator, 'onLine', { value: true, configurable: true });

        document.body.innerHTML = `
            <div data-controller="my-rsvps">
                <div data-my-rsvps-target="modal" id="rsvpModal">
                    <div data-my-rsvps-target="modalBody"></div>
                </div>
                <div data-my-rsvps-target="upcomingList">
                    <div class="mobile-event-card attending" data-end-date="2099-12-31">
                        <span class="bi-check-circle-fill text-success"></span>
                        <div class="mobile-event-actions-row"><button class="online-only-btn">Edit</button></div>
                    </div>
                    <div class="mobile-event-card attending" data-end-date="2020-01-01">
                        <span class="bi-check-circle-fill text-success"></span>
                        <div class="mobile-event-actions-row"><button class="online-only-btn">Edit</button></div>
                    </div>
                </div>
                <div data-my-rsvps-target="pastList"></div>
                <div data-my-rsvps-target="pastEmptyState">No past events</div>
                <span data-my-rsvps-target="upcomingCount">2</span>
                <span data-my-rsvps-target="pastCount" hidden>0</span>
                <button class="online-only-btn">Browse</button>
            </div>
        `;

        controller = new MyRsvpsController();
        controller.element = document.querySelector('[data-controller="my-rsvps"]');
        controller.modalTarget = document.querySelector('[data-my-rsvps-target="modal"]');
        controller.hasModalTarget = true;
        controller.modalBodyTarget = document.querySelector('[data-my-rsvps-target="modalBody"]');
        controller.hasModalBodyTarget = true;
        controller.upcomingListTarget = document.querySelector('[data-my-rsvps-target="upcomingList"]');
        controller.hasUpcomingListTarget = true;
        controller.pastListTarget = document.querySelector('[data-my-rsvps-target="pastList"]');
        controller.hasPastListTarget = true;
        controller.pastEmptyStateTarget = document.querySelector('[data-my-rsvps-target="pastEmptyState"]');
        controller.hasPastEmptyStateTarget = true;
        controller.upcomingCountTarget = document.querySelector('[data-my-rsvps-target="upcomingCount"]');
        controller.hasUpcomingCountTarget = true;
        controller.pastCountTarget = document.querySelector('[data-my-rsvps-target="pastCount"]');
        controller.hasPastCountTarget = true;
        controller.actionButtonsTargets = [];
        controller._boundHandlers = new Map();
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
        if (global.fetch) delete global.fetch;
    });

    // --- Static properties ---

    test('has correct static targets', () => {
        expect(MyRsvpsController.targets).toEqual(expect.arrayContaining([
            'modal', 'modalBody', 'actionButtons', 'upcomingList', 'pastList',
            'pastEmptyState', 'upcomingCount', 'pastCount'
        ]));
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['my-rsvps']).toBe(MyRsvpsController);
    });

    // --- filterPastEvents ---

    test('filterPastEvents moves past events to past tab', () => {
        controller.filterPastEvents();

        const pastCards = controller.pastListTarget.querySelectorAll('.mobile-event-card');
        const upcomingCards = controller.upcomingListTarget.querySelectorAll('.mobile-event-card:not(.past)');
        expect(pastCards.length).toBe(1);
        expect(upcomingCards.length).toBe(1);
    });

    test('filterPastEvents does nothing when no upcoming list target', () => {
        controller.hasUpcomingListTarget = false;
        controller.filterPastEvents();
        // Should not throw
    });

    // --- transformCardForPast ---

    test('transformCardForPast updates card classes and removes actions', () => {
        const card = controller.upcomingListTarget.querySelector('.mobile-event-card');
        controller.transformCardForPast(card);

        expect(card.classList.contains('attending')).toBe(false);
        expect(card.classList.contains('past')).toBe(true);
        expect(card.querySelector('.mobile-event-actions-row')).toBeNull();

        const icon = card.querySelector('.bi-check-circle');
        expect(icon).toBeTruthy();
        expect(icon.classList.contains('text-muted')).toBe(true);
    });

    // --- updateBadgeCounts ---

    test('updateBadgeCounts updates count text content', () => {
        // Move past event first
        controller.filterPastEvents();
        controller.updateBadgeCounts();

        expect(controller.upcomingCountTarget.textContent).toBe('1');
        expect(controller.pastCountTarget.textContent).toBe('1');
    });

    // --- updateOnlineButtons ---

    test('updateOnlineButtons enables buttons when online', () => {
        controller.updateOnlineButtons();

        const btns = controller.element.querySelectorAll('.online-only-btn');
        btns.forEach(btn => {
            expect(btn.classList.contains('disabled')).toBe(false);
            expect(btn.style.opacity).toBe('1');
        });
    });

    test('updateOnlineButtons disables buttons when offline', () => {
        Object.defineProperty(navigator, 'onLine', { value: false, configurable: true });

        controller.updateOnlineButtons();

        const btns = controller.element.querySelectorAll('.online-only-btn');
        btns.forEach(btn => {
            expect(btn.classList.contains('disabled')).toBe(true);
            expect(btn.style.opacity).toBe('0.5');
            expect(btn.getAttribute('aria-disabled')).toBe('true');
        });
    });

    // --- onConnectionStateChanged ---

    test('onConnectionStateChanged calls updateOnlineButtons', () => {
        const spy = jest.spyOn(controller, 'updateOnlineButtons');
        controller.onConnectionStateChanged(true);
        expect(spy).toHaveBeenCalled();
    });

    // --- editRsvp ---

    test('editRsvp prevents editing when offline', async () => {
        Object.defineProperty(navigator, 'onLine', { value: false, configurable: true });

        await controller.editRsvp({
            currentTarget: { dataset: { gatheringId: '1', attendanceId: '2' } }
        });

        expect(window.KMP_accessibility.announce).toHaveBeenCalledWith(
            'You need to be online to edit RSVPs.',
            { assertive: true }
        );
    });

    test('editRsvp loads modal content on success', async () => {
        global.fetch = jest.fn().mockResolvedValue({
            ok: true,
            text: () => Promise.resolve('<form id="attendanceModalForm"></form>')
        });

        // Mock bootstrap.Modal
        const mockModal = { show: jest.fn(), hide: jest.fn(), dispose: jest.fn() };
        window.bootstrap.Modal = jest.fn().mockImplementation(() => mockModal);

        await controller.editRsvp({
            currentTarget: { dataset: { gatheringId: '10', attendanceId: '20' } }
        });

        expect(controller.currentGatheringId).toBe('10');
        expect(global.fetch).toHaveBeenCalledWith(
            '/gatherings/attendance-modal/10?attendance_id=20',
            expect.objectContaining({ headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        );
        expect(mockModal.show).toHaveBeenCalled();
    });

    test('editRsvp shows error on fetch failure', async () => {
        global.fetch = jest.fn().mockResolvedValue({
            ok: false,
            status: 500
        });

        const mockModal = { show: jest.fn(), hide: jest.fn(), dispose: jest.fn() };
        window.bootstrap.Modal = jest.fn().mockImplementation(() => mockModal);

        await controller.editRsvp({
            currentTarget: { dataset: { gatheringId: '10', attendanceId: '20' } }
        });

        expect(controller.modalBodyTarget.innerHTML).toContain('Failed to load');
    });

    // --- onModalHidden ---

    test('onModalHidden resets modal body and gatheringId', () => {
        controller.currentGatheringId = '10';
        controller.onModalHidden();

        expect(controller.currentGatheringId).toBeNull();
        expect(controller.modalBodyTarget.innerHTML).toContain('spinner-border');
    });

    // --- onDisconnect ---

    test('onDisconnect disposes modal', () => {
        const mockModal = { dispose: jest.fn() };
        controller.modal = mockModal;

        controller.onDisconnect();

        expect(mockModal.dispose).toHaveBeenCalled();
        expect(controller.modal).toBeNull();
    });
});
