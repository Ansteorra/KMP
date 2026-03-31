import DeleteConfirmationController from '../../../assets/js/controllers/delete-confirmation-controller.js';

describe('DeleteConfirmationController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="delete-confirmation"
                 data-delete-confirmation-item-type-value="member"
                 data-delete-confirmation-item-name-value="Test Member">
                <button data-action="click->delete-confirmation#confirm">Delete</button>
            </div>
        `;

        controller = new DeleteConfirmationController();
        controller.element = document.querySelector('[data-controller="delete-confirmation"]');
        controller.itemTypeValue = 'member';
        controller.itemNameValue = 'Test Member';
        controller.hasItemNameValue = true;
        controller.hasReferencesValue = false;
        controller.referenceCountValue = 0;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['delete-confirmation']).toBe(DeleteConfirmationController);
    });

    test('has correct static values', () => {
        expect(DeleteConfirmationController.values).toHaveProperty('itemType', String);
        expect(DeleteConfirmationController.values).toHaveProperty('itemName', String);
        expect(DeleteConfirmationController.values.hasReferences).toEqual({ type: Boolean, default: false });
        expect(DeleteConfirmationController.values.referenceCount).toEqual({ type: Number, default: 0 });
    });

    test('buildConfirmMessage includes item name when present', () => {
        const message = controller.buildConfirmMessage();
        expect(message).toContain('Are you sure you want to delete "Test Member"?');
    });

    test('buildConfirmMessage uses item type when name not present', () => {
        controller.hasItemNameValue = false;
        const message = controller.buildConfirmMessage();
        expect(message).toContain('Are you sure you want to delete this member?');
    });

    test('buildConfirmMessage includes undo warning', () => {
        const message = controller.buildConfirmMessage();
        expect(message).toContain('This action cannot be undone.');
    });

    test('buildConfirmMessage includes reference warning for single reference', () => {
        controller.hasReferencesValue = true;
        controller.referenceCountValue = 1;
        const message = controller.buildConfirmMessage();
        expect(message).toContain('1 other item');
    });

    test('buildConfirmMessage includes reference warning for multiple references', () => {
        controller.hasReferencesValue = true;
        controller.referenceCountValue = 5;
        const message = controller.buildConfirmMessage();
        expect(message).toContain('5 other items');
    });

    test('buildConfirmMessage omits reference warning when no references', () => {
        controller.hasReferencesValue = false;
        const message = controller.buildConfirmMessage();
        expect(message).not.toContain('referenced by');
    });

    test('confirm prevents default when user cancels', () => {
        jest.spyOn(window, 'confirm').mockReturnValue(false);
        const event = { preventDefault: jest.fn(), stopPropagation: jest.fn() };
        const result = controller.confirm(event);
        expect(event.preventDefault).toHaveBeenCalled();
        expect(event.stopPropagation).toHaveBeenCalled();
        expect(result).toBe(false);
    });

    test('confirm allows action when user confirms', () => {
        jest.spyOn(window, 'confirm').mockReturnValue(true);
        const event = { preventDefault: jest.fn(), stopPropagation: jest.fn() };
        const result = controller.confirm(event);
        expect(event.preventDefault).not.toHaveBeenCalled();
        expect(result).toBe(true);
    });

    test('confirm passes built message to window.confirm', () => {
        const confirmSpy = jest.spyOn(window, 'confirm').mockReturnValue(true);
        controller.confirm({ preventDefault: jest.fn(), stopPropagation: jest.fn() });
        expect(confirmSpy).toHaveBeenCalledWith(expect.stringContaining('Test Member'));
    });
});
