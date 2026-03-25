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
