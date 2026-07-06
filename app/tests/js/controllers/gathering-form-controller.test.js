import '../../../assets/js/controllers/gathering-form-controller.js';
const GatheringFormController = window.Controllers['gathering-form'];

describe('GatheringFormController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <form data-controller="gathering-form">
                <input type="date" data-gathering-form-target="startDate" value="">
                <input type="date" data-gathering-form-target="endDate" value="">
                <button type="submit" data-gathering-form-target="submitButton">Save</button>
            </form>
        `;

        controller = new GatheringFormController();
        controller.element = document.querySelector('[data-controller="gathering-form"]');
        controller.startDateTarget = document.querySelector('[data-gathering-form-target="startDate"]');
        controller.endDateTarget = document.querySelector('[data-gathering-form-target="endDate"]');
        controller.submitButtonTarget = document.querySelector('[data-gathering-form-target="submitButton"]');
        controller.hasStartDateTarget = true;
        controller.hasEndDateTarget = true;
        controller.hasSubmitButtonTarget = true;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['gathering-form']).toBe(GatheringFormController);
    });

    test('inherits targets from BaseGatheringFormController', () => {
        expect(GatheringFormController.targets).toEqual(
            expect.arrayContaining(['startDate', 'endDate', 'submitButton'])
        );
    });

    test('inherits validateDates', () => {
        expect(typeof controller.validateDates).toBe('function');
    });

    test('inherits startDateChanged', () => {
        expect(typeof controller.startDateChanged).toBe('function');
    });

    test('inherits endDateChanged', () => {
        expect(typeof controller.endDateChanged).toBe('function');
    });

    test('validates dates correctly', () => {
        controller.startDateTarget.value = '2025-07-01';
        controller.endDateTarget.value = '2025-07-05';
        expect(controller.validateDates()).toBe(true);
    });

    test('rejects invalid date range', () => {
        controller.startDateTarget.value = '2025-07-05';
        controller.endDateTarget.value = '2025-07-01';
        expect(controller.validateDates()).toBe(false);
    });

    test('connect calls validateDates', () => {
        const spy = jest.spyOn(controller, 'validateDates').mockReturnValue(true);
        controller.connect();
        expect(spy).toHaveBeenCalled();
    });

    describe('website URL toggle', () => {
        beforeEach(() => {
            const toggle = document.createElement('input');
            toggle.type = 'checkbox';
            const website = document.createElement('input');
            website.type = 'url';
            controller.element.appendChild(toggle);
            controller.element.appendChild(website);
            controller.publicPageToggleTarget = toggle;
            controller.websiteUrlTarget = website;
            controller.hasPublicPageToggleTarget = true;
            controller.hasWebsiteUrlTarget = true;
        });

        test('declares the toggle targets', () => {
            expect(GatheringFormController.targets).toEqual(
                expect.arrayContaining(['publicPageToggle', 'websiteUrl'])
            );
        });

        test('disables website field while public page is enabled', () => {
            controller.publicPageToggleTarget.checked = true;
            controller.publicPageToggled();
            expect(controller.websiteUrlTarget.disabled).toBe(true);

            controller.publicPageToggleTarget.checked = false;
            controller.publicPageToggled();
            expect(controller.websiteUrlTarget.disabled).toBe(false);
        });

        test('connect syncs the initial disabled state', () => {
            jest.spyOn(controller, 'validateDates').mockReturnValue(true);
            controller.publicPageToggleTarget.checked = true;
            controller.connect();
            expect(controller.websiteUrlTarget.disabled).toBe(true);
        });
    });
});
