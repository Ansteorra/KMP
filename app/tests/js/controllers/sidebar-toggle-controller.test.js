import '../../../assets/js/controllers/sidebar-toggle-controller.js';

describe('responsive sidebar header spacing', () => {
    let controllers, observers, height;
    beforeEach(() => {
        controllers = []; observers = []; height = 52;
        document.body.innerHTML = '<header><button data-controller="sidebar-toggle"></button></header>';
        global.ResizeObserver = class {
            constructor(callback) { this.callback = callback; this.disconnect = jest.fn(); observers.push(this); }
            observe() {}
        };
        jest.spyOn(document.querySelector('header'), 'getBoundingClientRect').mockImplementation(() => ({ height }));
    });
    afterEach(() => {
        controllers.forEach(controller => controller.disconnect());
        delete global.ResizeObserver;
        jest.restoreAllMocks();
    });
    const connect = () => {
        const controller = new window.Controllers['sidebar-toggle']();
        controller.element = document.querySelector('button'); controller.hasIconTarget = false;
        controller.connect(); controllers.push(controller); return controller;
    };

    test('a wrapped header reserves its measured space and obsolete visits cannot overwrite the next visit', () => {
        const first = connect();
        expect(document.documentElement.style.getPropertyValue('--kmp-navbar-height')).toBe('52px');
        height = 104; observers[0].callback();
        expect(document.documentElement.style.getPropertyValue('--kmp-navbar-height')).toBe('104px');
        const second = connect(); first.disconnect();
        height = 148; observers[0].callback();
        expect(document.documentElement.style.getPropertyValue('--kmp-navbar-height')).toBe('104px');
        observers[1].callback();
        expect(document.documentElement.style.getPropertyValue('--kmp-navbar-height')).toBe('148px');
        second.disconnect();
        expect(document.documentElement.style.getPropertyValue('--kmp-navbar-height')).toBe('');
        expect(observers[0].disconnect).toHaveBeenCalled();
        expect(observers[1].disconnect).toHaveBeenCalled();
    });
});
