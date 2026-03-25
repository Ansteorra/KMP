// Controller registers on window.Controllers (no default export)
import '../../../plugins/Activities/assets/js/controllers/approve-and-assign-auth-controller.js';
const ApproveAndAssignAuthController = window.Controllers['activities-approve-and-assign-auth'];

describe('ActivitiesApproveAndAssignAuthorization', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <form data-controller="activities-approve-and-assign-auth"
                  data-activities-approve-and-assign-auth-url-value="/activities/approvers"
                  data-activities-approve-and-assign-auth-approval-id-value="0">
                <input type="hidden" data-activities-approve-and-assign-auth-target="approvers" value="">
                <button type="submit"
                        data-activities-approve-and-assign-auth-target="submitBtn"
                        disabled>Approve</button>
                <input type="hidden" data-activities-approve-and-assign-auth-target="id" value="">
            </form>
        `;

        controller = new ApproveAndAssignAuthController();
        controller.element = document.querySelector('[data-controller="activities-approve-and-assign-auth"]');

        // Wire up targets
        controller.approversTarget = document.querySelector('[data-activities-approve-and-assign-auth-target="approvers"]');
        controller.submitBtnTarget = document.querySelector('[data-activities-approve-and-assign-auth-target="submitBtn"]');
        controller.idTarget = document.querySelector('[data-activities-approve-and-assign-auth-target="id"]');

        // Wire up values
        controller.urlValue = '/activities/approvers';
        controller.approvalIdValue = 0;

        // Wire up has* checks
        controller.hasApproversTarget = true;
        controller.hasApprovalIdValue = false;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
        delete global.fetch;
    });

    // --- Instantiation ---

    test('instantiates with correct static targets', () => {
        expect(ApproveAndAssignAuthController.targets).toEqual(
            expect.arrayContaining(['approvers', 'submitBtn', 'id'])
        );
    });

    test('instantiates with correct static values', () => {
        expect(ApproveAndAssignAuthController.values).toHaveProperty('url', String);
        expect(ApproveAndAssignAuthController.values).toHaveProperty('approvalId', Number);
    });

    test('defines outlet-btn outlet', () => {
        expect(ApproveAndAssignAuthController.outlets).toEqual(
            expect.arrayContaining(['outlet-btn'])
        );
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['activities-approve-and-assign-auth']).toBe(ApproveAndAssignAuthController);
    });

    // --- connect ---

    test('connect fetches approvers when approvalId is pre-set', () => {
        controller.hasApprovalIdValue = true;
        controller.approvalIdValue = 42;

        global.fetch = jest.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve([{ id: 1, sca_name: 'Sir Test' }])
        }));

        controller.connect();

        expect(controller.idTarget.value).toBe('42');
        expect(global.fetch).toHaveBeenCalled();
    });

    test('connect does nothing when approvalId is 0', () => {
        controller.hasApprovalIdValue = true;
        controller.approvalIdValue = 0;
        global.fetch = jest.fn();

        controller.connect();

        expect(global.fetch).not.toHaveBeenCalled();
    });

    test('connect does nothing when hasApprovalIdValue is false', () => {
        controller.hasApprovalIdValue = false;
        global.fetch = jest.fn();

        controller.connect();

        expect(global.fetch).not.toHaveBeenCalled();
    });

    // --- setId ---

    test('setId sets id value and calls getApprovers', () => {
        const spy = jest.spyOn(controller, 'getApprovers').mockImplementation(() => {});
        controller.setId({ detail: { id: '99' } });

        expect(controller.idTarget.value).toBe('99');
        expect(spy).toHaveBeenCalled();
    });

    // --- getApprovers ---

    test('getApprovers fetches and populates approver list', async () => {
        global.fetch = jest.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve([
                { id: 1, sca_name: 'Sir Alpha' },
                { id: 2, sca_name: 'Lady Beta' },
            ])
        }));

        controller.idTarget.value = '10';
        controller.getApprovers();
        await new Promise(r => setTimeout(r, 0));

        expect(global.fetch).toHaveBeenCalledWith(
            '/activities/approvers/10',
            expect.any(Object)
        );
        expect(controller.approversTarget.options.length).toBe(2);
        expect(controller.submitBtnTarget.disabled).toBe(true);
        expect(controller.approversTarget.disabled).toBe(false);
    });

    test('getApprovers auto-selects and enables submit when single approver', async () => {
        global.fetch = jest.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve([
                { id: 5, sca_name: 'Sir Only' },
            ])
        }));

        controller.idTarget.value = '10';
        controller.getApprovers();
        await new Promise(r => setTimeout(r, 0));

        expect(controller.approversTarget.value).toBe('5');
        expect(controller.submitBtnTarget.disabled).toBe(false);
    });

    test('getApprovers does nothing without approvers target', () => {
        controller.hasApproversTarget = false;
        global.fetch = jest.fn();
        controller.getApprovers();
        expect(global.fetch).not.toHaveBeenCalled();
    });

    // --- optionsForFetch ---

    test('optionsForFetch returns correct headers', () => {
        const opts = controller.optionsForFetch();
        expect(opts.headers['X-Requested-With']).toBe('XMLHttpRequest');
        expect(opts.headers['Accept']).toBe('application/json');
    });

    // --- checkReadyToSubmit ---

    test('checkReadyToSubmit enables submit for valid approver', () => {
        controller.approversTarget.value = '42';
        controller.checkReadyToSubmit();
        expect(controller.submitBtnTarget.disabled).toBe(false);
    });

    test('checkReadyToSubmit disables submit for zero', () => {
        controller.approversTarget.value = '0';
        controller.checkReadyToSubmit();
        expect(controller.submitBtnTarget.disabled).toBe(true);
    });

    test('checkReadyToSubmit disables submit for non-numeric', () => {
        controller.approversTarget.value = 'invalid';
        controller.checkReadyToSubmit();
        expect(controller.submitBtnTarget.disabled).toBe(true);
    });

    test('checkReadyToSubmit disables submit for empty value', () => {
        controller.approversTarget.value = '';
        controller.checkReadyToSubmit();
        expect(controller.submitBtnTarget.disabled).toBe(true);
    });

    // --- submitBtnTargetConnected ---

    test('submitBtnTargetConnected disables submit button', () => {
        controller.submitBtnTarget.disabled = false;
        controller.submitBtnTargetConnected();
        expect(controller.submitBtnTarget.disabled).toBe(true);
    });

    // --- outlet communication ---

    test('outletBtnOutletConnected adds listener', () => {
        const mockOutlet = { addListener: jest.fn() };
        controller.outletBtnOutletConnected(mockOutlet, document.createElement('button'));
        expect(mockOutlet.addListener).toHaveBeenCalledWith(expect.any(Function));
    });

    test('outletBtnOutletDisconnected removes listener', () => {
        const mockOutlet = { removeListener: jest.fn() };
        controller.outletBtnOutletDisconnected(mockOutlet);
        expect(mockOutlet.removeListener).toHaveBeenCalledWith(expect.any(Function));
    });

    // --- Integration workflow ---

    test('full workflow: setId, fetch approvers, select, enable submit', async () => {
        global.fetch = jest.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve([
                { id: 1, sca_name: 'Sir Alpha' },
                { id: 2, sca_name: 'Lady Beta' },
            ])
        }));

        controller.setId({ detail: { id: '10' } });
        await new Promise(r => setTimeout(r, 0));

        expect(controller.submitBtnTarget.disabled).toBe(true);

        controller.approversTarget.value = '2';
        controller.checkReadyToSubmit();
        expect(controller.submitBtnTarget.disabled).toBe(false);
    });
});
