import '../../../assets/js/controllers/gathering-clone-controller.js';
const GatheringCloneController = window.Controllers['gathering-clone'];

describe('GatheringCloneController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <form data-controller="gathering-clone">
                <input type="text" data-gathering-clone-target="nameInput" value="">
                <input type="date" data-gathering-clone-target="startDate" value="">
                <input type="date" data-gathering-clone-target="endDate" value="">
                <button type="submit" data-gathering-clone-target="submitButton">Clone</button>
            </form>
        `;

        controller = new GatheringCloneController();
        controller.element = document.querySelector('[data-controller="gathering-clone"]');
        controller.nameInputTarget = document.querySelector('[data-gathering-clone-target="nameInput"]');
        controller.startDateTarget = document.querySelector('[data-gathering-clone-target="startDate"]');
        controller.endDateTarget = document.querySelector('[data-gathering-clone-target="endDate"]');
        controller.submitButtonTarget = document.querySelector('[data-gathering-clone-target="submitButton"]');
        controller.hasNameInputTarget = true;
        controller.hasStartDateTarget = true;
        controller.hasEndDateTarget = true;
        controller.hasSubmitButtonTarget = true;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['gathering-clone']).toBe(GatheringCloneController);
    });

    test('has correct static targets including nameInput', () => {
        expect(GatheringCloneController.targets).toEqual(
            expect.arrayContaining(['nameInput', 'startDate', 'endDate', 'submitButton'])
        );
    });

    test('inherits validateDates from BaseGatheringFormController', () => {
        expect(typeof controller.validateDates).toBe('function');
    });

    test('inherits startDateChanged from BaseGatheringFormController', () => {
        expect(typeof controller.startDateChanged).toBe('function');
    });

    test('validateDates returns true for valid date range', () => {
        controller.startDateTarget.value = '2025-06-01';
        controller.endDateTarget.value = '2025-06-05';
        expect(controller.validateDates()).toBe(true);
    });

    test('validateDates returns false when end is before start', () => {
        controller.startDateTarget.value = '2025-06-05';
        controller.endDateTarget.value = '2025-06-01';
        expect(controller.validateDates()).toBe(false);
    });

    test('startDateChanged updates end date when empty', () => {
        controller.startDateTarget.value = '2025-06-15';
        controller.endDateTarget.value = '';
        controller.startDateChanged({ target: controller.startDateTarget });
        expect(controller.endDateTarget.value).toBe('2025-06-15');
    });
});
