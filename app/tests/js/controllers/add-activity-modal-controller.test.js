import '../../../assets/js/controllers/add-activity-modal-controller.js';
const AddActivityModalController = window.Controllers['add-activity-modal'];

describe('AddActivityModalController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="add-activity-modal">
                <select data-add-activity-modal-target="activitySelect">
                    <option value="">-- Select --</option>
                    <option value="1">Activity 1</option>
                    <option value="2">Activity 2</option>
                </select>
                <div data-add-activity-modal-target="defaultDescription">Select an activity</div>
                <input data-add-activity-modal-target="customDescription" value="">
                <div data-add-activity-modal-target="activityData"
                     data-activity-id="1"
                     data-activity-description="Description for Activity 1"></div>
                <div data-add-activity-modal-target="activityData"
                     data-activity-id="2"
                     data-activity-description=""></div>
            </div>
        `;

        controller = new AddActivityModalController();
        controller.element = document.querySelector('[data-controller="add-activity-modal"]');
        controller.activitySelectTarget = document.querySelector('[data-add-activity-modal-target="activitySelect"]');
        controller.defaultDescriptionTarget = document.querySelector('[data-add-activity-modal-target="defaultDescription"]');
        controller.customDescriptionTarget = document.querySelector('[data-add-activity-modal-target="customDescription"]');
        controller.activityDataTargets = Array.from(document.querySelectorAll('[data-add-activity-modal-target="activityData"]'));
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['add-activity-modal']).toBe(AddActivityModalController);
    });

    test('has correct static targets', () => {
        expect(AddActivityModalController.targets).toEqual(
            expect.arrayContaining(['activitySelect', 'defaultDescription', 'customDescription', 'activityData'])
        );
    });

    test('updateDefaultDescription shows placeholder when no activity selected', () => {
        const event = { target: { value: '' } };
        controller.updateDefaultDescription(event);
        expect(controller.defaultDescriptionTarget.textContent).toBe(
            'Select an activity to see its default description'
        );
    });

    test('updateDefaultDescription shows description for selected activity', () => {
        const event = { target: { value: '1' } };
        controller.updateDefaultDescription(event);
        expect(controller.defaultDescriptionTarget.textContent).toBe('Description for Activity 1');
    });

    test('updateDefaultDescription shows fallback when activity has no description', () => {
        const event = { target: { value: '2' } };
        controller.updateDefaultDescription(event);
        expect(controller.defaultDescriptionTarget.textContent).toBe('No default description available');
    });

    test('updateDefaultDescription handles unknown activity id gracefully', () => {
        const event = { target: { value: '999' } };
        // Should not throw - activityDataTargets.find returns undefined
        expect(() => controller.updateDefaultDescription(event)).not.toThrow();
    });
});
