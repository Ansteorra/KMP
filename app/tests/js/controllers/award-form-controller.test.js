// Controller registers on window.Controllers (no default export)
import '../../../plugins/Awards/Assets/js/controllers/award-form-controller.js';
const AwardsAwardForm = window.Controllers['awards-award-form'];

describe('AwardsAwardForm', () => {
    let controller;

    beforeEach(() => {
        // Mock KMP_utils.sanitizeString
        window.KMP_utils.sanitizeString = jest.fn(str => str);

        document.body.innerHTML = `
            <div data-controller="awards-award-form">
                <input type="text" data-awards-award-form-target="new" value="">
                <input type="hidden" data-awards-award-form-target="formValue" value="">
                <div data-awards-award-form-target="displayList"></div>
            </div>
        `;

        controller = new AwardsAwardForm();
        controller.element = document.querySelector('[data-controller="awards-award-form"]');

        // Wire up targets
        controller.newTarget = document.querySelector('[data-awards-award-form-target="new"]');
        controller.formValueTarget = document.querySelector('[data-awards-award-form-target="formValue"]');
        controller.displayListTarget = document.querySelector('[data-awards-award-form-target="displayList"]');
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    // --- Static properties ---

    test('has correct static targets', () => {
        expect(AwardsAwardForm.targets).toEqual(
            expect.arrayContaining(['new', 'formValue', 'displayList'])
        );
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['awards-award-form']).toBe(AwardsAwardForm);
    });

    // --- initialize ---

    test('initialize creates empty items array', () => {
        controller.initialize();
        expect(controller.items).toEqual([]);
    });

    // --- connect ---

    test('connect restores items from existing form value', () => {
        controller.formValueTarget.value = JSON.stringify(['Sword', 'Shield']);
        controller.connect();

        expect(controller.items).toEqual(['Sword', 'Shield']);
        const listItems = controller.displayListTarget.querySelectorAll('.input-group');
        expect(listItems.length).toBe(2);
    });

    test('connect handles empty form value', () => {
        controller.formValueTarget.value = '';
        controller.connect();
        expect(controller.items).toBeUndefined();
    });

    test('connect handles non-array JSON gracefully', () => {
        controller.formValueTarget.value = JSON.stringify({ not: 'array' });
        controller.connect();
        expect(controller.items).toEqual([]);
    });

    // --- add ---

    test('add appends new item and updates form value', () => {
        controller.initialize();
        controller.newTarget.value = 'New Item';
        const event = { preventDefault: jest.fn() };

        controller.add(event);

        expect(event.preventDefault).toHaveBeenCalled();
        expect(controller.items).toEqual(['New Item']);
        expect(JSON.parse(controller.formValueTarget.value)).toEqual(['New Item']);
        expect(controller.displayListTarget.querySelectorAll('.input-group').length).toBe(1);
        expect(controller.newTarget.value).toBe('');
    });

    test('add ignores empty input', () => {
        controller.initialize();
        controller.newTarget.value = '';
        const event = { preventDefault: jest.fn() };

        controller.add(event);

        expect(controller.items).toEqual([]);
    });

    test('add prevents duplicate items', () => {
        controller.initialize();
        controller.items = ['Existing'];

        controller.newTarget.value = 'Existing';
        const event = { preventDefault: jest.fn() };
        controller.add(event);

        expect(controller.items).toEqual(['Existing']);
    });

    test('add calls KMP_utils.sanitizeString', () => {
        controller.initialize();
        controller.newTarget.value = 'Test Item';
        const event = { preventDefault: jest.fn() };

        controller.add(event);

        expect(window.KMP_utils.sanitizeString).toHaveBeenCalledWith('Test Item');
    });

    // --- remove ---

    test('remove removes item and updates form value', () => {
        controller.initialize();
        controller.items = ['A', 'B', 'C'];
        controller.formValueTarget.value = JSON.stringify(controller.items);

        // Create list items
        ['A', 'B', 'C'].forEach(item => controller.createListItem(item));

        const removeBtn = controller.displayListTarget.querySelectorAll('button')[1]; // Remove 'B'
        const event = {
            preventDefault: jest.fn(),
            target: removeBtn
        };

        controller.remove(event);

        expect(controller.items).toEqual(['A', 'C']);
        expect(JSON.parse(controller.formValueTarget.value)).toEqual(['A', 'C']);
    });

    // --- createListItem ---

    test('createListItem creates input-group with remove button', () => {
        controller.createListItem('Test');

        const group = controller.displayListTarget.querySelector('.input-group');
        expect(group).not.toBeNull();
        expect(group.querySelector('span').innerHTML).toBe('Test');
        expect(group.querySelector('button').innerHTML).toBe('Remove');
        expect(group.querySelector('button').getAttribute('data-id')).toBe('Test');
    });
});
