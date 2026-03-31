import '../../../assets/js/controllers/gathering-type-form-controller.js';

const GatheringTypeFormController = window.Controllers['gathering-type-form'];

describe('GatheringTypeFormController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <form data-controller="gathering-type-form">
                <input type="text" data-gathering-type-form-target="name" value="">
                <div class="invalid-feedback d-none" data-gathering-type-form-target="nameError"></div>
                <textarea data-gathering-type-form-target="description"></textarea>
                <span data-gathering-type-form-target="descriptionCount" class="text-muted"></span>
                <div class="invalid-feedback d-none" data-gathering-type-form-target="descriptionError"></div>
                <button type="submit" data-gathering-type-form-target="submitButton">Save</button>
            </form>
        `;

        controller = new GatheringTypeFormController();
        controller.element = document.querySelector('[data-controller="gathering-type-form"]');
        controller.nameTarget = document.querySelector('[data-gathering-type-form-target="name"]');
        controller.nameErrorTarget = document.querySelector('[data-gathering-type-form-target="nameError"]');
        controller.descriptionTarget = document.querySelector('[data-gathering-type-form-target="description"]');
        controller.descriptionCountTarget = document.querySelector('[data-gathering-type-form-target="descriptionCount"]');
        controller.descriptionErrorTarget = document.querySelector('[data-gathering-type-form-target="descriptionError"]');
        controller.submitButtonTarget = document.querySelector('[data-gathering-type-form-target="submitButton"]');
        controller.hasNameTarget = true;
        controller.hasNameErrorTarget = true;
        controller.hasDescriptionTarget = true;
        controller.hasDescriptionCountTarget = true;
        controller.hasDescriptionErrorTarget = true;
        controller.hasSubmitButtonTarget = true;
        controller.maxDescriptionLengthValue = 500;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['gathering-type-form']).toBe(GatheringTypeFormController);
    });

    test('has correct static targets', () => {
        expect(GatheringTypeFormController.targets).toEqual(
            expect.arrayContaining(['name', 'description', 'nameError', 'descriptionCount', 'descriptionError', 'submitButton'])
        );
    });

    test('has correct static values', () => {
        expect(GatheringTypeFormController.values).toHaveProperty('maxDescriptionLength');
        expect(GatheringTypeFormController.values).toHaveProperty('checkNameUrl');
    });

    test('connect sets maxlength on description', () => {
        controller.connect();
        expect(controller.descriptionTarget.getAttribute('maxlength')).toBe('500');
    });

    test('connect calls updateDescriptionCount', () => {
        const spy = jest.spyOn(controller, 'updateDescriptionCount').mockImplementation(() => {});
        controller.connect();
        expect(spy).toHaveBeenCalled();
    });

    // validateName tests
    test('validateName returns false for empty name', () => {
        controller.nameTarget.value = '';
        expect(controller.validateName()).toBe(false);
    });

    test('validateName returns false for name less than 3 chars', () => {
        controller.nameTarget.value = 'Ab';
        expect(controller.validateName()).toBe(false);
        expect(controller.nameErrorTarget.textContent).toBe('Name must be at least 3 characters');
    });

    test('validateName returns false for name over 128 chars', () => {
        controller.nameTarget.value = 'A'.repeat(129);
        expect(controller.validateName()).toBe(false);
        expect(controller.nameErrorTarget.textContent).toBe('Name must be less than 128 characters');
    });

    test('validateName returns true for valid name', () => {
        controller.nameTarget.value = 'Tournament';
        expect(controller.validateName()).toBe(true);
    });

    test('validateName shows is-invalid class on error', () => {
        controller.nameTarget.value = '';
        controller.validateName();
        expect(controller.nameTarget.classList.contains('is-invalid')).toBe(true);
    });

    test('validateName clears error and adds is-valid on success', () => {
        controller.nameTarget.value = 'Valid Name';
        controller.validateName();
        expect(controller.nameTarget.classList.contains('is-valid')).toBe(true);
        expect(controller.nameErrorTarget.classList.contains('d-none')).toBe(true);
    });

    // updateDescriptionCount tests
    test('updateDescriptionCount shows character count', () => {
        controller.descriptionTarget.value = 'Hello';
        controller.updateDescriptionCount();
        expect(controller.descriptionCountTarget.textContent).toBe('5 / 500 characters');
    });

    test('updateDescriptionCount adds warning class when less than 50 remaining', () => {
        controller.descriptionTarget.value = 'A'.repeat(460);
        controller.updateDescriptionCount();
        expect(controller.descriptionCountTarget.classList.contains('text-warning')).toBe(true);
        expect(controller.descriptionCountTarget.classList.contains('text-muted')).toBe(false);
    });

    test('updateDescriptionCount uses text-muted when plenty of chars remaining', () => {
        controller.descriptionTarget.value = 'Short';
        controller.updateDescriptionCount();
        expect(controller.descriptionCountTarget.classList.contains('text-muted')).toBe(true);
        expect(controller.descriptionCountTarget.classList.contains('text-warning')).toBe(false);
    });

    test('updateDescriptionCount truncates text beyond max length', () => {
        controller.descriptionTarget.value = 'A'.repeat(510);
        controller.updateDescriptionCount();
        expect(controller.descriptionTarget.value.length).toBe(500);
    });

    // validateForm tests
    test('validateForm prevents default when invalid', () => {
        controller.nameTarget.value = '';
        const event = { preventDefault: jest.fn() };
        const result = controller.validateForm(event);
        expect(event.preventDefault).toHaveBeenCalled();
        expect(result).toBe(false);
    });

    test('validateForm allows submission when valid', () => {
        controller.nameTarget.value = 'Valid Name';
        controller.descriptionTarget.value = 'A description';
        const event = { preventDefault: jest.fn() };
        const result = controller.validateForm(event);
        expect(event.preventDefault).not.toHaveBeenCalled();
        expect(result).toBe(true);
    });

    // showValidationSummary tests
    test('showValidationSummary adds alert to form', () => {
        controller.showValidationSummary();
        const alert = controller.element.querySelector('.alert.alert-danger.validation-summary');
        expect(alert).not.toBeNull();
        expect(alert.textContent).toContain('Validation Error');
    });

    test('showValidationSummary removes existing alert before adding new one', () => {
        controller.showValidationSummary();
        controller.showValidationSummary();
        const alerts = controller.element.querySelectorAll('.validation-summary');
        expect(alerts.length).toBe(1);
    });

    test('showValidationSummary auto-dismisses after 5 seconds', () => {
        jest.useFakeTimers();
        controller.showValidationSummary();
        const alert = controller.element.querySelector('.validation-summary');
        expect(alert).not.toBeNull();

        jest.advanceTimersByTime(5000);
        const alertAfter = controller.element.querySelector('.validation-summary');
        expect(alertAfter).toBeNull();
        jest.useRealTimers();
    });

    // disableSubmit / enableSubmit
    test('disableSubmit disables the button', () => {
        controller.disableSubmit();
        expect(controller.submitButtonTarget.disabled).toBe(true);
    });

    test('enableSubmit enables the button', () => {
        controller.submitButtonTarget.disabled = true;
        controller.enableSubmit();
        expect(controller.submitButtonTarget.disabled).toBe(false);
    });

    // showDescriptionError / clearDescriptionError
    test('showDescriptionError adds is-invalid and shows message', () => {
        controller.showDescriptionError('Too long');
        expect(controller.descriptionTarget.classList.contains('is-invalid')).toBe(true);
        expect(controller.descriptionTarget.getAttribute('aria-invalid')).toBe('true');
        expect(controller.descriptionErrorTarget.textContent).toBe('Too long');
    });

    test('clearDescriptionError removes is-invalid and hides error', () => {
        controller.showDescriptionError('Error');
        controller.clearDescriptionError();
        expect(controller.descriptionTarget.classList.contains('is-invalid')).toBe(false);
        expect(controller.descriptionErrorTarget.style.display).toBe('none');
    });
});
