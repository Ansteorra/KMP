import '../../../assets/js/controllers/activity-toggle-controller.js';
const ActivityToggleController = window.Controllers['activity-toggle'];

describe('ActivityToggleController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="activity-toggle">
                <input type="checkbox" data-activity-toggle-target="checkbox">
                <input type="text" data-activity-toggle-target="descriptionField" disabled>
            </div>
        `;

        controller = new ActivityToggleController();
        controller.element = document.querySelector('[data-controller="activity-toggle"]');
        controller.checkboxTarget = document.querySelector('[data-activity-toggle-target="checkbox"]');
        controller.descriptionFieldTarget = document.querySelector('[data-activity-toggle-target="descriptionField"]');
        controller.hasCheckboxTarget = true;
        controller.hasDescriptionFieldTarget = true;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['activity-toggle']).toBe(ActivityToggleController);
    });

    test('has correct static targets', () => {
        expect(ActivityToggleController.targets).toEqual(
            expect.arrayContaining(['checkbox', 'descriptionField'])
        );
    });

    test('toggleDescription enables field when checkbox checked', () => {
        const event = { target: { checked: true } };
        controller.toggleDescription(event);
        expect(controller.descriptionFieldTarget.disabled).toBe(false);
    });

    test('toggleDescription disables field when checkbox unchecked', () => {
        controller.descriptionFieldTarget.disabled = false;
        controller.descriptionFieldTarget.value = 'some text';
        const event = { target: { checked: false } };
        controller.toggleDescription(event);
        expect(controller.descriptionFieldTarget.disabled).toBe(true);
    });

    test('toggleDescription clears value when checkbox unchecked', () => {
        controller.descriptionFieldTarget.value = 'some text';
        const event = { target: { checked: false } };
        controller.toggleDescription(event);
        expect(controller.descriptionFieldTarget.value).toBe('');
    });

    test('toggleDescription preserves value when checkbox checked', () => {
        controller.descriptionFieldTarget.value = 'existing text';
        const event = { target: { checked: true } };
        controller.toggleDescription(event);
        expect(controller.descriptionFieldTarget.value).toBe('existing text');
    });
});
