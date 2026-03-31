import '../../../assets/js/controllers/member-verify-form-controller.js';
const MemberVerifyForm = window.Controllers['member-verify-form'];

describe('MemberVerifyFormController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <form data-controller="member-verify-form">
                <input type="checkbox" id="parentCheckbox">
                <input type="text" data-member-verify-form-target="scaMember" disabled>
                <input type="checkbox" id="membershipCheckbox">
                <input type="text" data-member-verify-form-target="membershipNumber" disabled>
                <input type="date" data-member-verify-form-target="membershipExpDate" disabled>
            </form>
        `;

        controller = new MemberVerifyForm();
        controller.element = document.querySelector('[data-controller="member-verify-form"]');
        controller.scaMemberTarget = document.querySelector('[data-member-verify-form-target="scaMember"]');
        controller.membershipNumberTarget = document.querySelector('[data-member-verify-form-target="membershipNumber"]');
        controller.membershipExpDateTarget = document.querySelector('[data-member-verify-form-target="membershipExpDate"]');
        controller.hasScaMemberTarget = true;
        controller.hasMembershipNumberTarget = true;
        controller.hasMembershipExpDateTarget = true;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['member-verify-form']).toBe(MemberVerifyForm);
    });

    test('has correct static targets', () => {
        expect(MemberVerifyForm.targets).toEqual(
            expect.arrayContaining(['scaMember', 'membershipNumber', 'membershipExpDate'])
        );
    });

    test('toggleParent enables scaMember when checked', () => {
        controller.toggleParent({ target: { checked: true } });
        expect(controller.scaMemberTarget.disabled).toBe(false);
    });

    test('toggleParent disables scaMember when unchecked', () => {
        controller.scaMemberTarget.disabled = false;
        controller.toggleParent({ target: { checked: false } });
        expect(controller.scaMemberTarget.disabled).toBe(true);
    });

    test('toggleMembership enables both fields when checked', () => {
        controller.toggleMembership({ target: { checked: true } });
        expect(controller.membershipNumberTarget.disabled).toBe(false);
        expect(controller.membershipExpDateTarget.disabled).toBe(false);
    });

    test('toggleMembership disables both fields when unchecked', () => {
        controller.membershipNumberTarget.disabled = false;
        controller.membershipExpDateTarget.disabled = false;
        controller.toggleMembership({ target: { checked: false } });
        expect(controller.membershipNumberTarget.disabled).toBe(true);
        expect(controller.membershipExpDateTarget.disabled).toBe(true);
    });
});
