// Controller registers on window.Controllers (no default export)
import '../../../assets/js/controllers/revoke-form-controller.js';
const RevokeFormController = window.Controllers['revoke-form'];

describe('RevokeFormController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <form data-controller="revoke-form"
                  data-revoke-form-url-value="/revoke">
                <input type="hidden" data-revoke-form-target="id" value="">
                <textarea data-revoke-form-target="reason"
                          data-action="input->revoke-form#checkReadyToSubmit"
                          rows="3"></textarea>
                <button type="submit"
                        data-revoke-form-target="submitBtn"
                        class="btn btn-danger">
                    Revoke Access
                </button>
            </form>
        `;

        controller = new RevokeFormController();
        controller.element = document.querySelector('[data-controller="revoke-form"]');

        // Wire up targets
        controller.submitBtnTarget = document.querySelector('[data-revoke-form-target="submitBtn"]');
        controller.reasonTarget = document.querySelector('[data-revoke-form-target="reason"]');
        controller.idTarget = document.querySelector('[data-revoke-form-target="id"]');

        // Wire up values
        controller.urlValue = '/revoke';
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    // --- Instantiation ---

    test('instantiates with correct static targets', () => {
        expect(RevokeFormController.targets).toEqual(
            expect.arrayContaining(['submitBtn', 'reason', 'id'])
        );
    });

    test('instantiates with correct static values', () => {
        expect(RevokeFormController.values).toHaveProperty('url', String);
    });

    test('defines outlet-btn outlet', () => {
        expect(RevokeFormController.outlets).toEqual(
            expect.arrayContaining(['outlet-btn'])
        );
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['revoke-form']).toBe(RevokeFormController);
    });

    // --- connect ---

    test('connect disables submit button initially', () => {
        controller.submitBtnTarget.disabled = false;
        controller.connect();
        expect(controller.submitBtnTarget.disabled).toBe(true);
    });

    // --- checkReadyToSubmit ---

    test('checkReadyToSubmit enables button when reason has content', () => {
        controller.connect();
        controller.reasonTarget.value = 'Violation of policy';
        controller.checkReadyToSubmit();
        expect(controller.submitBtnTarget.disabled).toBe(false);
    });

    test('checkReadyToSubmit keeps button disabled when reason is empty', () => {
        controller.connect();
        controller.reasonTarget.value = '';
        controller.checkReadyToSubmit();
        expect(controller.submitBtnTarget.disabled).toBe(true);
    });

    test('checkReadyToSubmit disables button when reason is cleared', () => {
        controller.connect();
        // First enable
        controller.reasonTarget.value = 'Some reason';
        controller.checkReadyToSubmit();
        expect(controller.submitBtnTarget.disabled).toBe(false);

        // Then clear
        controller.reasonTarget.value = '';
        controller.checkReadyToSubmit();
        expect(controller.submitBtnTarget.disabled).toBe(true);
    });

    // --- setId ---

    test('setId sets hidden input value from event detail', () => {
        const event = { detail: { id: '42' } };
        controller.setId(event);
        expect(controller.idTarget.value).toBe('42');
    });

    test('setId updates value when called multiple times', () => {
        controller.setId({ detail: { id: '10' } });
        expect(controller.idTarget.value).toBe('10');

        controller.setId({ detail: { id: '99' } });
        expect(controller.idTarget.value).toBe('99');
    });

    // --- Outlet communication ---

    test('outletBtnOutletConnected adds listener to outlet', () => {
        const mockOutlet = {
            addListener: jest.fn(),
        };
        const mockElement = document.createElement('button');

        controller.outletBtnOutletConnected(mockOutlet, mockElement);

        expect(mockOutlet.addListener).toHaveBeenCalledWith(expect.any(Function));
    });

    test('outletBtnOutletDisconnected removes listener from outlet', () => {
        const mockOutlet = {
            removeListener: jest.fn(),
        };

        controller.outletBtnOutletDisconnected(mockOutlet);

        expect(mockOutlet.removeListener).toHaveBeenCalledWith(expect.any(Function));
    });

    // --- Integration-style: full workflow ---

    test('full workflow: connect, set id via outlet, type reason, enable submit', () => {
        controller.connect();
        expect(controller.submitBtnTarget.disabled).toBe(true);

        // Simulate outlet setting an ID
        controller.setId({ detail: { id: '123' } });
        expect(controller.idTarget.value).toBe('123');

        // Simulate typing a reason
        controller.reasonTarget.value = 'Member violated code of conduct';
        controller.checkReadyToSubmit();
        expect(controller.submitBtnTarget.disabled).toBe(false);
    });
});
