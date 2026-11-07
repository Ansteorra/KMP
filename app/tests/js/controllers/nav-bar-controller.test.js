require('../../../assets/js/controllers/nav-bar-controller.js');
const NavBarController = window.Controllers['nav-bar'];

describe('NavBarController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <nav data-controller="nav-bar">
                <button data-nav-bar-target="navHeader"
                        data-expand-url="/api/nav/expand/menu1"
                        data-collapse-url="/api/nav/collapse/menu1"
                        aria-expanded="false">Menu 1</button>
                <button data-nav-bar-target="navHeader"
                        data-expand-url="/api/nav/expand/menu2"
                        data-collapse-url="/api/nav/collapse/menu2"
                        aria-expanded="true">Menu 2</button>
            </nav>
        `;

        controller = new NavBarController();
        controller.element = document.querySelector('[data-controller="nav-bar"]');
        controller.navHeaderTargets = Array.from(document.querySelectorAll('[data-nav-bar-target="navHeader"]'));
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
        if (global.fetch) {
            delete global.fetch;
        }
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['nav-bar']).toBe(NavBarController);
    });

    test('has correct static targets', () => {
        expect(NavBarController.targets).toEqual(['navHeader']);
    });

    test('optionsForFetch returns correct headers', () => {
        const options = controller.optionsForFetch();
        expect(options.headers['X-Requested-With']).toBe('XMLHttpRequest');
        expect(options.headers['Accept']).toBe('application/json');
    });

    test('navHeaderClicked fetches expand URL when expanded', () => {
        global.fetch = jest.fn(() => Promise.resolve({ ok: true }));
        const btn = controller.navHeaderTargets[1]; // aria-expanded="true"
        controller.navHeaderClicked({ target: btn });
        expect(global.fetch).toHaveBeenCalledWith(
            '/api/nav/expand/menu2',
            expect.any(Object)
        );
    });

    test('navHeaderClicked fetches collapse URL when collapsed', () => {
        global.fetch = jest.fn(() => Promise.resolve({ ok: true }));
        const btn = controller.navHeaderTargets[0]; // aria-expanded="false"
        controller.navHeaderClicked({ target: btn });
        expect(global.fetch).toHaveBeenCalledWith(
            '/api/nav/collapse/menu1',
            expect.any(Object)
        );
    });

    test('a preference write can finish after departure and preserves the local menu state', async () => {
        let finish;
        global.fetch = jest.fn(() => new Promise(resolve => { finish = resolve; }));
        const button = controller.navHeaderTargets[1];
        const saved = controller.navHeaderClicked({ currentTarget: button });
        expect(global.fetch).toHaveBeenCalledWith('/api/nav/expand/menu2', expect.objectContaining({
            keepalive: true, credentials: 'same-origin', cache: 'no-store',
        }));
        window.dispatchEvent(new Event('pagehide'));
        controller.navHeaderTargetDisconnected(button);
        finish({ ok: true });
        await expect(saved).resolves.toBeUndefined();
        expect(button.getAttribute('aria-expanded')).toBe('true');
    });

    test.each(['network', 'server'])('handles %s preference failures without rejecting the click', async (failure) => {
        const warning = jest.spyOn(console, 'warn').mockImplementation(() => {});
        global.fetch = failure === 'network'
            ? jest.fn().mockRejectedValue(new TypeError('Load failed'))
            : jest.fn().mockResolvedValue({ ok: false, status: 503 });
        const button = controller.navHeaderTargets[1];
        await expect(controller.navHeaderClicked({ currentTarget: button })).resolves.toBeUndefined();
        expect(button.getAttribute('aria-expanded')).toBe('true');
        expect(warning).toHaveBeenCalledWith('Navigation preference could not be saved.');
    });

    test('navHeaderTargetConnected adds click listener', () => {
        const btn = document.createElement('button');
        const addSpy = jest.spyOn(btn, 'addEventListener');
        controller.navHeaderTargetConnected(btn);
        expect(addSpy).toHaveBeenCalledWith('click', expect.any(Function));
    });

    test('navHeaderTargetDisconnected removes click listener', () => {
        const btn = document.createElement('button');
        const removeSpy = jest.spyOn(btn, 'removeEventListener');
        controller.navHeaderTargetDisconnected(btn);
        expect(removeSpy).toHaveBeenCalledWith('click', expect.any(Function));
    });
});
