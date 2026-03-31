// Controller registers on window.Controllers (no default export)
import '../../../assets/js/controllers/permission-add-role-controller.js';
const PermissionAddRoleController = window.Controllers['permission-add-role'];

describe('PermissionAddRoleController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <form data-controller="permission-add-role">
                <input type="text"
                       data-permission-add-role-target="role"
                       data-action="change->permission-add-role#checkSubmitEnable"
                       placeholder="Start typing role name...">
                <button type="submit"
                        data-permission-add-role-target="submitBtn"
                        class="btn btn-primary"
                        disabled>
                    Add Role to Permission
                </button>
            </form>
        `;

        controller = new PermissionAddRoleController();
        controller.element = document.querySelector('[data-controller="permission-add-role"]');

        // Wire up targets
        controller.roleTarget = document.querySelector('[data-permission-add-role-target="role"]');
        controller.submitBtnTarget = document.querySelector('[data-permission-add-role-target="submitBtn"]');
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    // --- Instantiation ---

    test('instantiates with correct static targets', () => {
        expect(PermissionAddRoleController.targets).toEqual(
            expect.arrayContaining(['role', 'form', 'submitBtn'])
        );
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['permission-add-role']).toBe(PermissionAddRoleController);
    });

    // --- checkSubmitEnable: valid role ---

    test('checkSubmitEnable enables button for valid numeric role ID', () => {
        controller.roleTarget.value = '42';
        controller.checkSubmitEnable();
        expect(controller.submitBtnTarget.disabled).toBe(false);
    });

    test('checkSubmitEnable enables button for underscore-separated role ID', () => {
        // Autocomplete might return values like "1_2_3" which become 123
        controller.roleTarget.value = '1_2_3';
        controller.checkSubmitEnable();
        expect(controller.submitBtnTarget.disabled).toBe(false);
    });

    test('checkSubmitEnable focuses submit button when enabled', () => {
        const focusSpy = jest.spyOn(controller.submitBtnTarget, 'focus');
        controller.roleTarget.value = '5';
        controller.checkSubmitEnable();
        expect(focusSpy).toHaveBeenCalled();
    });

    // --- checkSubmitEnable: invalid role ---

    test('checkSubmitEnable disables button for empty value', () => {
        controller.submitBtnTarget.disabled = false;
        controller.roleTarget.value = '';
        controller.checkSubmitEnable();
        expect(controller.submitBtnTarget.disabled).toBe(true);
    });

    test('checkSubmitEnable disables button for zero value', () => {
        controller.submitBtnTarget.disabled = false;
        controller.roleTarget.value = '0';
        controller.checkSubmitEnable();
        expect(controller.submitBtnTarget.disabled).toBe(true);
    });

    test('checkSubmitEnable disables button for non-numeric text', () => {
        controller.submitBtnTarget.disabled = false;
        controller.roleTarget.value = 'admin';
        controller.checkSubmitEnable();
        expect(controller.submitBtnTarget.disabled).toBe(true);
    });

    test('checkSubmitEnable disables button for negative result after parsing', () => {
        controller.submitBtnTarget.disabled = false;
        controller.roleTarget.value = 'abc_def';
        controller.checkSubmitEnable();
        expect(controller.submitBtnTarget.disabled).toBe(true);
    });

    // --- Toggle behavior ---

    test('submit button toggles between enabled and disabled', () => {
        // Start disabled
        controller.roleTarget.value = '';
        controller.checkSubmitEnable();
        expect(controller.submitBtnTarget.disabled).toBe(true);

        // Enable with valid value
        controller.roleTarget.value = '10';
        controller.checkSubmitEnable();
        expect(controller.submitBtnTarget.disabled).toBe(false);

        // Disable again with invalid value
        controller.roleTarget.value = '';
        controller.checkSubmitEnable();
        expect(controller.submitBtnTarget.disabled).toBe(true);
    });
});
