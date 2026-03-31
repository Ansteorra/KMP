import '../../../assets/js/controllers/outlet-button-controller.js';
const OutletButton = window.Controllers['outlet-btn'];

describe('OutletButtonController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <button data-controller="outlet-btn"
                    data-outlet-btn-require-data-value="true">
                Submit
            </button>
        `;

        controller = new OutletButton();
        controller.element = document.querySelector('[data-controller="outlet-btn"]');
        controller.btnDataValue = {};
        controller.requireDataValue = true;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['outlet-btn']).toBe(OutletButton);
    });

    test('has correct static values', () => {
        expect(OutletButton.values).toHaveProperty('btnData', Object);
        expect(OutletButton.values).toHaveProperty('requireData', Boolean);
    });

    test('btnDataValueChanged disables button when data required and empty', () => {
        controller.btnDataValue = {};
        controller.btnDataValueChanged();
        expect(controller.element.disabled).toBe(true);
    });

    test('btnDataValueChanged enables button when data is present', () => {
        controller.btnDataValue = { memberId: 123 };
        controller.btnDataValueChanged();
        expect(controller.element.disabled).toBe(false);
    });

    test('btnDataValueChanged enables button when data not required', () => {
        controller.requireDataValue = false;
        controller.btnDataValue = {};
        controller.btnDataValueChanged();
        expect(controller.element.disabled).toBe(false);
    });

    test('btnDataValueChanged handles null by resetting to empty object', () => {
        controller.btnDataValue = null;
        controller.btnDataValueChanged();
        expect(controller.btnDataValue).toEqual({});
    });

    test('addBtnData sets btnDataValue', () => {
        controller.addBtnData({ action: 'assign', id: 42 });
        expect(controller.btnDataValue).toEqual({ action: 'assign', id: 42 });
    });

    test('fireNotice dispatches event with button data', () => {
        controller.dispatch = jest.fn();
        controller.btnDataValue = { memberId: 99 };
        controller.fireNotice({ target: controller.element });
        expect(controller.dispatch).toHaveBeenCalledWith(
            'outlet-button-clicked',
            { detail: { memberId: 99 } }
        );
    });

    test('addListener registers event handler', () => {
        const addSpy = jest.spyOn(controller.element, 'addEventListener');
        const callback = jest.fn();
        controller.addListener(callback);
        expect(addSpy).toHaveBeenCalledWith('outlet-btn:outlet-button-clicked', callback);
    });

    test('removeListener unregisters event handler', () => {
        const removeSpy = jest.spyOn(controller.element, 'removeEventListener');
        const callback = jest.fn();
        controller.removeListener(callback);
        expect(removeSpy).toHaveBeenCalledWith('outlet-btn:outlet-button-clicked', callback);
    });
});
