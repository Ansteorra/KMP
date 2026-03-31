import '../../../assets/js/controllers/activity-waiver-manager-controller.js';
const ActivityWaiverManagerController = window.Controllers['activity-waiver-manager'];

describe('ActivityWaiverManagerController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="activity-waiver-manager"
                 data-activity-waiver-manager-min-waivers-value="0"
                 data-activity-waiver-manager-max-waivers-value="99">
                <div data-activity-waiver-manager-target="waiverList">
                    <div class="form-check">
                        <input type="checkbox" value="1"
                               data-activity-waiver-manager-target="waiverCheckbox">
                        Waiver A
                    </div>
                    <div class="form-check">
                        <input type="checkbox" value="2"
                               data-activity-waiver-manager-target="waiverCheckbox">
                        Waiver B
                    </div>
                    <div class="form-check">
                        <input type="checkbox" value="3"
                               data-activity-waiver-manager-target="waiverCheckbox">
                        Waiver C
                    </div>
                </div>
                <span data-activity-waiver-manager-target="selectedCount"></span>
            </div>
        `;

        controller = new ActivityWaiverManagerController();
        controller.element = document.querySelector('[data-controller="activity-waiver-manager"]');
        controller.waiverCheckboxTargets = Array.from(document.querySelectorAll('[data-activity-waiver-manager-target="waiverCheckbox"]'));
        controller.selectedCountTarget = document.querySelector('[data-activity-waiver-manager-target="selectedCount"]');
        controller.hasSelectedCountTarget = true;
        controller.waiverListTarget = document.querySelector('[data-activity-waiver-manager-target="waiverList"]');
        controller.hasWaiverListTarget = true;
        controller.minWaiversValue = 0;
        controller.maxWaiversValue = 99;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    // --- Static properties ---

    test('has correct static targets', () => {
        expect(ActivityWaiverManagerController.targets).toEqual(
            expect.arrayContaining(['waiverCheckbox', 'selectedCount', 'waiverList'])
        );
    });

    test('has correct static values', () => {
        expect(ActivityWaiverManagerController.values).toHaveProperty('minWaivers');
        expect(ActivityWaiverManagerController.values).toHaveProperty('maxWaivers');
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['activity-waiver-manager']).toBe(ActivityWaiverManagerController);
    });

    // --- connect ---

    test('connect calls updateSelectedCount and updateVisualState', () => {
        const countSpy = jest.spyOn(controller, 'updateSelectedCount');
        const visualSpy = jest.spyOn(controller, 'updateVisualState');
        controller.connect();
        expect(countSpy).toHaveBeenCalled();
        expect(visualSpy).toHaveBeenCalled();
    });

    // --- updateSelectedCount ---

    test('updateSelectedCount shows "No waivers selected" for zero', () => {
        controller.updateSelectedCount();
        expect(controller.selectedCountTarget.textContent).toBe('No waivers selected');
    });

    test('updateSelectedCount shows "1 waiver selected" for one', () => {
        controller.waiverCheckboxTargets[0].checked = true;
        controller.updateSelectedCount();
        expect(controller.selectedCountTarget.textContent).toBe('1 waiver selected');
    });

    test('updateSelectedCount shows "N waivers selected" for multiple', () => {
        controller.waiverCheckboxTargets[0].checked = true;
        controller.waiverCheckboxTargets[1].checked = true;
        controller.updateSelectedCount();
        expect(controller.selectedCountTarget.textContent).toBe('2 waivers selected');
    });

    test('updateSelectedCount does nothing without target', () => {
        controller.hasSelectedCountTarget = false;
        controller.updateSelectedCount();
        // Should not throw
    });

    // --- updateVisualState ---

    test('updateVisualState adds selected class to checked items', () => {
        controller.waiverCheckboxTargets[0].checked = true;
        controller.waiverCheckboxTargets[1].checked = false;

        controller.updateVisualState();

        const container0 = controller.waiverCheckboxTargets[0].closest('.form-check');
        const container1 = controller.waiverCheckboxTargets[1].closest('.form-check');
        expect(container0.classList.contains('selected')).toBe(true);
        expect(container1.classList.contains('selected')).toBe(false);
    });

    // --- getSelectedWaivers ---

    test('getSelectedWaivers returns checked waiver values', () => {
        controller.waiverCheckboxTargets[0].checked = true;
        controller.waiverCheckboxTargets[2].checked = true;

        const selected = controller.getSelectedWaivers();

        expect(selected).toEqual(['1', '3']);
    });

    test('getSelectedWaivers returns empty array when none checked', () => {
        expect(controller.getSelectedWaivers()).toEqual([]);
    });

    // --- validateSelection ---

    test('validateSelection returns true when within bounds', () => {
        controller.waiverCheckboxTargets[0].checked = true;
        expect(controller.validateSelection()).toBe(true);
    });

    test('validateSelection returns true for zero when min is 0', () => {
        expect(controller.validateSelection()).toBe(true);
    });

    test('validateSelection adds is-invalid class when exceeds max', () => {
        controller.maxWaiversValue = 1;
        controller.waiverCheckboxTargets[0].checked = true;
        controller.waiverCheckboxTargets[1].checked = true;

        const result = controller.validateSelection();

        expect(result).toBe(false);
        expect(controller.waiverListTarget.classList.contains('is-invalid')).toBe(true);
    });

    test('validateSelection removes is-invalid class when valid', () => {
        controller.waiverListTarget.classList.add('is-invalid');
        controller.waiverCheckboxTargets[0].checked = true;

        controller.validateSelection();

        expect(controller.waiverListTarget.classList.contains('is-invalid')).toBe(false);
    });

    // --- toggleWaiver ---

    test('toggleWaiver updates count, visual state, and validates', () => {
        const countSpy = jest.spyOn(controller, 'updateSelectedCount');
        const visualSpy = jest.spyOn(controller, 'updateVisualState');
        const validateSpy = jest.spyOn(controller, 'validateSelection');

        controller.toggleWaiver({});

        expect(countSpy).toHaveBeenCalled();
        expect(visualSpy).toHaveBeenCalled();
        expect(validateSpy).toHaveBeenCalled();
    });

    // --- selectAll ---

    test('selectAll checks all waivers', () => {
        controller.selectAll();

        controller.waiverCheckboxTargets.forEach(cb => {
            expect(cb.checked).toBe(true);
        });
        expect(controller.selectedCountTarget.textContent).toBe('3 waivers selected');
    });

    // --- deselectAll ---

    test('deselectAll unchecks all waivers', () => {
        controller.waiverCheckboxTargets.forEach(cb => { cb.checked = true; });
        controller.deselectAll();

        controller.waiverCheckboxTargets.forEach(cb => {
            expect(cb.checked).toBe(false);
        });
        expect(controller.selectedCountTarget.textContent).toBe('No waivers selected');
    });
});
