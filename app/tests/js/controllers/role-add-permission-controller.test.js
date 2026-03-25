import '../../../assets/js/controllers/role-add-permission-controller.js';

const RoleAddPermission = window.Controllers['role-add-permission'];

describe('RoleAddPermissionController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <form data-controller="role-add-permission">
                <input type="text" data-role-add-permission-target="permission" value="">
                <button type="submit" data-role-add-permission-target="submitBtn" disabled>Add</button>
            </form>
        `;

        controller = new RoleAddPermission();
        controller.element = document.querySelector('[data-controller="role-add-permission"]');
        controller.permissionTarget = document.querySelector('[data-role-add-permission-target="permission"]');
        controller.submitBtnTarget = document.querySelector('[data-role-add-permission-target="submitBtn"]');
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['role-add-permission']).toBe(RoleAddPermission);
    });

    test('has correct static targets', () => {
        expect(RoleAddPermission.targets).toEqual(
            expect.arrayContaining(['permission', 'form', 'submitBtn'])
        );
    });

    test('checkSubmitEnable enables button for valid numeric permission', () => {
        controller.permissionTarget.value = '42';
        controller.checkSubmitEnable();
        expect(controller.submitBtnTarget.disabled).toBe(false);
    });

    test('checkSubmitEnable enables button for underscore-separated id', () => {
        controller.permissionTarget.value = '1_2_3';
        controller.checkSubmitEnable();
        expect(controller.submitBtnTarget.disabled).toBe(false);
    });

    test('checkSubmitEnable disables button for empty value', () => {
        controller.permissionTarget.value = '';
        controller.checkSubmitEnable();
        expect(controller.submitBtnTarget.disabled).toBe(true);
    });

    test('checkSubmitEnable disables button for zero-valued id', () => {
        controller.permissionTarget.value = '0';
        controller.checkSubmitEnable();
        expect(controller.submitBtnTarget.disabled).toBe(true);
    });

    test('checkSubmitEnable disables button for non-numeric value', () => {
        controller.permissionTarget.value = 'abc';
        controller.checkSubmitEnable();
        expect(controller.submitBtnTarget.disabled).toBe(true);
    });

    test('checkSubmitEnable focuses button when enabled', () => {
        const focusSpy = jest.spyOn(controller.submitBtnTarget, 'focus');
        controller.permissionTarget.value = '10';
        controller.checkSubmitEnable();
        expect(focusSpy).toHaveBeenCalled();
    });
});
