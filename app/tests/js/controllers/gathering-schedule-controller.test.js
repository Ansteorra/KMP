// Mock bootstrap.Modal
window.bootstrap = {
    ...window.bootstrap,
    Modal: jest.fn().mockImplementation(() => ({
        show: jest.fn(),
        hide: jest.fn()
    }))
};
window.bootstrap.Modal.getInstance = jest.fn(() => ({
    hide: jest.fn()
}));

import '../../../assets/js/controllers/gathering-schedule-controller.js';

const GatheringScheduleController = window.Controllers['gathering-schedule'];

describe('GatheringScheduleController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="gathering-schedule"
                 data-gathering-schedule-gathering-id-value="10"
                 data-gathering-schedule-gathering-start-value="2025-06-01T09:00"
                 data-gathering-schedule-gathering-end-value="2025-06-03T17:00"
                 data-gathering-schedule-add-url-value="/gatherings/10/schedule/add"
                 data-gathering-schedule-edit-url-value="/gatherings/schedule/edit/__ID__"
                 data-gathering-schedule-delete-url-value="/gatherings/schedule/delete">
                <div data-gathering-schedule-target="scheduleList"></div>
                <div data-gathering-schedule-target="addModal"></div>
                <div data-gathering-schedule-target="editModal"></div>
                <select data-gathering-schedule-target="activitySelect">
                    <option value="">Select</option>
                    <option value="1">Archery</option>
                </select>
                <input type="checkbox" data-gathering-schedule-target="isOtherCheckbox">
                <form data-gathering-schedule-target="addForm">
                    <input type="text" name="display_title" value="">
                </form>
                <form data-gathering-schedule-target="editForm" action="/gatherings/schedule/edit/1">
                    <input type="hidden" name="gathering_activity_id" value="">
                    <input type="text" name="display_title" value="">
                    <input type="text" name="description" value="">
                    <input type="datetime-local" name="start_datetime" value="">
                    <input type="datetime-local" name="end_datetime" value="">
                </form>
                <select data-gathering-schedule-target="editActivitySelect">
                    <option value="">Select</option>
                    <option value="1">Archery</option>
                </select>
                <input type="checkbox" data-gathering-schedule-target="editIsOtherCheckbox">
                <input type="datetime-local" data-gathering-schedule-target="startDatetime" value="">
                <input type="datetime-local" data-gathering-schedule-target="endDatetime" value="">
                <input type="datetime-local" data-gathering-schedule-target="editStartDatetime" value="">
                <input type="datetime-local" data-gathering-schedule-target="editEndDatetime" value="">
                <input type="checkbox" data-gathering-schedule-target="hasEndTimeCheckbox">
                <input type="checkbox" data-gathering-schedule-target="editHasEndTimeCheckbox">
                <div data-gathering-schedule-target="endTimeContainer" style="display: none;"></div>
                <div data-gathering-schedule-target="editEndTimeContainer" style="display: none;"></div>
                <input type="checkbox" id="edit-pre-register">
                <input type="checkbox" id="edit-is-other">
                <input type="checkbox" id="edit-has-end-time">
            </div>
        `;

        controller = new GatheringScheduleController();
        controller.element = document.querySelector('[data-controller="gathering-schedule"]');

        // Wire targets
        controller.scheduleListTarget = document.querySelector('[data-gathering-schedule-target="scheduleList"]');
        controller.addModalTarget = document.querySelector('[data-gathering-schedule-target="addModal"]');
        controller.editModalTarget = document.querySelector('[data-gathering-schedule-target="editModal"]');
        controller.activitySelectTarget = document.querySelector('[data-gathering-schedule-target="activitySelect"]');
        controller.isOtherCheckboxTarget = document.querySelector('[data-gathering-schedule-target="isOtherCheckbox"]');
        controller.addFormTarget = document.querySelector('[data-gathering-schedule-target="addForm"]');
        controller.editFormTarget = document.querySelector('[data-gathering-schedule-target="editForm"]');
        controller.editActivitySelectTarget = document.querySelector('[data-gathering-schedule-target="editActivitySelect"]');
        controller.editIsOtherCheckboxTarget = document.querySelector('[data-gathering-schedule-target="editIsOtherCheckbox"]');
        controller.startDatetimeTarget = document.querySelector('[data-gathering-schedule-target="startDatetime"]');
        controller.endDatetimeTarget = document.querySelector('[data-gathering-schedule-target="endDatetime"]');
        controller.editStartDatetimeTarget = document.querySelector('[data-gathering-schedule-target="editStartDatetime"]');
        controller.editEndDatetimeTarget = document.querySelector('[data-gathering-schedule-target="editEndDatetime"]');
        controller.hasEndTimeCheckboxTarget = document.querySelector('[data-gathering-schedule-target="hasEndTimeCheckbox"]');
        controller.editHasEndTimeCheckboxTarget = document.querySelector('[data-gathering-schedule-target="editHasEndTimeCheckbox"]');
        controller.endTimeContainerTarget = document.querySelector('[data-gathering-schedule-target="endTimeContainer"]');
        controller.editEndTimeContainerTarget = document.querySelector('[data-gathering-schedule-target="editEndTimeContainer"]');

        // Wire has* flags
        controller.hasStartDatetimeTarget = true;
        controller.hasEndDatetimeTarget = true;
        controller.hasEditStartDatetimeTarget = true;
        controller.hasEditEndDatetimeTarget = true;
        controller.hasEndTimeContainerTarget = true;
        controller.hasEditEndTimeContainerTarget = true;

        // Wire values
        controller.gatheringIdValue = 10;
        controller.gatheringStartValue = '2025-06-01T09:00';
        controller.gatheringEndValue = '2025-06-03T17:00';
        controller.addUrlValue = '/gatherings/10/schedule/add';
        controller.editUrlValue = '/gatherings/schedule/edit/__ID__';
        controller.deleteUrlValue = '/gatherings/schedule/delete';
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
        delete global.fetch;
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['gathering-schedule']).toBe(GatheringScheduleController);
    });

    test('has correct static targets', () => {
        expect(GatheringScheduleController.targets).toEqual(
            expect.arrayContaining([
                'scheduleList', 'addModal', 'editModal', 'activitySelect',
                'isOtherCheckbox', 'addForm', 'editForm', 'startDatetime', 'endDatetime'
            ])
        );
    });

    test('has correct static values', () => {
        expect(GatheringScheduleController.values).toHaveProperty('gatheringId', Number);
        expect(GatheringScheduleController.values).toHaveProperty('gatheringStart', String);
        expect(GatheringScheduleController.values).toHaveProperty('addUrl', String);
        expect(GatheringScheduleController.values).toHaveProperty('editUrl', String);
        expect(GatheringScheduleController.values).toHaveProperty('deleteUrl', String);
    });

    // setupDateTimeLimits tests
    test('setupDateTimeLimits sets min/max on datetime inputs', () => {
        controller.setupDateTimeLimits();
        expect(controller.startDatetimeTarget.min).toBe('2025-06-01T09:00');
        expect(controller.startDatetimeTarget.max).toBe('2025-06-03T17:00');
        expect(controller.endDatetimeTarget.min).toBe('2025-06-01T09:00');
        expect(controller.endDatetimeTarget.max).toBe('2025-06-03T17:00');
    });

    test('setupDateTimeLimits defaults start to gathering start when empty', () => {
        controller.startDatetimeTarget.value = '';
        controller.setupDateTimeLimits();
        expect(controller.startDatetimeTarget.value).toBe('2025-06-01T09:00');
    });

    test('setupDateTimeLimits skips with invalid date format', () => {
        controller.gatheringStartValue = 'invalid';
        controller.setupDateTimeLimits();
        expect(controller.startDatetimeTarget.min).toBe('');
    });

    test('setupDateTimeLimits also sets limits on edit fields', () => {
        controller.setupDateTimeLimits();
        expect(controller.editStartDatetimeTarget.min).toBe('2025-06-01T09:00');
        expect(controller.editEndDatetimeTarget.max).toBe('2025-06-03T17:00');
    });

    // resetAddForm tests
    test('resetAddForm calls setupDateTimeLimits and resets start', () => {
        const spy = jest.spyOn(controller, 'setupDateTimeLimits');
        controller.resetAddForm({});
        expect(spy).toHaveBeenCalled();
        expect(controller.startDatetimeTarget.value).toBe('2025-06-01T09:00');
        expect(controller.endDatetimeTarget.value).toBe('');
    });

    test('resetAddForm skips when invalid dates', () => {
        controller.gatheringStartValue = '';
        controller.resetAddForm({});
        // Should not throw
    });

    // handleOtherChange tests
    test('handleOtherChange disables activity select when checked', () => {
        controller.handleOtherChange({ target: { checked: true } });
        expect(controller.activitySelectTarget.disabled).toBe(true);
        expect(controller.activitySelectTarget.required).toBe(false);
        expect(controller.activitySelectTarget.value).toBe('');
    });

    test('handleOtherChange enables activity select when unchecked', () => {
        controller.activitySelectTarget.disabled = true;
        controller.handleOtherChange({ target: { checked: false } });
        expect(controller.activitySelectTarget.disabled).toBe(false);
        expect(controller.activitySelectTarget.required).toBe(true);
    });

    // handleEditOtherChange tests
    test('handleEditOtherChange disables edit activity select when checked', () => {
        controller.handleEditOtherChange({ target: { checked: true } });
        expect(controller.editActivitySelectTarget.disabled).toBe(true);
        expect(controller.editActivitySelectTarget.required).toBe(false);
    });

    // toggleEndTime tests
    test('toggleEndTime shows container when checked', () => {
        controller.toggleEndTime({ target: { checked: true } });
        expect(controller.endTimeContainerTarget.style.display).toBe('block');
    });

    test('toggleEndTime hides container and clears value when unchecked', () => {
        controller.endDatetimeTarget.value = '2025-06-01T10:00';
        controller.toggleEndTime({ target: { checked: false } });
        expect(controller.endTimeContainerTarget.style.display).toBe('none');
        expect(controller.endDatetimeTarget.value).toBe('');
    });

    test('toggleEndTime sets default end to 1 hour after start when checked', () => {
        controller.startDatetimeTarget.value = '2025-06-01T09:00';
        controller.toggleEndTime({ target: { checked: true } });
        expect(controller.endDatetimeTarget.value).toBe('2025-06-01T10:00');
    });

    // toggleEditEndTime tests
    test('toggleEditEndTime shows/hides edit container', () => {
        controller.toggleEditEndTime({ target: { checked: true } });
        expect(controller.editEndTimeContainerTarget.style.display).toBe('block');

        controller.editEndDatetimeTarget.value = '2025-06-01T10:00';
        controller.toggleEditEndTime({ target: { checked: false } });
        expect(controller.editEndTimeContainerTarget.style.display).toBe('none');
        expect(controller.editEndDatetimeTarget.value).toBe('');
    });

    // normalizeErrors tests
    test('normalizeErrors handles array errors', () => {
        expect(controller.normalizeErrors({ errors: ['Error 1', 'Error 2'] })).toBe('Error 1, Error 2');
    });

    test('normalizeErrors handles string errors', () => {
        expect(controller.normalizeErrors({ errors: 'Something went wrong' })).toBe('Something went wrong');
    });

    test('normalizeErrors handles object errors', () => {
        const result = controller.normalizeErrors({
            errors: { name: ['Required'], date: ['Invalid'] }
        });
        expect(result).toBe('Required, Invalid');
    });

    test('normalizeErrors falls back to message', () => {
        expect(controller.normalizeErrors({ message: 'Fallback' })).toBe('Fallback');
    });

    test('normalizeErrors uses default when no errors or message', () => {
        expect(controller.normalizeErrors({})).toBe('An error occurred');
    });

    // formatDate
    test('formatDate formats date correctly', () => {
        const date = new Date('2025-06-15T14:30:00');
        const formatted = controller.formatDate(date);
        expect(formatted).toContain('Jun');
        expect(formatted).toContain('15');
        expect(formatted).toContain('2025');
    });

    // showFlashMessage
    test('showFlashMessage creates success alert', () => {
        controller.showFlashMessage('success', 'Saved!');
        const alert = document.querySelector('.alert-success');
        expect(alert).not.toBeNull();
        expect(alert.textContent).toContain('Saved!');
    });

    test('showFlashMessage creates error alert', () => {
        controller.showFlashMessage('error', 'Failed!');
        const alert = document.querySelector('.alert-danger');
        expect(alert).not.toBeNull();
    });

    test('showFlashMessage auto-dismisses after 5 seconds', () => {
        jest.useFakeTimers();
        controller.showFlashMessage('success', 'Temp message');
        expect(document.querySelector('.alert')).not.toBeNull();
        jest.advanceTimersByTime(5000);
        expect(document.querySelector('.alert')).toBeNull();
        jest.useRealTimers();
    });

    test('createFlashContainer creates container when none exists', () => {
        const container = controller.createFlashContainer();
        expect(container.classList.contains('flash-messages')).toBe(true);
        expect(document.body.contains(container)).toBe(true);
    });

    // submitAddForm tests
    test('submitAddForm sends POST request', async () => {
        global.fetch = jest.fn(() =>
            Promise.resolve({
                json: () => Promise.resolve({ success: false, errors: ['test'] })
            })
        );

        const event = {
            preventDefault: jest.fn(),
            target: controller.addFormTarget
        };

        await controller.submitAddForm(event);

        expect(event.preventDefault).toHaveBeenCalled();
        expect(global.fetch).toHaveBeenCalledWith(
            '/gatherings/10/schedule/add',
            expect.objectContaining({
                method: 'POST',
                headers: expect.objectContaining({
                    'X-Requested-With': 'XMLHttpRequest'
                })
            })
        );
    });

    test('submitAddForm shows error on failure', async () => {
        global.fetch = jest.fn(() =>
            Promise.resolve({
                json: () => Promise.resolve({ success: false, errors: ['Invalid data'] })
            })
        );

        const spy = jest.spyOn(controller, 'showFlashMessage');
        const event = {
            preventDefault: jest.fn(),
            target: controller.addFormTarget
        };

        await controller.submitAddForm(event);

        expect(spy).toHaveBeenCalledWith('error', 'Invalid data');
    });

    test('submitAddForm handles fetch error', async () => {
        global.fetch = jest.fn(() => Promise.reject(new Error('Network error')));
        const spy = jest.spyOn(controller, 'showFlashMessage');

        const event = {
            preventDefault: jest.fn(),
            target: controller.addFormTarget
        };

        await controller.submitAddForm(event);

        expect(spy).toHaveBeenCalledWith('error', expect.stringContaining('error occurred'));
    });

    // submitEditForm tests
    test('submitEditForm sends POST request to form action', async () => {
        global.fetch = jest.fn(() =>
            Promise.resolve({
                json: () => Promise.resolve({ success: false, message: 'Bad request' })
            })
        );

        const event = {
            preventDefault: jest.fn(),
            target: controller.editFormTarget
        };

        await controller.submitEditForm(event);

        expect(global.fetch).toHaveBeenCalledWith(
            expect.stringContaining('/gatherings/schedule/edit/1'),
            expect.objectContaining({ method: 'POST' })
        );
    });

    test('submitEditForm shows error on failure', async () => {
        global.fetch = jest.fn(() =>
            Promise.resolve({
                json: () => Promise.resolve({ success: false, message: 'Bad request' })
            })
        );

        const spy = jest.spyOn(controller, 'showFlashMessage');
        const event = {
            preventDefault: jest.fn(),
            target: controller.editFormTarget
        };

        await controller.submitEditForm(event);

        expect(spy).toHaveBeenCalledWith('error', 'Bad request');
    });

    // openEditModal tests
    test('openEditModal populates form from button data', () => {
        const button = document.createElement('button');
        button.dataset.activityId = '5';
        button.dataset.activityName = 'Archery';
        button.dataset.gatheringActivityId = '99';
        button.dataset.startDatetime = '2025-06-01T10:00';
        button.dataset.endDatetime = '2025-06-01T11:00';
        button.dataset.displayTitle = 'Morning Archery';
        button.dataset.description = 'Practice session';
        button.dataset.preRegister = 'false';
        button.dataset.isOther = 'false';
        button.dataset.hasEndTime = 'true';

        const event = {
            preventDefault: jest.fn(),
            currentTarget: button
        };

        controller.openEditModal(event);

        expect(event.preventDefault).toHaveBeenCalled();
        expect(controller.editFormTarget.action).toContain('/gatherings/schedule/edit/5');
        expect(controller.editFormTarget.querySelector('[name="gathering_activity_id"]').value).toBe('99');
        expect(controller.editFormTarget.querySelector('[name="display_title"]').value).toBe('Morning Archery');
        expect(controller.editEndTimeContainerTarget.style.display).toBe('block');
    });

    test('openEditModal handles is_other checkbox correctly', () => {
        const button = document.createElement('button');
        button.dataset.activityId = '5';
        button.dataset.activityName = 'Custom';
        button.dataset.gatheringActivityId = '';
        button.dataset.startDatetime = '2025-06-01T10:00';
        button.dataset.endDatetime = '';
        button.dataset.displayTitle = 'Custom Activity';
        button.dataset.description = '';
        button.dataset.preRegister = 'false';
        button.dataset.isOther = 'true';
        button.dataset.hasEndTime = 'false';

        const event = {
            preventDefault: jest.fn(),
            currentTarget: button
        };

        controller.openEditModal(event);

        expect(controller.editActivitySelectTarget.disabled).toBe(true);
        expect(controller.editActivitySelectTarget.required).toBe(false);
        expect(document.getElementById('edit-is-other').checked).toBe(true);
    });
});
