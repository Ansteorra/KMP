import '../../../plugins/Awards/Assets/js/controllers/approval-step-form-controller.js';

const Controller = window.Controllers['awards-approval-step-form'];

describe('AwardsApprovalStepForm', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <fieldset data-controller="awards-approval-step-form">
                <select data-awards-approval-step-form-target="approverType">
                    <option value="role" selected>Role</option>
                    <option value="permission">Permission</option>
                    <option value="dynamic">Dynamic</option>
                </select>
                <div data-awards-approval-step-form-target="sourceGroup" data-approver-source-type="role">
                    <input name="role_source_id" value="1">
                </div>
                <div data-awards-approval-step-form-target="sourceGroup" data-approver-source-type="permission">
                    <input name="permission_source_id" value="2">
                </div>
                <div data-awards-approval-step-form-target="sourceGroup" data-approver-source-type="dynamic">
                    <input name="approver_source_key" value="">
                </div>
                <select data-awards-approval-step-form-target="branchMode">
                    <option value="award_branch" selected>Award branch</option>
                    <option value="ancestor_branch_type">Ancestor</option>
                </select>
                <div data-awards-approval-step-form-target="branchTypeGroup">
                    <select name="branch_type"><option value="Kingdom">Kingdom</option></select>
                </div>
                <select data-awards-approval-step-form-target="thresholdMode">
                    <option value="any" selected>Any</option>
                    <option value="count">Count</option>
                </select>
                <div data-awards-approval-step-form-target="requiredCountGroup">
                    <input name="required_count" type="number" value="2">
                </div>
            </fieldset>
        `;

        controller = new Controller();
        controller.element = document.querySelector('[data-controller="awards-approval-step-form"]');
        controller.approverTypeTarget = controller.element.querySelector('[data-awards-approval-step-form-target="approverType"]');
        controller.sourceGroupTargets = Array.from(controller.element.querySelectorAll('[data-awards-approval-step-form-target="sourceGroup"]'));
        controller.branchModeTarget = controller.element.querySelector('[data-awards-approval-step-form-target="branchMode"]');
        controller.branchTypeGroupTarget = controller.element.querySelector('[data-awards-approval-step-form-target="branchTypeGroup"]');
        controller.thresholdModeTarget = controller.element.querySelector('[data-awards-approval-step-form-target="thresholdMode"]');
        controller.requiredCountGroupTarget = controller.element.querySelector('[data-awards-approval-step-form-target="requiredCountGroup"]');
        controller.hasApproverTypeTarget = true;
        controller.hasBranchModeTarget = true;
        controller.hasBranchTypeGroupTarget = true;
        controller.hasThresholdModeTarget = true;
        controller.hasRequiredCountGroupTarget = true;
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    test('registers on window.Controllers', () => {
        expect(Controller).toBeDefined();
    });

    test('shows only the selected approver source group', () => {
        controller.connect();

        const [roleGroup, permissionGroup, dynamicGroup] = controller.sourceGroupTargets;
        expect(roleGroup.hidden).toBe(false);
        expect(roleGroup.querySelector('input')).not.toBeDisabled();
        expect(permissionGroup.hidden).toBe(true);
        expect(permissionGroup.querySelector('input')).toBeDisabled();
        expect(dynamicGroup.hidden).toBe(true);
        expect(dynamicGroup.querySelector('input')).toBeDisabled();
    });

    test('switching approver source enables only the newly selected group', () => {
        controller.connect();
        controller.approverTypeTarget.value = 'dynamic';
        controller.sync();

        const [roleGroup, permissionGroup, dynamicGroup] = controller.sourceGroupTargets;
        expect(roleGroup.hidden).toBe(true);
        expect(roleGroup.querySelector('input')).toBeDisabled();
        expect(permissionGroup.hidden).toBe(true);
        expect(permissionGroup.querySelector('input')).toBeDisabled();
        expect(dynamicGroup.hidden).toBe(false);
        expect(dynamicGroup.querySelector('input')).not.toBeDisabled();
    });

    test('shows ancestor branch type only for ancestor branch scope', () => {
        controller.connect();
        expect(controller.branchTypeGroupTarget.hidden).toBe(true);
        expect(controller.branchTypeGroupTarget.querySelector('select')).toBeDisabled();

        controller.branchModeTarget.value = 'ancestor_branch_type';
        controller.sync();

        expect(controller.branchTypeGroupTarget.hidden).toBe(false);
        expect(controller.branchTypeGroupTarget.querySelector('select')).not.toBeDisabled();
    });

    test('shows required count only for count threshold', () => {
        controller.connect();
        expect(controller.requiredCountGroupTarget.hidden).toBe(true);
        expect(controller.requiredCountGroupTarget.querySelector('input')).toBeDisabled();

        controller.thresholdModeTarget.value = 'count';
        controller.sync();

        expect(controller.requiredCountGroupTarget.hidden).toBe(false);
        expect(controller.requiredCountGroupTarget.querySelector('input')).not.toBeDisabled();
    });

    test('does not re-enable controls that were disabled before this controller hid a group', () => {
        const permissionInput = controller.sourceGroupTargets[1].querySelector('input');
        permissionInput.disabled = true;

        controller.connect();
        controller.approverTypeTarget.value = 'permission';
        controller.sync();

        expect(permissionInput).toBeDisabled();
    });
});
