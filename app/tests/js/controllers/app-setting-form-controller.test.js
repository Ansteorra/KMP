require('../../../assets/js/controllers/app-setting-form-controller.js');

const AppSettingForm = window.Controllers['app-setting-form'];

describe('AppSettingFormController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="app-setting-form">
                <form data-app-setting-form-target="form" action="/admin/settings" method="post">
                    <input type="text" name="key" value="test-key">
                    <input type="text" name="value" value="test-value">
                    <button type="submit" data-app-setting-form-target="submitBtn" disabled>Save</button>
                </form>
            </div>
        `;

        controller = new AppSettingForm();
        controller.element = document.querySelector('[data-controller="app-setting-form"]');
        controller.formTarget = document.querySelector('[data-app-setting-form-target="form"]');
        controller.submitBtnTarget = document.querySelector('[data-app-setting-form-target="submitBtn"]');
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['app-setting-form']).toBe(AppSettingForm);
    });

    test('has correct static targets', () => {
        expect(AppSettingForm.targets).toEqual(
            expect.arrayContaining(['submitBtn', 'form'])
        );
    });

    test('submit prevents default and submits the form', () => {
        const event = { preventDefault: jest.fn() };
        const submitSpy = jest.spyOn(controller.formTarget, 'submit').mockImplementation(() => {});
        
        controller.submit(event);
        
        expect(event.preventDefault).toHaveBeenCalled();
        expect(submitSpy).toHaveBeenCalled();
    });

    test('enableSubmit enables the submit button', () => {
        controller.submitBtnTarget.disabled = true;
        controller.enableSubmit();
        expect(controller.submitBtnTarget.disabled).toBe(false);
    });

    test('enableSubmit focuses the submit button', () => {
        const focusSpy = jest.spyOn(controller.submitBtnTarget, 'focus');
        controller.enableSubmit();
        expect(focusSpy).toHaveBeenCalled();
    });
});
