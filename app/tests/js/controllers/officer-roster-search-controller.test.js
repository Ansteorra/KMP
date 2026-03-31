// Controller registers on window.Controllers (no default export)
import '../../../plugins/Officers/assets/js/controllers/officer-roster-search-controller.js';
const OfficerRosterSearchForm = window.Controllers['officer-roster-search'];

describe('OfficerRosterSearchForm', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="officer-roster-search">
                <select data-officer-roster-search-target="warrantPeriods">
                    <option value="0">Select</option>
                    <option value="1">Period 1</option>
                </select>
                <select data-officer-roster-search-target="departments">
                    <option value="0">Select</option>
                    <option value="1">Dept 1</option>
                </select>
                <button data-officer-roster-search-target="showBtn" disabled>Show</button>
            </div>
        `;

        controller = new OfficerRosterSearchForm();
        controller.element = document.querySelector('[data-controller="officer-roster-search"]');

        // Wire up targets
        controller.warrantPeriodsTarget = document.querySelector('[data-officer-roster-search-target="warrantPeriods"]');
        controller.departmentsTarget = document.querySelector('[data-officer-roster-search-target="departments"]');
        controller.showBtnTarget = document.querySelector('[data-officer-roster-search-target="showBtn"]');
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    // --- Static properties ---

    test('has correct static targets', () => {
        expect(OfficerRosterSearchForm.targets).toEqual(
            expect.arrayContaining(['warrantPeriods', 'departments', 'showBtn'])
        );
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['officer-roster-search']).toBe(OfficerRosterSearchForm);
    });

    // --- checkEnable ---

    test('checkEnable enables button when both selects have values > 0', () => {
        controller.warrantPeriodsTarget.value = '1';
        controller.departmentsTarget.value = '1';
        controller.checkEnable();
        expect(controller.showBtnTarget.disabled).toBe(false);
    });

    test('checkEnable disables button when warrant period is 0', () => {
        controller.warrantPeriodsTarget.value = '0';
        controller.departmentsTarget.value = '1';
        controller.checkEnable();
        expect(controller.showBtnTarget.disabled).toBe(true);
    });

    test('checkEnable disables button when department is 0', () => {
        controller.warrantPeriodsTarget.value = '1';
        controller.departmentsTarget.value = '0';
        controller.checkEnable();
        expect(controller.showBtnTarget.disabled).toBe(true);
    });

    test('checkEnable disables button when both are 0', () => {
        controller.warrantPeriodsTarget.value = '0';
        controller.departmentsTarget.value = '0';
        controller.checkEnable();
        expect(controller.showBtnTarget.disabled).toBe(true);
    });
});
