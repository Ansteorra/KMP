import '../../../assets/js/controllers/role-add-member-controller.js';

const RoleAddMember = window.Controllers['role-add-member'];

describe('RoleAddMemberController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <form data-controller="role-add-member">
                <input type="text" data-role-add-member-target="scaMember" value="">
                <select data-role-add-member-target="branch">
                    <option value="">Select Branch</option>
                    <option value="1">Branch 1</option>
                </select>
                <button type="submit" data-role-add-member-target="submitBtn" disabled>Add</button>
            </form>
        `;

        controller = new RoleAddMember();
        controller.element = document.querySelector('[data-controller="role-add-member"]');
        controller.scaMemberTarget = document.querySelector('[data-role-add-member-target="scaMember"]');
        controller.branchTarget = document.querySelector('[data-role-add-member-target="branch"]');
        controller.submitBtnTarget = document.querySelector('[data-role-add-member-target="submitBtn"]');
        controller.hasBranchTarget = true;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['role-add-member']).toBe(RoleAddMember);
    });

    test('has correct static targets', () => {
        expect(RoleAddMember.targets).toEqual(
            expect.arrayContaining(['scaMember', 'form', 'submitBtn', 'branch'])
        );
    });

    test('checkSubmitEnable enables button when member valid and branch selected', () => {
        controller.scaMemberTarget.value = '42';
        controller.branchTarget.value = '1';
        controller.checkSubmitEnable();
        expect(controller.submitBtnTarget.disabled).toBe(false);
    });

    test('checkSubmitEnable disables button when member valid but branch empty', () => {
        controller.scaMemberTarget.value = '42';
        controller.branchTarget.value = '';
        controller.checkSubmitEnable();
        expect(controller.submitBtnTarget.disabled).toBe(true);
    });

    test('checkSubmitEnable enables button without branch target when member valid', () => {
        controller.hasBranchTarget = false;
        controller.scaMemberTarget.value = '42';
        controller.checkSubmitEnable();
        expect(controller.submitBtnTarget.disabled).toBe(false);
    });

    test('checkSubmitEnable disables button for empty member value', () => {
        controller.scaMemberTarget.value = '';
        controller.checkSubmitEnable();
        expect(controller.submitBtnTarget.disabled).toBe(true);
    });

    test('checkSubmitEnable disables button for non-numeric member value', () => {
        controller.scaMemberTarget.value = 'abc';
        controller.checkSubmitEnable();
        expect(controller.submitBtnTarget.disabled).toBe(true);
    });

    test('checkSubmitEnable parses underscore-separated member ids', () => {
        controller.hasBranchTarget = false;
        controller.scaMemberTarget.value = '1_234';
        controller.checkSubmitEnable();
        expect(controller.submitBtnTarget.disabled).toBe(false);
    });

    test('checkSubmitEnable focuses button when enabled', () => {
        const focusSpy = jest.spyOn(controller.submitBtnTarget, 'focus');
        controller.hasBranchTarget = false;
        controller.scaMemberTarget.value = '10';
        controller.checkSubmitEnable();
        expect(focusSpy).toHaveBeenCalled();
    });
});
