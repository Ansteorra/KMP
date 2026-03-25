// Controller registers on window.Controllers (no default export)
import '../../../plugins/Officers/assets/js/controllers/officer-roster-table-controller.js';
const OfficerRosterTableForm = window.Controllers['officer-roster-table'];

describe('OfficerRosterTableForm', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="officer-roster-table">
                <input type="checkbox" data-officer-roster-table-target="rowCheckbox" value="100">
                <input type="checkbox" data-officer-roster-table-target="rowCheckbox" value="200">
            </div>
        `;

        controller = new OfficerRosterTableForm();
        controller.element = document.querySelector('[data-controller="officer-roster-table"]');

        // Wire up targets
        controller.rowCheckboxTargets = Array.from(
            document.querySelectorAll('[data-officer-roster-table-target="rowCheckbox"]')
        );

        controller.ids = [];
        controller.submitBtn = null;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    // --- Static properties ---

    test('has correct static targets', () => {
        expect(OfficerRosterTableForm.targets).toEqual(
            expect.arrayContaining(['rowCheckbox'])
        );
    });

    test('has correct static outlets', () => {
        expect(OfficerRosterTableForm.outlets).toEqual(
            expect.arrayContaining(['outlet-btn'])
        );
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['officer-roster-table']).toBe(OfficerRosterTableForm);
    });

    // --- outletBtnOutletConnected ---

    test('outletBtnOutletConnected stores outlet reference', () => {
        const mockOutlet = { element: { disabled: true } };
        controller.outletBtnOutletConnected(mockOutlet, document.createElement('div'));
        expect(controller.submitBtn).toBe(mockOutlet);
    });

    test('outletBtnOutletConnected enables button when IDs exist', () => {
        const mockOutlet = { element: { disabled: true } };
        controller.ids = ['100'];
        controller.outletBtnOutletConnected(mockOutlet, document.createElement('div'));
        expect(mockOutlet.element.disabled).toBe(false);
    });

    // --- outletBtnOutletDisconnected ---

    test('outletBtnOutletDisconnected clears outlet reference', () => {
        controller.submitBtn = { element: {} };
        controller.outletBtnOutletDisconnected({});
        expect(controller.submitBtn).toBeNull();
    });

    // --- rowCheckboxTargetConnected ---

    test('rowCheckboxTargetConnected adds ID to array', () => {
        const element = { value: '300' };
        controller.rowCheckboxTargetConnected(element);
        expect(controller.ids).toContain('300');
    });

    // --- rowChecked ---

    test('rowChecked adds ID when checked', () => {
        controller.submitBtn = { element: { disabled: true } };
        const event = { target: { checked: true, value: '100' } };
        controller.rowChecked(event);

        expect(controller.ids).toContain('100');
        expect(controller.submitBtn.element.disabled).toBe(false);
    });

    test('rowChecked removes ID when unchecked', () => {
        controller.ids = ['100', '200'];
        controller.submitBtn = { element: { disabled: true } };
        const event = { target: { checked: false, value: '100' } };
        controller.rowChecked(event);

        expect(controller.ids).not.toContain('100');
        expect(controller.ids).toContain('200');
        expect(controller.submitBtn.element.disabled).toBe(false);
    });

    test('rowChecked disables button when no IDs remain', () => {
        controller.ids = ['100'];
        controller.submitBtn = { element: { disabled: false } };
        const event = { target: { checked: false, value: '100' } };
        controller.rowChecked(event);

        expect(controller.ids).toEqual([]);
        expect(controller.submitBtn.element.disabled).toBe(true);
    });
});
