import MobileControllerBase from '../../../assets/js/controllers/mobile-controller-base.js';
import '../../../assets/js/controllers/member-mobile-card-menu-controller.js';
const MemberMobileCardMenu = window.Controllers['member-mobile-card-menu'];

describe('MemberMobileCardMenuController', () => {
    let controller;
    const menuItemsJson = JSON.stringify([
        { label: 'Auth Card', url: '/card', icon: 'bi-person-vcard', color: 'primary', order: 1, badge: 0 },
        { label: 'My RSVPs', url: '/rsvps', icon: 'bi-calendar', color: 'success', order: 2, badge: 3 },
        { label: 'Settings', url: '/settings', icon: 'bi-gear', color: 'secondary', order: 3, badge: 0 }
    ]);

    beforeEach(() => {
        MobileControllerBase.setOnlineState(true, false);
        MobileControllerBase.connectionListeners = new Set();

        document.body.innerHTML = `
            <div data-controller="member-mobile-card-menu"
                 data-member-mobile-card-menu-menu-items-value='${menuItemsJson}'>
                <button data-member-mobile-card-menu-target="fab">Menu</button>
                <div data-member-mobile-card-menu-target="menu" hidden></div>
            </div>
        `;

        controller = new MemberMobileCardMenu();
        controller.element = document.querySelector('[data-controller="member-mobile-card-menu"]');
        controller.fabTarget = document.querySelector('[data-member-mobile-card-menu-target="fab"]');
        controller.hasFabTarget = true;
        controller.menuTarget = document.querySelector('[data-member-mobile-card-menu-target="menu"]');
        controller.hasMenuTarget = true;
        controller.menuItemTargets = [];
        controller.hasMenuItemTarget = false;
        controller.badgeTargets = [];
        controller.menuItemsValue = menuItemsJson;
        controller._boundHandlers = new Map();
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    // --- Static properties ---

    test('has correct static targets', () => {
        expect(MemberMobileCardMenu.targets).toEqual(expect.arrayContaining(['fab', 'menu', 'menuItem', 'badge']));
    });

    test('has correct static values', () => {
        expect(MemberMobileCardMenu.values).toHaveProperty('menuItems', String);
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['member-mobile-card-menu']).toBe(MemberMobileCardMenu);
    });

    // --- initialize ---

    test('initialize sets default state', () => {
        controller.initialize();
        expect(controller.menuOpen).toBe(false);
        expect(controller.items).toEqual([]);
        expect(controller.authCardUrl).toBeNull();
    });

    // --- loadMenuItems ---

    test('loadMenuItems parses JSON and sorts by order', () => {
        controller.loadMenuItems();
        expect(controller.items.length).toBe(3);
        expect(controller.items[0].label).toBe('Auth Card');
        expect(controller.items[2].label).toBe('Settings');
    });

    test('loadMenuItems handles empty value', () => {
        controller.menuItemsValue = '';
        controller.items = [];
        controller.loadMenuItems();
        expect(controller.items).toEqual([]);
    });

    test('loadMenuItems handles invalid JSON', () => {
        controller.menuItemsValue = '{invalid';
        controller.items = [];
        controller.loadMenuItems();
        expect(controller.items).toEqual([]);
    });

    // --- renderMenu ---

    test('renderMenu creates menu item elements', () => {
        controller.items = JSON.parse(menuItemsJson);
        controller.renderMenu();

        const items = controller.menuTarget.querySelectorAll('.mobile-menu-item');
        expect(items.length).toBe(3);
    });

    test('renderMenu does nothing when no menu target', () => {
        controller.hasMenuTarget = false;
        controller.items = JSON.parse(menuItemsJson);
        controller.renderMenu();
        // Should not throw
    });

    test('renderMenu does nothing when no items', () => {
        controller.items = [];
        controller.renderMenu();
        expect(controller.menuTarget.innerHTML).toBe('');
    });

    // --- createMenuItem ---

    test('createMenuItem creates link with icon and label', () => {
        const item = { label: 'Test', url: '/test', icon: 'bi-star', color: 'warning', badge: 0 };
        const el = controller.createMenuItem(item);

        expect(el.tagName).toBe('A');
        expect(el.href).toContain('/test');
        expect(el.className).toContain('btn-warning');
        expect(el.getAttribute('aria-label')).toBe('Test');
        expect(el.querySelector('.bi-star')).toBeTruthy();
        expect(el.textContent).toContain('Test');
    });

    test('createMenuItem adds badge when badge > 0', () => {
        const item = { label: 'Test', url: '/test', icon: 'bi-star', color: 'primary', badge: 5 };
        const el = controller.createMenuItem(item);

        const badge = el.querySelector('.badge');
        expect(badge).toBeTruthy();
        expect(badge.textContent).toBe('5');
    });

    test('createMenuItem does not add badge when badge is 0', () => {
        const item = { label: 'Test', url: '/test', icon: 'bi-star', color: 'primary', badge: 0 };
        const el = controller.createMenuItem(item);

        expect(el.querySelector('.badge')).toBeNull();
    });

    // --- toggleMenu / openMenu / closeMenu ---

    test('toggleMenu opens closed menu', () => {
        controller.menuOpen = false;
        const openSpy = jest.spyOn(controller, 'openMenu');
        controller.toggleMenu({ preventDefault: jest.fn() });
        expect(openSpy).toHaveBeenCalled();
    });

    test('toggleMenu closes open menu', () => {
        controller.menuOpen = true;
        const closeSpy = jest.spyOn(controller, 'closeMenu');
        controller.toggleMenu({ preventDefault: jest.fn() });
        expect(closeSpy).toHaveBeenCalled();
    });

    test('openMenu shows menu and updates FAB', () => {
        jest.useFakeTimers();
        controller.openMenu();

        expect(controller.menuOpen).toBe(true);
        expect(controller.menuTarget.hidden).toBe(false);
        expect(controller.menuTarget.classList.contains('menu-opening')).toBe(true);
        expect(controller.fabTarget.classList.contains('menu-active')).toBe(true);

        jest.advanceTimersByTime(300);
        expect(controller.menuTarget.classList.contains('menu-opening')).toBe(false);
        jest.useRealTimers();
    });

    test('closeMenu hides menu after animation', () => {
        jest.useFakeTimers();
        controller.menuOpen = true;
        controller.menuTarget.hidden = false;

        controller.closeMenu();

        expect(controller.menuOpen).toBe(false);
        expect(controller.menuTarget.classList.contains('menu-closing')).toBe(true);
        expect(controller.fabTarget.classList.contains('menu-active')).toBe(false);

        jest.advanceTimersByTime(300);
        expect(controller.menuTarget.hidden).toBe(true);
        expect(controller.menuTarget.classList.contains('menu-closing')).toBe(false);
        jest.useRealTimers();
    });

    test('openMenu does nothing when no menu target', () => {
        controller.hasMenuTarget = false;
        controller.openMenu();
        // Should not throw
    });

    // --- handleOutsideClick ---

    test('handleOutsideClick closes menu when clicking outside', () => {
        controller.menuOpen = true;
        const closeSpy = jest.spyOn(controller, 'closeMenu').mockImplementation(() => {});
        controller.handleOutsideClick({ target: document.body });
        expect(closeSpy).toHaveBeenCalled();
    });

    test('handleOutsideClick does nothing when menu is closed', () => {
        controller.menuOpen = false;
        const closeSpy = jest.spyOn(controller, 'closeMenu');
        controller.handleOutsideClick({ target: document.body });
        expect(closeSpy).not.toHaveBeenCalled();
    });

    test('handleOutsideClick does nothing when clicking inside', () => {
        controller.menuOpen = true;
        const closeSpy = jest.spyOn(controller, 'closeMenu');
        controller.handleOutsideClick({ target: controller.element });
        expect(closeSpy).not.toHaveBeenCalled();
    });

    // --- handleConnectionStatusEvent ---

    test('handleConnectionStatusEvent updates authCardUrl', () => {
        const spy = jest.spyOn(controller, 'updateOfflineState').mockImplementation(() => {});
        controller.handleConnectionStatusEvent({ detail: { authCardUrl: '/card/42' } });
        expect(controller.authCardUrl).toBe('/card/42');
    });

    // --- updateOfflineState ---

    test('updateOfflineState disables online-only items when offline', () => {
        // Render menu items first
        controller.items = JSON.parse(menuItemsJson);
        controller.renderMenu();
        controller.menuItemTargets = Array.from(controller.menuTarget.querySelectorAll('.mobile-menu-item'));
        controller.hasMenuItemTarget = true;

        MobileControllerBase.setOnlineState(false, false);
        controller.updateOfflineState();

        controller.menuItemTargets.forEach(item => {
            const label = item.dataset.itemLabel;
            if (label === 'Auth Card' || label === 'My RSVPs') {
                expect(item.classList.contains('disabled')).toBe(false);
            } else {
                expect(item.classList.contains('disabled')).toBe(true);
            }
        });
    });

    test('updateOfflineState enables all items when online', () => {
        controller.items = JSON.parse(menuItemsJson);
        controller.renderMenu();
        controller.menuItemTargets = Array.from(controller.menuTarget.querySelectorAll('.mobile-menu-item'));
        controller.hasMenuItemTarget = true;

        MobileControllerBase.setOnlineState(true, false);
        controller.updateOfflineState();

        controller.menuItemTargets.forEach(item => {
            expect(item.classList.contains('disabled')).toBe(false);
        });
    });

    // --- onConnect / onDisconnect ---

    test('onConnect registers event listeners', () => {
        const addSpy = jest.spyOn(document, 'addEventListener');
        controller.onConnect();
        const eventNames = addSpy.mock.calls.map(c => c[0]);
        expect(eventNames).toContain('click');
        expect(eventNames).toContain('touchstart');
        expect(eventNames).toContain('connection-status-changed');
    });

    test('onDisconnect removes event listeners', () => {
        controller.onConnect();
        const removeSpy = jest.spyOn(document, 'removeEventListener');
        controller.onDisconnect();
        const eventNames = removeSpy.mock.calls.map(c => c[0]);
        expect(eventNames).toContain('click');
        expect(eventNames).toContain('touchstart');
        expect(eventNames).toContain('connection-status-changed');
    });
});
