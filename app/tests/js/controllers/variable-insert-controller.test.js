import '../../../assets/js/controllers/variable-insert-controller.js';

const VariableInsertController = window.Controllers['variable-insert'];

describe('VariableInsertController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="variable-insert">
                <textarea data-variable-insert-target="field">Hello world</textarea>
                <button data-action="variable-insert#insert"
                        data-variable-insert-variable-param="email">Insert email</button>
            </div>
        `;

        controller = new VariableInsertController();
        controller.element = document.querySelector('[data-controller="variable-insert"]');
        controller.fieldTarget = document.querySelector('[data-variable-insert-target="field"]');
        controller.hasFieldTarget = true;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['variable-insert']).toBe(VariableInsertController);
    });

    test('has correct static targets', () => {
        expect(VariableInsertController.targets).toEqual(['field']);
    });

    test('insert inserts variable at cursor position', () => {
        const field = controller.fieldTarget;
        field.value = 'Hello world';
        field.selectionStart = 5;
        field.selectionEnd = 5;

        const event = {
            preventDefault: jest.fn(),
            params: { variable: 'email' }
        };

        controller.insert(event);

        expect(field.value).toBe('Hello{{email}} world');
        expect(event.preventDefault).toHaveBeenCalled();
    });

    test('insert replaces selected text', () => {
        const field = controller.fieldTarget;
        field.value = 'Hello world';
        field.selectionStart = 6;
        field.selectionEnd = 11;

        const event = {
            preventDefault: jest.fn(),
            params: { variable: 'name' }
        };

        controller.insert(event);

        expect(field.value).toBe('Hello {{name}}');
    });

    test('insert at beginning of field', () => {
        const field = controller.fieldTarget;
        field.value = 'Hello';
        field.selectionStart = 0;
        field.selectionEnd = 0;

        const event = {
            preventDefault: jest.fn(),
            params: { variable: 'greeting' }
        };

        controller.insert(event);

        expect(field.value).toBe('{{greeting}}Hello');
    });

    test('insert dispatches input event', () => {
        const field = controller.fieldTarget;
        field.value = 'text';
        field.selectionStart = 4;
        field.selectionEnd = 4;

        const dispatchSpy = jest.spyOn(field, 'dispatchEvent');

        const event = {
            preventDefault: jest.fn(),
            params: { variable: 'var' }
        };

        controller.insert(event);

        expect(dispatchSpy).toHaveBeenCalledWith(
            expect.objectContaining({ type: 'input', bubbles: true })
        );
    });

    test('insert returns early when no variable specified', () => {
        const field = controller.fieldTarget;
        field.value = 'Hello';

        const event = {
            preventDefault: jest.fn(),
            params: {}
        };

        controller.insert(event);

        expect(field.value).toBe('Hello');
    });

    test('insert returns early when no field target', () => {
        controller.hasFieldTarget = false;

        const event = {
            preventDefault: jest.fn(),
            params: { variable: 'email' }
        };

        controller.insert(event);
        // Should not throw
    });

    test('insert focuses the field after insertion', () => {
        const field = controller.fieldTarget;
        field.value = 'text';
        field.selectionStart = 0;
        field.selectionEnd = 0;
        const focusSpy = jest.spyOn(field, 'focus');

        const event = {
            preventDefault: jest.fn(),
            params: { variable: 'x' }
        };

        controller.insert(event);

        expect(focusSpy).toHaveBeenCalled();
    });

    test('insert sets cursor position after inserted variable', () => {
        const field = controller.fieldTarget;
        field.value = 'ab';
        field.selectionStart = 1;
        field.selectionEnd = 1;

        const event = {
            preventDefault: jest.fn(),
            params: { variable: 'v' }
        };

        controller.insert(event);

        expect(field.value).toBe('a{{v}}b');
        expect(field.selectionStart).toBe(1 + '{{v}}'.length);
        expect(field.selectionEnd).toBe(1 + '{{v}}'.length);
    });
});
