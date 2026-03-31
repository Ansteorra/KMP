// Controller registers on window.Controllers (no default export)
import '../../../plugins/Officers/assets/js/controllers/office-form-controller.js';
const OfficeFormController = window.Controllers['office-form'];

describe('OfficeFormController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="office-form">
                <input type="checkbox" data-office-form-target="isDeputy">
                <div data-office-form-target="reportsToBlock">
                    <select data-office-form-target="reportsTo">
                        <option value="">None</option>
                        <option value="1">Seneschal</option>
                    </select>
                </div>
                <div data-office-form-target="deputyToBlock" hidden>
                    <select data-office-form-target="deputyTo" disabled>
                        <option value="">None</option>
                        <option value="2">Herald</option>
                    </select>
                </div>
            </div>
        `;

        controller = new OfficeFormController();
        controller.element = document.querySelector('[data-controller="office-form"]');

        // Wire up targets
        controller.isDeputyTarget = document.querySelector('[data-office-form-target="isDeputy"]');
        controller.reportsToTarget = document.querySelector('[data-office-form-target="reportsTo"]');
        controller.reportsToBlockTarget = document.querySelector('[data-office-form-target="reportsToBlock"]');
        controller.deputyToTarget = document.querySelector('[data-office-form-target="deputyTo"]');
        controller.deputyToBlockTarget = document.querySelector('[data-office-form-target="deputyToBlock"]');
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    // --- Static properties ---

    test('has correct static targets', () => {
        expect(OfficeFormController.targets).toEqual(
            expect.arrayContaining(['reportsTo', 'reportsToBlock', 'deputyTo', 'deputyToBlock', 'isDeputy'])
        );
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['office-form']).toBe(OfficeFormController);
    });

    // --- connect ---

    test('connect calls toggleIsDeputy', () => {
        const spy = jest.spyOn(controller, 'toggleIsDeputy');
        controller.connect();
        expect(spy).toHaveBeenCalled();
    });

    // --- toggleIsDeputy ---

    test('toggleIsDeputy shows deputy fields when isDeputy checked', () => {
        controller.isDeputyTarget.checked = true;
        controller.toggleIsDeputy();

        expect(controller.deputyToBlockTarget.hidden).toBe(false);
        expect(controller.deputyToTarget.disabled).toBe(false);
        expect(controller.reportsToBlockTarget.hidden).toBe(true);
        expect(controller.reportsToTarget.disabled).toBe(true);
    });

    test('toggleIsDeputy shows reportsTo fields when isDeputy unchecked', () => {
        controller.isDeputyTarget.checked = false;
        controller.toggleIsDeputy();

        expect(controller.deputyToBlockTarget.hidden).toBe(true);
        expect(controller.deputyToTarget.disabled).toBe(true);
        expect(controller.deputyToTarget.value).toBe('');
        expect(controller.reportsToBlockTarget.hidden).toBe(false);
        expect(controller.reportsToTarget.disabled).toBe(false);
    });

    test('toggleIsDeputy clears deputyTo value when unchecking', () => {
        controller.deputyToTarget.value = '2';
        controller.isDeputyTarget.checked = false;
        controller.toggleIsDeputy();
        expect(controller.deputyToTarget.value).toBe('');
    });
});
