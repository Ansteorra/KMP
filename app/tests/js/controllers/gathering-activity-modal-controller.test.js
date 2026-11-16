import GatheringActivityModalController from '../../../assets/js/controllers/gathering-activity-modal-controller.js';

describe('Shared gathering activity modal loader', () => {
    let controller;
    beforeEach(() => {
        document.body.innerHTML = `<div class="modal show"><h2 id="title"></h2><turbo-frame id="frame"></turbo-frame></div>
            <template id="loading"><p role="status">Loading activity</p></template>
            <template id="error"><p role="alert">Unable to load activity</p></template>`;
        controller = new GatheringActivityModalController();
        controller.element = document.querySelector('.modal');
        controller.frameTarget = document.getElementById('frame');
        controller.titleTarget = document.getElementById('title');
        controller.loadingTarget = document.getElementById('loading');
        controller.errorTarget = document.getElementById('error');
        controller.addUrlValue = '/gathering-activities/add';
        controller.editUrlValue = '/gathering-activities/edit';
        controller.addTitleValue = 'Add Gathering Activity';
        controller.editTitleValue = 'Edit Gathering Activity';
    });
    afterEach(() => { document.body.innerHTML = ''; });

    test('add and edit use the same frame and discard previous form values before loading', () => {
        controller.frameTarget.innerHTML = '<input value="old activity">';
        controller.load({ relatedTarget: { dataset: { activityId: '37' } } });
        expect(controller.frameTarget.src).toBe('/gathering-activities/edit/37');
        expect(controller.titleTarget).toHaveTextContent('Edit Gathering Activity');
        expect(controller.frameTarget.querySelector('input')).toBeNull();
        expect(controller.frameTarget.querySelector('[role="status"]')).not.toBeNull();
        controller.load({ relatedTarget: { dataset: {} } });
        expect(controller.frameTarget.src).toBe('/gathering-activities/add');
        expect(controller.titleTarget).toHaveTextContent('Add Gathering Activity');
    });

    test('focus moves into the loaded form only while the dialog is shown', () => {
        controller.frameTarget.innerHTML = '<input name="name">';
        const input = controller.frameTarget.querySelector('input');
        controller.focusForm();
        expect(input).toHaveFocus();
        input.blur();
        controller.element.classList.remove('show');
        controller.focusForm();
        expect(input).not.toHaveFocus();
    });

    test('missing frames show a readable error inside the dialog', () => {
        const event = { preventDefault: jest.fn() };
        controller.missing(event);
        expect(event.preventDefault).toHaveBeenCalled();
        expect(controller.frameTarget.querySelector('[role="alert"]')).toHaveTextContent('Unable to load activity');
    });

    test('validation replacement focuses the invalid setting inside the open dialog', () => {
        controller.frameTarget.innerHTML = '<input name="name"><div class="is-invalid"><input name="display_order" class="is-invalid"></div>';
        controller.formTargetConnected();
        expect(controller.frameTarget.querySelector('[name="display_order"]')).toHaveFocus();
    });
});
