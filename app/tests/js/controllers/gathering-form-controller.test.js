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

    describe('branch-specific gathering types', () => {
        beforeEach(() => {
            controller.element.insertAdjacentHTML('beforeend', `
                <div id="branch"><input name="branch_id" value="39"><div class="autocomplete"></div></div>
                <select required id="type"><option value="">Select type</option></select>
                <small id="availability" role="status" aria-live="polite"></small>
                <small id="description"></small>
            `);
            controller.branchTarget = document.querySelector('#branch');
            controller.gatheringTypeSelectTarget = document.querySelector('#type');
            controller.typeAvailabilityTarget = document.querySelector('#availability');
            controller.gatheringTypeDescriptionTarget = document.querySelector('#description');
            controller.hasBranchTarget = true;
            controller.hasGatheringTypeSelectTarget = true;
            controller.hasTypeAvailabilityTarget = true;
            controller.hasGatheringTypeDescriptionTarget = true;
            controller.hasTypesByBranchValue = true;
            controller.typesByBranchValue = {
                39: [{ value: 1, text: 'Practice', 'data-description': 'Local practice' },
                    { value: 2, text: 'Court', 'data-description': 'Court description' }],
                41: [{ value: 1, text: 'Practice', 'data-description': 'Local practice' }],
            };
        });

        test('connect loads only the types authorized for the current branch', () => {
            controller.branchTarget.querySelector('input').value = '41';
            controller.connect();
            expect([...controller.gatheringTypeSelectTarget.options].map(option => option.value)).toEqual(['', '1']);
        });

        test('branch autocomplete changes clear an unauthorized selection and announce it without moving focus', () => {
            controller.syncGatheringTypes();
            controller.gatheringTypeSelectTarget.value = '2';
            const branch = controller.branchTarget.querySelector('input');
            branch.value = '41';
            branch.focus();
            controller.branchChanged({ target: controller.branchTarget.querySelector('.autocomplete') });
            expect(controller.gatheringTypeSelectTarget.value).toBe('');
            expect(controller.gatheringTypeDescriptionTarget.textContent).toBe('');
            expect(controller.typeAvailabilityTarget.textContent).toContain('Choose another type');
            expect(document.activeElement).toBe(branch);
            expect(controller.gatheringTypeSelectTarget.checkValidity()).toBe(false);
        });

        test('preserves a selection that is still permitted in the new branch', () => {
            controller.syncGatheringTypes();
            controller.gatheringTypeSelectTarget.value = '1';
            controller.branchTarget.querySelector('input').value = '41';
            controller.syncGatheringTypes();
            expect(controller.gatheringTypeSelectTarget.value).toBe('1');
            expect(controller.gatheringTypeDescriptionTarget.textContent).toBe('Local practice');
        });

        test('clearing the branch or selecting one with no allowed types leaves no selectable type', () => {
            for (const branchId of ['', '99']) {
                controller.branchTarget.querySelector('input').value = branchId;
                controller.syncGatheringTypes();
                expect(controller.gatheringTypeSelectTarget.options).toHaveLength(1);
                expect(controller.gatheringTypeSelectTarget.checkValidity()).toBe(false);
                expect(controller.typeAvailabilityTarget.textContent).toContain(branchId ? 'No gathering types' : 'Select a branch');
            }
        });

        test('date validation preserves server-side type errors', () => {
            const feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            feedback.textContent = 'Choose an authorized type';
            feedback.style.display = 'block';
            controller.element.appendChild(feedback);
            controller.connect();
            controller.validateDates();
            expect(feedback.style.display).toBe('block');
        });
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
