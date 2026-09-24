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

window.bootstrap.Modal.getOrCreateInstance = jest.fn(() => ({ show: jest.fn() }));

import '../../../assets/js/controllers/gathering-schedule-controller.js';

const GatheringScheduleController = window.Controllers['gathering-schedule'];

describe('GatheringScheduleController', () => {
    let controller;

    beforeEach(() => {
        const timing = prefix => `
            <input type="date" data-gathering-schedule-target="${prefix}StartDate">
            <select data-gathering-schedule-target="${prefix}StartTime">
                ${Array.from({ length: 96 }, (_, index) => {
                    const time = `${String(Math.floor(index / 4)).padStart(2, '0')}:${String(index % 4 * 15).padStart(2, '0')}`;
                    return `<option value="${time}">${time}</option>`;
                }).join('')}
            </select>
            <select data-gathering-schedule-target="${prefix}Duration">
                ${Array.from({ length: 16 }, (_, index) => `<option value="${(index + 1) * 15}">${(index + 1) * 15}</option>`).join('')}
                <option value="other">Other</option>
            </select>
            <input type="hidden" name="start_datetime" data-gathering-schedule-target="${prefix}StartDatetime">
        `.replaceAll('="Start', '="start').replaceAll('="Duration', '="duration');
        document.body.innerHTML = `
            <div data-controller="gathering-schedule">
                <div data-gathering-schedule-target="scheduleList"></div>
                <div data-gathering-schedule-target="addModal"></div>
                <div data-gathering-schedule-target="editModal"></div>
                <form data-gathering-schedule-target="addForm">
                    ${timing('')}
                    <input name="display_title">
                    <select data-gathering-schedule-target="activitySelect"><option value="">Select</option><option value="1">Archery</option></select>
                    <input type="checkbox" data-gathering-schedule-target="isOtherCheckbox">
                </form>
                <form data-gathering-schedule-target="editForm" action="/gatherings/schedule/edit/1">
                    ${timing('edit')}
                    <input name="gathering_activity_id">
                    <input name="display_title">
                    <input name="description">
                    <select data-gathering-schedule-target="editActivitySelect"><option value="">Select</option><option value="1">Archery</option></select>
                    <input type="checkbox" id="edit-is-other" data-gathering-schedule-target="editIsOtherCheckbox">
                    <input type="checkbox" id="edit-pre-register">
                </form>
            </div>`;
        controller = new GatheringScheduleController();
        controller.element = document.querySelector('[data-controller="gathering-schedule"]');
        for (const target of GatheringScheduleController.targets) {
            controller[`${target}Target`] = document.querySelector(`[data-gathering-schedule-target="${target}"]`);
        }

        controller.startDateTargets = [controller.startDateTarget];
        controller.editStartDateTargets = [controller.editStartDateTarget];

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
                'isOtherCheckbox', 'addForm', 'editForm', 'startDatetime', 'startDate', 'startTime', 'duration'
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

    test('date controls use gathering date bounds', () => {
        controller.setupDateTimeLimits();
        expect(controller.startDateTarget.min).toBe('2025-06-01');
        expect(controller.editStartDateTarget.max).toBe('2025-06-03');
    });

    test('new activities round up to the next quarter-hour', () => {
        controller.gatheringStartValue = '2025-06-01T09:06';
        controller.resetAddForm();
        expect(controller.startDatetimeTarget.value).toBe('2025-06-01T09:15');
        expect(controller.durationTarget.value).toBe('60');
    });

    test('rounding handles midnight without browser timezone conversion', () => {
        controller.gatheringStartValue = '2025-06-01T23:59';
        controller.resetAddForm();
        expect(controller.startDatetimeTarget.value).toBe('2025-06-02T00:00');
    });

    test('visible time control rejects starts outside the gathering', () => {
        controller.resetAddForm();
        controller.startTimeTarget.value = '08:45';
        controller.timingChanged({ target: controller.startTimeTarget });
        expect(controller.startTimeTarget.checkValidity()).toBe(false);
        controller.startTimeTarget.value = '09:00';
        controller.timingChanged({ target: controller.startTimeTarget });
        expect(controller.startTimeTarget.checkValidity()).toBe(true);
    });

    test('editing a standard duration selects its elapsed minutes', () => {
        controller.setEditTiming('2026-03-08T01:45', '2026-03-08T03:15', true, '30');
        expect(controller.editDurationTarget.value).toBe('30');
        expect(controller.editDurationTarget.querySelector('option[value="existing"]')).toBeNull();
    });

    test('editing preserves unusual start/end times without leaking choices to another entry', () => {
        controller.setEditTiming('2025-06-01T18:06', '2025-06-01T20:36', true);
        expect(controller.editStartDatetimeTarget.value).toBe('2025-06-01T18:06');
        expect(controller.editDurationTarget.value).toBe('existing');
        expect(controller.editDurationTarget.selectedOptions[0].text).toContain('20:36');
        expect(controller.startTimeTarget.querySelector('option[value="18:06"]')).toBeNull();
        controller.setEditTiming('2025-06-02T09:00', '', false);
        expect(controller.editStartTimeTarget.querySelector('option[value="18:06"]')).toBeNull();
        expect(controller.editDurationTarget.value).toBe('other');
        expect(controller.editDurationTarget.querySelector('option[value="existing"]')).toBeNull();
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
        expect(controller.editDurationTarget.value).toBe('existing');
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
