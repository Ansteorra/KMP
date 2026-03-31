// Controller registers on window.Controllers (no default export)
import '../../../plugins/Waivers/assets/js/controllers/add-requirement-controller.js';
const WaiversAddRequirement = window.Controllers['waivers-add-requirement'];

describe('WaiversAddRequirement', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="waivers-add-requirement"
                 data-waivers-add-requirement-url-value="/waivers/types">
                <select data-waivers-add-requirement-target="activityId">
                    <option value="">Select</option>
                    <option value="10">Activity 10</option>
                </select>
                <select data-waivers-add-requirement-target="waiverType">
                    <option value="">Select</option>
                </select>
                <button data-waivers-add-requirement-target="submitBtn" disabled>Submit</button>
            </div>
        `;

        controller = new WaiversAddRequirement();
        controller.element = document.querySelector('[data-controller="waivers-add-requirement"]');

        // Wire up targets
        controller.activityIdTarget = document.querySelector('[data-waivers-add-requirement-target="activityId"]');
        controller.waiverTypeTarget = document.querySelector('[data-waivers-add-requirement-target="waiverType"]');
        controller.submitBtnTarget = document.querySelector('[data-waivers-add-requirement-target="submitBtn"]');

        // Wire up has* checks
        controller.hasSubmitBtnTarget = true;

        // Wire up values
        controller.urlValue = '/waivers/types';
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
        delete global.fetch;
    });

    // --- Static properties ---

    test('has correct static targets', () => {
        expect(WaiversAddRequirement.targets).toEqual(
            expect.arrayContaining(['waiverType', 'submitBtn', 'activityId'])
        );
    });

    test('has correct static values', () => {
        expect(WaiversAddRequirement.values).toHaveProperty('url', String);
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['waivers-add-requirement']).toBe(WaiversAddRequirement);
    });

    // --- submitBtnTargetConnected ---

    test('submitBtnTargetConnected disables submit button', () => {
        controller.submitBtnTarget.disabled = false;
        controller.submitBtnTargetConnected();
        expect(controller.submitBtnTarget.disabled).toBe(true);
    });

    // --- optionsForFetch ---

    test('optionsForFetch returns correct headers', () => {
        const opts = controller.optionsForFetch();
        expect(opts.headers['X-Requested-With']).toBe('XMLHttpRequest');
        expect(opts.headers['Accept']).toBe('application/json');
    });

    // --- loadWaiverTypes ---

    test('loadWaiverTypes fetches and populates waiver type options', async () => {
        controller.activityIdTarget.value = '10';

        global.fetch = jest.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve({
                waiverTypes: [
                    { id: 1, name: 'Type A' },
                    { id: 2, name: 'Type B' }
                ]
            })
        }));

        // Use a plain object as target so .options can be set freely
        controller.waiverTypeTarget = { options: [], value: '' };
        controller.loadWaiverTypes();

        // Allow promise chain to resolve
        await new Promise(resolve => setTimeout(resolve, 0));

        expect(global.fetch).toHaveBeenCalledWith('/waivers/types/10', expect.any(Object));
        expect(controller.waiverTypeTarget.options).toEqual([
            { value: 1, text: 'Type A' },
            { value: 2, text: 'Type B' }
        ]);
        expect(controller.submitBtnTarget.disabled).toBe(true);
    });

    test('loadWaiverTypes sets empty options when no types returned', async () => {
        controller.activityIdTarget.value = '10';

        global.fetch = jest.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve({ waiverTypes: [] })
        }));

        controller.waiverTypeTarget = { options: [], value: '' };
        controller.loadWaiverTypes();
        await new Promise(resolve => setTimeout(resolve, 0));
        expect(controller.waiverTypeTarget.options).toEqual([]);
    });

    test('loadWaiverTypes handles fetch error', async () => {
        controller.activityIdTarget.value = '10';

        global.fetch = jest.fn(() => Promise.reject(new Error('Network error')));

        await controller.loadWaiverTypes();
        expect(controller.submitBtnTarget.disabled).toBe(true);
    });

    // --- checkReadyToSubmit ---

    test('checkReadyToSubmit enables button when valid type selected', () => {
        // Use a plain object so .value can be set to anything
        controller.waiverTypeTarget = { value: '5' };
        controller.checkReadyToSubmit();
        expect(controller.submitBtnTarget.disabled).toBe(false);
    });

    test('checkReadyToSubmit disables button when no type selected', () => {
        controller.waiverTypeTarget = { value: '' };
        controller.checkReadyToSubmit();
        expect(controller.submitBtnTarget.disabled).toBe(true);
    });

    test('checkReadyToSubmit disables button for non-numeric value', () => {
        controller.waiverTypeTarget = { value: 'abc' };
        controller.checkReadyToSubmit();
        expect(controller.submitBtnTarget.disabled).toBe(true);
    });

    test('checkReadyToSubmit disables button for zero value', () => {
        controller.waiverTypeTarget = { value: '0' };
        controller.checkReadyToSubmit();
        expect(controller.submitBtnTarget.disabled).toBe(true);
    });
});
