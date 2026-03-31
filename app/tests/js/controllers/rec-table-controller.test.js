// Controller registers on window.Controllers (no default export)
import '../../../plugins/Awards/Assets/js/controllers/rec-table-controller.js';
const AwardsRecommendationTable = window.Controllers['awards-rec-table'];

describe('AwardsRecommendationTable', () => {
    let controller;
    let mockOutletBtn;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="awards-rec-table">
                <input type="checkbox" data-awards-rec-table-target="CheckAllBox">
                <input type="checkbox" data-awards-rec-table-target="rowCheckbox" value="1">
                <input type="checkbox" data-awards-rec-table-target="rowCheckbox" value="2">
                <input type="checkbox" data-awards-rec-table-target="rowCheckbox" value="3">
            </div>
        `;

        mockOutletBtn = { btnDataValue: {} };

        controller = new AwardsRecommendationTable();
        controller.element = document.querySelector('[data-controller="awards-rec-table"]');

        // Wire up targets
        controller.CheckAllBoxTarget = document.querySelector('[data-awards-rec-table-target="CheckAllBox"]');
        controller.rowCheckboxTargets = Array.from(
            document.querySelectorAll('[data-awards-rec-table-target="rowCheckbox"]')
        );

        // Wire up outlet
        controller.outletBtnOutlet = mockOutletBtn;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    // --- Static properties ---

    test('has correct static targets', () => {
        expect(AwardsRecommendationTable.targets).toEqual(
            expect.arrayContaining(['rowCheckbox', 'CheckAllBox'])
        );
    });

    test('has correct static outlets', () => {
        expect(AwardsRecommendationTable.outlets).toEqual(
            expect.arrayContaining(['outlet-btn'])
        );
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['awards-rec-table']).toBe(AwardsRecommendationTable);
    });

    // --- checked ---

    test('checked collects selected checkbox values into outlet', () => {
        controller.rowCheckboxTargets[0].checked = true;
        controller.rowCheckboxTargets[2].checked = true;

        controller.checked({});

        expect(mockOutletBtn.btnDataValue).toEqual({ ids: ['1', '3'] });
    });

    test('checked clears outlet when no checkboxes selected', () => {
        controller.checked({});
        expect(mockOutletBtn.btnDataValue).toEqual({});
    });

    // --- checkAll ---

    test('checkAll checks all checkboxes and sends all IDs to outlet', () => {
        controller.CheckAllBoxTarget.checked = true;
        controller.checkAll({});

        controller.rowCheckboxTargets.forEach(cb => {
            expect(cb.checked).toBe(true);
        });
        expect(mockOutletBtn.btnDataValue).toEqual({ ids: ['1', '2', '3'] });
    });

    test('checkAll unchecks all checkboxes and clears outlet', () => {
        // First check all
        controller.rowCheckboxTargets.forEach(cb => cb.checked = true);
        controller.CheckAllBoxTarget.checked = false;
        controller.checkAll({});

        controller.rowCheckboxTargets.forEach(cb => {
            expect(cb.checked).toBe(false);
        });
        expect(mockOutletBtn.btnDataValue).toEqual({});
    });
});
