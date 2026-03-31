// BaseGatheringFormController is exported, not registered on window.Controllers
import { BaseGatheringFormController } from '../../../assets/js/controllers/base-gathering-form-controller.js';

describe('BaseGatheringFormController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <form data-controller="base-gathering-form">
                <input type="date" data-base-gathering-form-target="startDate" value="">
                <input type="date" data-base-gathering-form-target="endDate" value="">
                <button type="submit" data-base-gathering-form-target="submitButton">Save</button>
            </form>
        `;

        controller = new BaseGatheringFormController();
        controller.element = document.querySelector('[data-controller="base-gathering-form"]');
        controller.startDateTarget = document.querySelector('[data-base-gathering-form-target="startDate"]');
        controller.endDateTarget = document.querySelector('[data-base-gathering-form-target="endDate"]');
        controller.submitButtonTarget = document.querySelector('[data-base-gathering-form-target="submitButton"]');
        controller.hasStartDateTarget = true;
        controller.hasEndDateTarget = true;
        controller.hasSubmitButtonTarget = true;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    test('has correct static targets', () => {
        expect(BaseGatheringFormController.targets).toEqual(
            expect.arrayContaining(['startDate', 'endDate', 'submitButton'])
        );
    });

    test('connect calls validateDates when both targets exist', () => {
        const spy = jest.spyOn(controller, 'validateDates').mockImplementation(() => true);
        controller.connect();
        expect(spy).toHaveBeenCalled();
    });

    test('connect skips validation when startDate target missing', () => {
        controller.hasStartDateTarget = false;
        const spy = jest.spyOn(controller, 'validateDates');
        controller.connect();
        expect(spy).not.toHaveBeenCalled();
    });

    test('validateDates returns true when dates are valid', () => {
        controller.startDateTarget.value = '2025-01-01';
        controller.endDateTarget.value = '2025-01-02';
        expect(controller.validateDates()).toBe(true);
    });

    test('validateDates returns true when dates are the same', () => {
        controller.startDateTarget.value = '2025-01-01';
        controller.endDateTarget.value = '2025-01-01';
        expect(controller.validateDates()).toBe(true);
    });

    test('validateDates returns false when end date is before start date', () => {
        controller.startDateTarget.value = '2025-01-05';
        controller.endDateTarget.value = '2025-01-01';
        expect(controller.validateDates()).toBe(false);
    });

    test('validateDates disables submit button on invalid dates', () => {
        controller.startDateTarget.value = '2025-01-05';
        controller.endDateTarget.value = '2025-01-01';
        controller.validateDates();
        expect(controller.submitButtonTarget.disabled).toBe(true);
    });

    test('validateDates enables submit button on valid dates', () => {
        controller.submitButtonTarget.disabled = true;
        controller.startDateTarget.value = '2025-01-01';
        controller.endDateTarget.value = '2025-01-02';
        controller.validateDates();
        expect(controller.submitButtonTarget.disabled).toBe(false);
    });

    test('validateDates adds is-invalid class on error', () => {
        controller.startDateTarget.value = '2025-01-05';
        controller.endDateTarget.value = '2025-01-01';
        controller.validateDates();
        expect(controller.endDateTarget.classList.contains('is-invalid')).toBe(true);
    });

    test('validateDates shows error message', () => {
        controller.startDateTarget.value = '2025-01-05';
        controller.endDateTarget.value = '2025-01-01';
        controller.validateDates();
        const feedback = controller.endDateTarget.parentElement.querySelector('.invalid-feedback');
        expect(feedback).not.toBeNull();
        expect(feedback.textContent).toBe('End date cannot be before start date');
    });

    test('validateDates clears previous errors on valid input', () => {
        // First create an error
        controller.startDateTarget.value = '2025-01-05';
        controller.endDateTarget.value = '2025-01-01';
        controller.validateDates();

        // Then fix it
        controller.endDateTarget.value = '2025-01-10';
        controller.validateDates();
        expect(controller.endDateTarget.classList.contains('is-invalid')).toBe(false);
    });

    test('startDateChanged updates end date when end is empty', () => {
        controller.startDateTarget.value = '2025-03-15';
        controller.endDateTarget.value = '';
        controller.startDateChanged({ target: controller.startDateTarget });
        expect(controller.endDateTarget.value).toBe('2025-03-15');
    });

    test('startDateChanged updates end date when end is before start', () => {
        controller.startDateTarget.value = '2025-03-15';
        controller.endDateTarget.value = '2025-03-10';
        controller.startDateChanged({ target: controller.startDateTarget });
        expect(controller.endDateTarget.value).toBe('2025-03-15');
    });

    test('startDateChanged keeps end date when end is after start', () => {
        controller.startDateTarget.value = '2025-03-15';
        controller.endDateTarget.value = '2025-03-20';
        controller.startDateChanged({ target: controller.startDateTarget });
        expect(controller.endDateTarget.value).toBe('2025-03-20');
    });

    test('endDateChanged calls validateDates', () => {
        const spy = jest.spyOn(controller, 'validateDates');
        controller.endDateChanged({ target: controller.endDateTarget });
        expect(spy).toHaveBeenCalled();
    });

    test('validateForm prevents default on invalid dates', () => {
        controller.startDateTarget.value = '2025-01-05';
        controller.endDateTarget.value = '2025-01-01';
        const event = { preventDefault: jest.fn() };
        const result = controller.validateForm(event);
        expect(event.preventDefault).toHaveBeenCalled();
        expect(result).toBe(false);
    });

    test('validateForm allows submission on valid dates', () => {
        controller.startDateTarget.value = '2025-01-01';
        controller.endDateTarget.value = '2025-01-05';
        const event = { preventDefault: jest.fn() };
        const result = controller.validateForm(event);
        expect(event.preventDefault).not.toHaveBeenCalled();
        expect(result).toBe(true);
    });

    test('validateDates returns true when targets missing', () => {
        controller.hasStartDateTarget = false;
        expect(controller.validateDates()).toBe(true);
    });

    test('clearValidationMessages hides feedback elements', () => {
        // Create a feedback element
        const feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        feedback.style.display = 'block';
        controller.element.appendChild(feedback);

        controller.clearValidationMessages();
        expect(feedback.style.display).toBe('none');
    });
});
