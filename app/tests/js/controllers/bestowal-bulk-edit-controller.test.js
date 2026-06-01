import '../../../plugins/Awards/Assets/js/controllers/bestowal-bulk-edit-controller.js';

const BestowalBulkEditController = window.Controllers['awards-bestowal-bulk-edit'];

describe('AwardsBestowalBulkEditForm', () => {
    let controller;
    let root;
    let form;
    let bulkIds;
    let state;
    let submitButton;

    beforeEach(() => {
        document.body.replaceChildren();

        root = document.createElement('div');
        root.id = 'bestowal_bulk_edit_root';
        root.dataset.awardsBestowalBulkEditFormUrlValue = '/awards/bestowals/updateStates';
        root.dataset.awardsBestowalBulkEditGatheringsLookupUrlValue = '/awards/bestowals/gatherings-for-bestowal-bulk-auto-complete';

        form = document.createElement('form');
        form.id = 'bestowal_bulk_form';
        form.setAttribute('action', '/awards/bestowals/update-states');
        form.checkValidity = jest.fn(() => true);

        bulkIds = document.createElement('input');
        bulkIds.type = 'hidden';
        bulkIds.setAttribute('data-awards-bestowal-bulk-edit-target', 'bulkIds');
        form.appendChild(bulkIds);

        state = document.createElement('select');
        state.setAttribute('data-awards-bestowal-bulk-edit-target', 'state');
        const option = document.createElement('option');
        option.value = 'Court Scheduled';
        option.selected = true;
        state.appendChild(option);
        form.appendChild(state);

        submitButton = document.createElement('button');
        submitButton.setAttribute('data-awards-bestowal-bulk-edit-target', 'submitButton');
        form.appendChild(submitButton);

        root.appendChild(form);
        document.body.appendChild(root);

        controller = new BestowalBulkEditController();
        controller.element = root;
        controller.bulkIdsTarget = bulkIds;
        controller.stateTarget = state;
        controller.submitButtonTarget = submitButton;
        controller.formUrlValue = '/awards/bestowals/updateStates';
        controller.gatheringsLookupUrlValue = '/awards/bestowals/gatherings-for-bestowal-bulk-auto-complete';
        controller.hasBulkIdsTarget = true;
        controller.hasStateTarget = true;
        controller.hasSubmitButtonTarget = true;
        controller.hasPlanToGiveGatheringTarget = false;
        controller.hasGatheringsLookupUrlValue = true;
    });

    test('setId updates the nested form action when controller is on wrapper element', () => {
        expect(() => controller.setId({ detail: { ids: [3, 7] } })).not.toThrow();

        expect(controller.bulkIdsValue).toEqual([3, 7]);
        expect(bulkIds.value).toBe('3,7');
        expect(form.getAttribute('action')).toBe('/awards/bestowals/updateStates');
        expect(submitButton.disabled).toBe(false);
    });

    test('isFormSubmittable checks the nested form validity', () => {
        controller.bulkIdsValue = [3];

        expect(controller.isFormSubmittable()).toBe(true);
        expect(form.checkValidity).toHaveBeenCalled();
    });
});
