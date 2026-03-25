// Controller registers on window.Controllers (no default export)
import '../../../plugins/Activities/assets/js/controllers/renew-auth-controller.js';
const RenewAuthController = window.Controllers['activities-renew-auth'];

describe('ActivitiesRenewAuthorization', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <form data-controller="activities-renew-auth"
                  data-activities-renew-auth-url-value="/activities/approvers">
                <input type="hidden" data-activities-renew-auth-target="activity" value="">
                <input type="hidden" data-activities-renew-auth-target="approvers" value="">
                <button type="submit"
                        data-activities-renew-auth-target="submitBtn"
                        disabled>Renew</button>
                <input type="hidden" data-activities-renew-auth-target="memberId" value="100">
                <input type="hidden" data-activities-renew-auth-target="id" value="">
            </form>
        `;

        controller = new RenewAuthController();
        controller.element = document.querySelector('[data-controller="activities-renew-auth"]');

        // Wire up targets
        controller.activityTarget = document.querySelector('[data-activities-renew-auth-target="activity"]');
        controller.approversTarget = document.querySelector('[data-activities-renew-auth-target="approvers"]');
        controller.submitBtnTarget = document.querySelector('[data-activities-renew-auth-target="submitBtn"]');
        controller.memberIdTarget = document.querySelector('[data-activities-renew-auth-target="memberId"]');
        controller.idTarget = document.querySelector('[data-activities-renew-auth-target="id"]');

        // Wire up values
        controller.urlValue = '/activities/approvers';

        // Wire up has* checks
        controller.hasApproversTarget = true;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
        delete global.fetch;
    });

    // --- Instantiation ---

    test('instantiates with correct static targets', () => {
        expect(RenewAuthController.targets).toEqual(
            expect.arrayContaining(['activity', 'approvers', 'submitBtn', 'memberId', 'id'])
        );
    });

    test('instantiates with correct static values', () => {
        expect(RenewAuthController.values).toHaveProperty('url', String);
    });

    test('defines outlet-btn outlet', () => {
        expect(RenewAuthController.outlets).toEqual(
            expect.arrayContaining(['outlet-btn'])
        );
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['activities-renew-auth']).toBe(RenewAuthController);
    });

    // --- setId ---

    test('setId sets id and activity values from event', () => {
        const spy = jest.spyOn(controller, 'getApprovers').mockImplementation(() => {});
        controller.setId({ detail: { id: '42', activity: '7' } });

        expect(controller.idTarget.value).toBe('42');
        expect(controller.activityTarget.value).toBe('7');
        expect(spy).toHaveBeenCalled();
    });

    // --- getApprovers ---

    test('getApprovers fetches with activity and member IDs', async () => {
        global.fetch = jest.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve([
                { id: 1, sca_name: 'Sir Alpha' },
                { id: 2, sca_name: 'Lady Beta' },
            ])
        }));

        controller.activityTarget.value = '7';
        controller.memberIdTarget.value = '100';
        controller.getApprovers();
        await new Promise(r => setTimeout(r, 0));

        expect(global.fetch).toHaveBeenCalledWith(
            '/activities/approvers/7/100',
            expect.any(Object)
        );
        expect(controller.approversTarget.options.length).toBe(2);
        expect(controller.submitBtnTarget.disabled).toBe(true);
        expect(controller.approversTarget.disabled).toBe(false);
    });

    test('getApprovers auto-selects when single approver', async () => {
        global.fetch = jest.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve([
                { id: 5, sca_name: 'Sir Only' },
            ])
        }));

        controller.activityTarget.value = '7';
        controller.memberIdTarget.value = '100';
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

    test('checkReadyToSubmit disables submit for NaN', () => {
        controller.approversTarget.value = 'invalid';
        controller.checkReadyToSubmit();
        expect(controller.submitBtnTarget.disabled).toBe(true);
    });

    test('checkReadyToSubmit disables submit for empty string', () => {
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

    test('outletBtnOutletDisconnected removes listener after connect', () => {
        const mockOutlet = { addListener: jest.fn(), removeListener: jest.fn() };
        // Must connect first to create the _boundSetId
        controller.outletBtnOutletConnected(mockOutlet, document.createElement('button'));
        controller.outletBtnOutletDisconnected(mockOutlet);
        expect(mockOutlet.removeListener).toHaveBeenCalledWith(expect.any(Function));
    });

    test('outletBtnOutletDisconnected handles missing bound listener', () => {
        const mockOutlet = { removeListener: jest.fn() };
        controller._boundSetId = null;
        controller.outletBtnOutletDisconnected(mockOutlet);
        // Should not call removeListener when _boundSetId is null
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

        controller.setId({ detail: { id: '42', activity: '7' } });
        await new Promise(r => setTimeout(r, 0));

        expect(controller.submitBtnTarget.disabled).toBe(true);

        controller.approversTarget.value = '1';
        controller.checkReadyToSubmit();
        expect(controller.submitBtnTarget.disabled).toBe(false);
    });
});
