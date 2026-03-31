// Controller registers on window.Controllers (no default export)
import '../../../plugins/Activities/assets/js/controllers/request-auth-controller.js';
const RequestAuthController = window.Controllers['activities-request-auth'];

describe('ActivitiesRequestAuthorization', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <form data-controller="activities-request-auth"
                  data-activities-request-auth-url-value="/activities/approvers">
                <select data-activities-request-auth-target="activity">
                    <option value="">Select...</option>
                    <option value="5">Archery</option>
                </select>
                <input type="hidden" data-activities-request-auth-target="approvers" value="">
                <button type="submit"
                        data-activities-request-auth-target="submitBtn"
                        disabled>Request</button>
                <input type="hidden" data-activities-request-auth-target="memberId" value="100">
            </form>
        `;

        controller = new RequestAuthController();
        controller.element = document.querySelector('[data-controller="activities-request-auth"]');

        // Wire up targets
        controller.activityTarget = document.querySelector('[data-activities-request-auth-target="activity"]');
        controller.approversTarget = document.querySelector('[data-activities-request-auth-target="approvers"]');
        controller.submitBtnTarget = document.querySelector('[data-activities-request-auth-target="submitBtn"]');
        controller.memberIdTarget = document.querySelector('[data-activities-request-auth-target="memberId"]');

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
        expect(RequestAuthController.targets).toEqual(
            expect.arrayContaining(['activity', 'approvers', 'submitBtn', 'memberId'])
        );
    });

    test('instantiates with correct static values', () => {
        expect(RequestAuthController.values).toHaveProperty('url', String);
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['activities-request-auth']).toBe(RequestAuthController);
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

        controller.activityTarget.value = '5';
        controller.memberIdTarget.value = '100';
        controller.getApprovers({});
        await new Promise(r => setTimeout(r, 0));

        expect(global.fetch).toHaveBeenCalledWith(
            '/activities/approvers/5/100',
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

        controller.activityTarget.value = '5';
        controller.memberIdTarget.value = '100';
        controller.getApprovers({});
        await new Promise(r => setTimeout(r, 0));

        expect(controller.approversTarget.value).toBe('5');
        expect(controller.submitBtnTarget.disabled).toBe(false);
    });

    // --- acConnected ---

    test('acConnected disables approvers dropdown', () => {
        controller.approversTarget.disabled = false;
        controller.acConnected();
        expect(controller.approversTarget.disabled).toBe(true);
    });

    test('acConnected does nothing without approvers target', () => {
        controller.hasApproversTarget = false;
        // Should not throw
        expect(() => controller.acConnected()).not.toThrow();
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

    test('checkReadyToSubmit toggles correctly', () => {
        controller.approversTarget.value = '10';
        controller.checkReadyToSubmit();
        expect(controller.submitBtnTarget.disabled).toBe(false);

        controller.approversTarget.value = '0';
        controller.checkReadyToSubmit();
        expect(controller.submitBtnTarget.disabled).toBe(true);
    });

    // --- submitBtnTargetConnected ---

    test('submitBtnTargetConnected disables submit button', () => {
        controller.submitBtnTarget.disabled = false;
        controller.submitBtnTargetConnected();
        expect(controller.submitBtnTarget.disabled).toBe(true);
    });

    // --- approversTargetConnected ---

    test('approversTargetConnected disables approvers dropdown', () => {
        controller.approversTarget.disabled = false;
        controller.approversTargetConnected();
        expect(controller.approversTarget.disabled).toBe(true);
    });

    // --- Integration workflow ---

    test('full workflow: select activity, fetch approvers, select approver, enable submit', async () => {
        global.fetch = jest.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve([
                { id: 1, sca_name: 'Sir Alpha' },
                { id: 2, sca_name: 'Lady Beta' },
            ])
        }));

        // Initialize form
        controller.approversTargetConnected();
        controller.submitBtnTargetConnected();
        expect(controller.approversTarget.disabled).toBe(true);
        expect(controller.submitBtnTarget.disabled).toBe(true);

        // Select an activity and get approvers
        controller.activityTarget.value = '5';
        controller.getApprovers({});
        await new Promise(r => setTimeout(r, 0));

        expect(controller.approversTarget.disabled).toBe(false);
        expect(controller.submitBtnTarget.disabled).toBe(true);

        // Select an approver
        controller.approversTarget.value = '2';
        controller.checkReadyToSubmit();
        expect(controller.submitBtnTarget.disabled).toBe(false);
    });
});
