import '../../../assets/js/controllers/edit-activity-description-controller.js';

const EditActivityDescriptionController = window.Controllers['edit-activity-description'];

describe('EditActivityDescriptionController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="edit-activity-description">
                <input type="hidden" data-edit-activity-description-target="activityId" value="">
                <span data-edit-activity-description-target="activityName"></span>
                <span data-edit-activity-description-target="defaultDescription"></span>
                <textarea data-edit-activity-description-target="customDescription"></textarea>
            </div>
            <div id="editActivityDescriptionModal"></div>
        `;

        controller = new EditActivityDescriptionController();
        controller.element = document.querySelector('[data-controller="edit-activity-description"]');
        controller.activityIdTarget = document.querySelector('[data-edit-activity-description-target="activityId"]');
        controller.activityNameTarget = document.querySelector('[data-edit-activity-description-target="activityName"]');
        controller.defaultDescriptionTarget = document.querySelector('[data-edit-activity-description-target="defaultDescription"]');
        controller.customDescriptionTarget = document.querySelector('[data-edit-activity-description-target="customDescription"]');
        controller.hasActivityIdTarget = true;
        controller.hasActivityNameTarget = true;
        controller.hasDefaultDescriptionTarget = true;
        controller.hasCustomDescriptionTarget = true;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['edit-activity-description']).toBe(EditActivityDescriptionController);
    });

    test('has correct static targets', () => {
        expect(EditActivityDescriptionController.targets).toEqual(
            expect.arrayContaining(['activityId', 'activityName', 'defaultDescription', 'customDescription'])
        );
    });

    test('connect adds event listener to modal', () => {
        const modal = document.getElementById('editActivityDescriptionModal');
        const addSpy = jest.spyOn(modal, 'addEventListener');
        
        controller.connect();

        expect(addSpy).toHaveBeenCalledWith('show.bs.modal', expect.any(Function));
    });

    test('connect handles missing modal gracefully', () => {
        document.getElementById('editActivityDescriptionModal').remove();
        expect(() => controller.connect()).not.toThrow();
    });

    test('handleModalShow populates fields from button data', () => {
        const button = document.createElement('button');
        button.setAttribute('data-activity-id', '42');
        button.setAttribute('data-activity-name', 'Archery');
        button.setAttribute('data-default-description', 'Default desc');
        button.setAttribute('data-custom-description', 'Custom desc');

        const event = { relatedTarget: button };

        controller.handleModalShow(event);

        expect(controller.activityIdTarget.value).toBe('42');
        expect(controller.activityNameTarget.textContent).toBe('Archery');
        expect(controller.defaultDescriptionTarget.textContent).toBe('Default desc');
        expect(controller.customDescriptionTarget.value).toBe('Custom desc');
    });

    test('handleModalShow uses fallback when default description is empty', () => {
        const button = document.createElement('button');
        button.setAttribute('data-activity-id', '1');
        button.setAttribute('data-activity-name', 'Test');
        button.setAttribute('data-default-description', '');
        button.setAttribute('data-custom-description', '');

        controller.handleModalShow({ relatedTarget: button });

        expect(controller.defaultDescriptionTarget.textContent).toBe('No default description');
        expect(controller.customDescriptionTarget.value).toBe('');
    });

    test('handleModalShow does nothing when no relatedTarget', () => {
        controller.activityIdTarget.value = 'original';
        controller.handleModalShow({ relatedTarget: null });
        expect(controller.activityIdTarget.value).toBe('original');
    });

    test('disconnect removes event listener from modal', () => {
        controller.connect();
        const modal = document.getElementById('editActivityDescriptionModal');
        const removeSpy = jest.spyOn(modal, 'removeEventListener');

        controller.disconnect();

        expect(removeSpy).toHaveBeenCalledWith('show.bs.modal', expect.any(Function));
        expect(controller.boundHandleModalShow).toBeNull();
    });

    test('disconnect handles missing modal gracefully', () => {
        controller.connect();
        document.getElementById('editActivityDescriptionModal').remove();
        expect(() => controller.disconnect()).not.toThrow();
    });
});
