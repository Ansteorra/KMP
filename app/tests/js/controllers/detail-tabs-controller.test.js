import '../../../assets/js/controllers/detail-tabs-controller.js';
const DetailTabsController = window.Controllers['detail-tabs'];

describe('DetailTabsController', () => {
    let controller;

    beforeEach(() => {
        // Reset KMP_utils mock
        window.KMP_utils.urlParam = jest.fn().mockReturnValue(null);
        window.scrollTo = jest.fn();
        window.history.pushState = jest.fn();

        document.body.innerHTML = `
            <div data-controller="detail-tabs" data-detail-tabs-update-url-value="true">
                <nav>
                    <button data-detail-tabs-target="tabBtn" id="nav-info-tab" data-tab-order="10" style="order: 10;">Info</button>
                    <button data-detail-tabs-target="tabBtn" id="nav-history-tab" data-tab-order="20" style="order: 20;">History</button>
                    <button data-detail-tabs-target="tabBtn" id="nav-awards-tab" data-tab-order="5" style="order: 5;">Awards</button>
                </nav>
            </div>
        `;

        controller = new DetailTabsController();
        controller.element = document.querySelector('[data-controller="detail-tabs"]');
        controller.tabBtnTargets = Array.from(document.querySelectorAll('[data-detail-tabs-target="tabBtn"]'));
        controller.tabContentTargets = [];
        controller.updateUrlValue = true;
        controller.foundFirst = false;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    // --- Static properties ---

    test('has correct static targets', () => {
        expect(DetailTabsController.targets).toEqual(expect.arrayContaining(['tabBtn', 'tabContent']));
    });

    test('has correct static values', () => {
        expect(DetailTabsController.values).toHaveProperty('updateUrl');
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['detail-tabs']).toBe(DetailTabsController);
    });

    // --- getFirstTabByOrder ---

    test('getFirstTabByOrder returns tab with lowest order', () => {
        const firstTab = controller.getFirstTabByOrder();
        expect(firstTab.id).toBe('nav-awards-tab');
    });

    test('getFirstTabByOrder returns null when no tabs', () => {
        controller.tabBtnTargets = [];
        expect(controller.getFirstTabByOrder()).toBeNull();
    });

    test('getFirstTabByOrder defaults to 999 for tabs without order', () => {
        document.body.innerHTML = `
            <div data-controller="detail-tabs">
                <button data-detail-tabs-target="tabBtn" id="nav-a-tab">A</button>
                <button data-detail-tabs-target="tabBtn" id="nav-b-tab" data-tab-order="5">B</button>
            </div>
        `;
        controller.tabBtnTargets = Array.from(document.querySelectorAll('[data-detail-tabs-target="tabBtn"]'));

        const firstTab = controller.getFirstTabByOrder();
        expect(firstTab.id).toBe('nav-b-tab');
    });

    // --- tabBtnTargetConnected ---

    test('tabBtnTargetConnected activates matching URL tab', () => {
        window.KMP_utils.urlParam.mockReturnValue('history');
        const historyTab = document.getElementById('nav-history-tab');
        historyTab.click = jest.fn();

        controller.tabBtnTargetConnected(historyTab);

        expect(historyTab.click).toHaveBeenCalled();
        expect(controller.foundFirst).toBe(true);
        expect(window.scrollTo).toHaveBeenCalledWith(0, 0);
    });

    test('tabBtnTargetConnected activates first tab by order when no URL param', () => {
        const awardsTab = document.getElementById('nav-awards-tab');
        awardsTab.click = jest.fn();
        const infoTab = document.getElementById('nav-info-tab');
        infoTab.click = jest.fn();

        controller.tabBtnTargetConnected(infoTab);

        expect(awardsTab.click).toHaveBeenCalled();
        expect(controller.foundFirst).toBe(true);
    });

    test('tabBtnTargetConnected does not activate if already found first', () => {
        controller.foundFirst = true;
        const tab = document.getElementById('nav-info-tab');
        tab.click = jest.fn();

        controller.tabBtnTargetConnected(tab);

        expect(tab.click).not.toHaveBeenCalled();
    });

    test('tabBtnTargetConnected adds click listener', () => {
        const tab = document.getElementById('nav-info-tab');
        const addSpy = jest.spyOn(tab, 'addEventListener');

        controller.foundFirst = true; // skip activation
        controller.tabBtnTargetConnected(tab);

        expect(addSpy).toHaveBeenCalledWith('click', expect.any(Function));
    });

    // --- tabBtnClicked ---

    test('tabBtnClicked pushes tab to URL for non-first tab', () => {
        const event = { target: document.getElementById('nav-history-tab') };
        controller.tabBtnClicked(event);
        expect(window.history.pushState).toHaveBeenCalledWith({}, '', '?tab=history');
    });

    test('tabBtnClicked pushes pathname for first tab when URL has tab', () => {
        window.KMP_utils.urlParam.mockReturnValue('history');
        // First tab by order is awards (order 5)
        const event = { target: document.getElementById('nav-awards-tab') };
        controller.tabBtnClicked(event);
        expect(window.history.pushState).toHaveBeenCalledWith({}, '', window.location.pathname);
    });

    test('tabBtnClicked does not push state when updateUrl is false', () => {
        controller.updateUrlValue = false;
        const event = { target: document.getElementById('nav-history-tab') };
        controller.tabBtnClicked(event);
        expect(window.history.pushState).not.toHaveBeenCalled();
    });

    test('tabBtnClicked reloads turbo frame if it exists and was loaded', () => {
        const frame = document.createElement('turbo-frame');
        frame.id = 'history-frame';
        frame.loaded = true;
        frame.reload = jest.fn();
        document.body.appendChild(frame);

        const event = { target: document.getElementById('nav-history-tab') };
        controller.tabBtnClicked(event);

        expect(frame.reload).toHaveBeenCalled();
    });

    // --- tabBtnTargetDisconnected ---

    test('tabBtnTargetDisconnected removes click listener', () => {
        const tab = document.getElementById('nav-info-tab');
        const removeSpy = jest.spyOn(tab, 'removeEventListener');

        controller.tabBtnTargetDisconnected(tab);

        expect(removeSpy).toHaveBeenCalledWith('click', expect.any(Function));
    });
});
