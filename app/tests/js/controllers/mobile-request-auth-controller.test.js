// MobileControllerBase must be imported first so it's available
import '../../../assets/js/controllers/mobile-controller-base.js';
import '../../../plugins/Activities/assets/js/controllers/mobile-request-auth-controller.js';
const MobileRequestAuthController = window.Controllers['mobile-request-auth'];
const MobileControllerBase = window.Controllers['mobile-controller-base'];

describe('MobileRequestAuthController', () => {
    let controller;

    beforeEach(() => {
        // Reset static state
        MobileControllerBase.isOnline = true;
        MobileControllerBase.connectionListeners = new Set();
        MobileControllerBase.initialized = false;

        document.body.innerHTML = `
            <form data-controller="mobile-request-auth"
                  data-mobile-request-auth-approvers-url-value="/api/approvers"
                  data-mobile-request-auth-member-id-value="42">
                <div data-mobile-request-auth-target="onlineStatus" hidden></div>
                <select data-mobile-request-auth-target="activitySelect">
                    <option value="">Select</option>
                    <option value="1">Activity 1</option>
                    <option value="5">Activity 5</option>
                </select>
                <select data-mobile-request-auth-target="approverSelect">
                    <option value="">Select</option>
                    <option value="2">Approver 2</option>
                </select>
                <span data-mobile-request-auth-target="approverHelp">Select an activity</span>
                <button type="submit" data-mobile-request-auth-target="submitBtn" disabled>
                    <span data-mobile-request-auth-target="submitText">Submit</span>
                </button>
                <div data-mobile-request-auth-target="form"></div>
            </form>
        `;

        controller = new MobileRequestAuthController();
        controller.element = document.querySelector('[data-controller="mobile-request-auth"]');

        // Wire up targets
        controller.formTarget = document.querySelector('[data-controller="mobile-request-auth"]');
        controller.activitySelectTarget = document.querySelector('[data-mobile-request-auth-target="activitySelect"]');
        controller.approverSelectTarget = document.querySelector('[data-mobile-request-auth-target="approverSelect"]');
        controller.approverHelpTarget = document.querySelector('[data-mobile-request-auth-target="approverHelp"]');
        controller.submitBtnTarget = document.querySelector('[data-mobile-request-auth-target="submitBtn"]');
        controller.submitTextTarget = document.querySelector('[data-mobile-request-auth-target="submitText"]');
        controller.onlineStatusTarget = document.querySelector('[data-mobile-request-auth-target="onlineStatus"]');

        // Wire up has* checks
        controller.hasFormTarget = true;
        controller.hasActivitySelectTarget = true;
        controller.hasApproverSelectTarget = true;
        controller.hasOnlineStatusTarget = true;

        // Wire up values
        controller.approversUrlValue = '/api/approvers';
        controller.memberIdValue = 42;

        // Initialize bound handlers map (normally done by base class)
        controller._boundHandlers = new Map();
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
        delete global.fetch;
        MobileControllerBase.isOnline = true;
        MobileControllerBase.connectionListeners = new Set();
        MobileControllerBase.initialized = false;
    });

    // --- Static properties ---

    test('has correct static targets', () => {
        expect(MobileRequestAuthController.targets).toEqual(
            expect.arrayContaining([
                'form', 'activitySelect', 'approverSelect',
                'approverHelp', 'submitBtn', 'submitText', 'onlineStatus'
            ])
        );
    });

    test('has correct static values', () => {
        expect(MobileRequestAuthController.values).toHaveProperty('approversUrl', String);
        expect(MobileRequestAuthController.values).toHaveProperty('memberId', Number);
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['mobile-request-auth']).toBe(MobileRequestAuthController);
    });

    // --- updateOnlineUI ---

    test('updateOnlineUI shows offline status when offline', () => {
        MobileControllerBase.isOnline = false;
        controller.updateOnlineUI();

        expect(controller.onlineStatusTarget.hidden).toBe(false);
        expect(controller.onlineStatusTarget.classList.contains('offline')).toBe(true);
        expect(controller.activitySelectTarget.disabled).toBe(true);
        expect(controller.approverSelectTarget.disabled).toBe(true);
        expect(controller.submitBtnTarget.disabled).toBe(true);
        expect(controller.approverHelpTarget.textContent).toBe('You must be online to submit requests');
    });

    test('updateOnlineUI hides offline status when online', () => {
        MobileControllerBase.isOnline = true;
        controller.activitySelectTarget.value = '';
        controller.updateOnlineUI();

        expect(controller.onlineStatusTarget.hidden).toBe(true);
        expect(controller.activitySelectTarget.disabled).toBe(false);
        expect(controller.approverSelectTarget.disabled).toBe(false);
        expect(controller.approverHelpTarget.textContent).toBe('Select an activity to see available approvers');
    });

    // --- validateForm ---

    test('validateForm enables submit when both selects have values and online', () => {
        MobileControllerBase.isOnline = true;
        controller.activitySelectTarget.value = '1';
        controller.approverSelectTarget.value = '2';
        controller.validateForm();
        expect(controller.submitBtnTarget.disabled).toBe(false);
    });

    test('validateForm disables submit when activity not selected', () => {
        MobileControllerBase.isOnline = true;
        controller.activitySelectTarget.value = '';
        controller.approverSelectTarget.value = '2';
        controller.validateForm();
        expect(controller.submitBtnTarget.disabled).toBe(true);
    });

    test('validateForm disables submit when offline', () => {
        MobileControllerBase.isOnline = false;
        controller.activitySelectTarget.value = '1';
        controller.approverSelectTarget.value = '2';
        controller.validateForm();
        expect(controller.submitBtnTarget.disabled).toBe(true);
    });

    // --- loadApprovers ---

    test('loadApprovers fetches and populates approver options', async () => {
        MobileControllerBase.isOnline = true;
        controller.fetchWithRetry = jest.fn(() => Promise.resolve({
            json: () => Promise.resolve([
                { id: 1, sca_name: 'Sir Test' },
                { id: 2, sca_name: 'Lady Test' }
            ])
        }));

        const event = { target: { value: '5' } };
        await controller.loadApprovers(event);

        expect(controller.fetchWithRetry).toHaveBeenCalledWith('/api/approvers/5/42');
        const options = controller.approverSelectTarget.querySelectorAll('option');
        expect(options.length).toBe(3); // empty + 2 approvers
        expect(options[1].textContent).toBe('Sir Test');
    });

    test('loadApprovers shows message when no approvers returned', async () => {
        MobileControllerBase.isOnline = true;
        controller.fetchWithRetry = jest.fn(() => Promise.resolve({
            json: () => Promise.resolve([])
        }));

        const event = { target: { value: '5' } };
        await controller.loadApprovers(event);

        expect(controller.approverHelpTarget.textContent).toBe('No approvers found for this activity');
    });

    test('loadApprovers shows select-first when no activityId', async () => {
        const event = { target: { value: '' } };
        await controller.loadApprovers(event);

        expect(controller.approverSelectTarget.innerHTML).toContain('Select activity first');
    });

    test('loadApprovers shows offline message when offline', async () => {
        MobileControllerBase.isOnline = false;
        const event = { target: { value: '5' } };
        await controller.loadApprovers(event);

        expect(controller.approverSelectTarget.innerHTML).toContain('You must be online');
    });

    test('loadApprovers handles fetch error', async () => {
        MobileControllerBase.isOnline = true;
        controller.fetchWithRetry = jest.fn(() => Promise.reject(new Error('Network error')));

        const event = { target: { value: '5' } };
        await controller.loadApprovers(event);

        expect(controller.approverSelectTarget.innerHTML).toContain('Error loading approvers');
    });

    // --- handleSubmit ---

    test('handleSubmit prevents submission when offline', () => {
        MobileControllerBase.isOnline = false;
        const event = { preventDefault: jest.fn() };

        controller.handleSubmit.call(controller, event);

        expect(event.preventDefault).toHaveBeenCalled();
        expect(controller.approverHelpTarget.textContent).toBe('You must be online to submit authorization requests');
    });

    test('handleSubmit disables button and shows spinner when online', () => {
        MobileControllerBase.isOnline = true;
        const event = { preventDefault: jest.fn() };

        const result = controller.handleSubmit.call(controller, event);

        expect(controller.submitBtnTarget.disabled).toBe(true);
        expect(controller.submitTextTarget.innerHTML).toContain('Submitting...');
        expect(result).toBe(true);
    });

    // --- onConnectionStateChanged ---

    test('onConnectionStateChanged calls updateOnlineUI', () => {
        const spy = jest.spyOn(controller, 'updateOnlineUI');
        controller.onConnectionStateChanged(true);
        expect(spy).toHaveBeenCalled();
    });
});
