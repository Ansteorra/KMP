// Controller registers on window.Controllers (no default export)
import '../../../plugins/Officers/assets/js/controllers/assign-officer-controller.js';
const OfficersAssignOfficer = window.Controllers['officers-assign-officer'];

describe('OfficersAssignOfficer', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="officers-assign-officer"
                 data-officers-assign-officer-url-value="/officers/assign">
                <input data-officers-assign-officer-target="assignee" value=""
                       data-ac-url-value="/members/search/0">
                <select data-officers-assign-officer-target="office">
                    <option value="">Select</option>
                    <option value="10">Seneschal</option>
                    <option value="20">Herald</option>
                </select>
                <button data-officers-assign-officer-target="submitBtn">Submit</button>
                <div data-officers-assign-officer-target="deputyDescBlock" class="d-none">
                    <input data-officers-assign-officer-target="deputyDesc" disabled>
                </div>
                <div data-officers-assign-officer-target="endDateBlock" class="d-none">
                    <input type="date" data-officers-assign-officer-target="endDate" disabled>
                </div>
                <div data-officers-assign-officer-target="emailAddressBlock" class="d-none">
                    <input data-officers-assign-officer-target="emailAddress" disabled>
                </div>
            </div>
        `;

        controller = new OfficersAssignOfficer();
        controller.element = document.querySelector('[data-controller="officers-assign-officer"]');

        // Wire up targets
        controller.assigneeTarget = document.querySelector('[data-officers-assign-officer-target="assignee"]');
        controller.officeTarget = document.querySelector('[data-officers-assign-officer-target="office"]');
        controller.submitBtnTarget = document.querySelector('[data-officers-assign-officer-target="submitBtn"]');
        controller.deputyDescBlockTarget = document.querySelector('[data-officers-assign-officer-target="deputyDescBlock"]');
        controller.deputyDescTarget = document.querySelector('[data-officers-assign-officer-target="deputyDesc"]');
        controller.endDateBlockTarget = document.querySelector('[data-officers-assign-officer-target="endDateBlock"]');
        controller.endDateTarget = document.querySelector('[data-officers-assign-officer-target="endDate"]');
        controller.emailAddressBlockTarget = document.querySelector('[data-officers-assign-officer-target="emailAddressBlock"]');
        controller.emailAddressTarget = document.querySelector('[data-officers-assign-officer-target="emailAddress"]');

        // Wire up values
        controller.urlValue = '/officers/assign';
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    // --- Static properties ---

    test('has correct static targets', () => {
        expect(OfficersAssignOfficer.targets).toEqual(
            expect.arrayContaining([
                'assignee', 'submitBtn', 'deputyDescBlock', 'deputyDesc',
                'office', 'endDateBlock', 'endDate', 'emailAddress', 'emailAddressBlock'
            ])
        );
    });

    test('has correct static values', () => {
        expect(OfficersAssignOfficer.values).toHaveProperty('url', String);
    });

    test('has correct static outlets', () => {
        expect(OfficersAssignOfficer.outlets).toEqual(
            expect.arrayContaining(['outlet-btn', 'member-serach'])
        );
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['officers-assign-officer']).toBe(OfficersAssignOfficer);
    });

    // --- connect ---

    test('connect hides optional field blocks', () => {
        controller.deputyDescBlockTarget.classList.remove('d-none');
        controller.endDateBlockTarget.classList.remove('d-none');
        controller.emailAddressBlockTarget.classList.remove('d-none');

        controller.connect();

        expect(controller.deputyDescBlockTarget.classList.contains('d-none')).toBe(true);
        expect(controller.endDateBlockTarget.classList.contains('d-none')).toBe(true);
        expect(controller.emailAddressBlockTarget.classList.contains('d-none')).toBe(true);
    });

    // --- submitBtnTargetConnected ---

    test('submitBtnTargetConnected disables submit button', () => {
        controller.submitBtnTarget.disabled = false;
        controller.submitBtnTargetConnected();
        expect(controller.submitBtnTarget.disabled).toBe(true);
    });

    // --- endDateTargetConnected ---

    test('endDateTargetConnected disables end date field', () => {
        controller.endDateTarget.disabled = false;
        controller.endDateTargetConnected();
        expect(controller.endDateTarget.disabled).toBe(true);
    });

    // --- deputyDescTargetConnected ---

    test('deputyDescTargetConnected disables deputy desc field', () => {
        controller.deputyDescTarget.disabled = false;
        controller.deputyDescTargetConnected();
        expect(controller.deputyDescTarget.disabled).toBe(true);
    });

    // --- checkReadyToSubmit ---

    test('checkReadyToSubmit enables button when both assignee and office selected', () => {
        controller.assigneeTarget.value = '10'; // matches option
        // Use a plain object for office since controller parses its .value
        const origOffice = controller.officeTarget;
        controller.officeTarget = { value: '10' };
        controller.checkReadyToSubmit();
        expect(controller.submitBtnTarget.disabled).toBe(false);
        controller.officeTarget = origOffice;
    });

    test('checkReadyToSubmit disables button when assignee is empty', () => {
        controller.assigneeTarget.value = '';
        controller.officeTarget = { value: '10' };
        controller.checkReadyToSubmit();
        expect(controller.submitBtnTarget.disabled).toBe(true);
    });

    test('checkReadyToSubmit disables button when office is empty', () => {
        controller.assigneeTarget.value = '5';
        controller.officeTarget = { value: '' };
        controller.checkReadyToSubmit();
        expect(controller.submitBtnTarget.disabled).toBe(true);
    });

    test('checkReadyToSubmit disables button for non-numeric values', () => {
        controller.assigneeTarget.value = 'abc';
        controller.officeTarget = { value: 'xyz' };
        controller.checkReadyToSubmit();
        expect(controller.submitBtnTarget.disabled).toBe(true);
    });

    // --- setOfficeQuestions ---

    test('setOfficeQuestions hides all optional blocks and disables fields', () => {
        // Use a plain object for officeTarget since controller calls .options.find()
        controller.officeTarget = {
            value: '10',
            options: { find: jest.fn(() => null) }
        };

        controller.setOfficeQuestions();

        expect(controller.deputyDescBlockTarget.classList.contains('d-none')).toBe(true);
        expect(controller.endDateBlockTarget.classList.contains('d-none')).toBe(true);
        expect(controller.emailAddressBlockTarget.classList.contains('d-none')).toBe(true);
        expect(controller.endDateTarget.disabled).toBe(true);
        expect(controller.deputyDescTarget.disabled).toBe(true);
        expect(controller.emailAddressTarget.disabled).toBe(true);
    });

    test('setOfficeQuestions shows deputy fields for deputy office', () => {
        controller.officeTarget = {
            value: '10',
            options: { find: jest.fn(() => ({
                value: '10',
                data: { is_deputy: true, email_address: '' }
            }))}
        };

        controller.setOfficeQuestions();

        expect(controller.deputyDescBlockTarget.classList.contains('d-none')).toBe(false);
        expect(controller.endDateBlockTarget.classList.contains('d-none')).toBe(false);
        expect(controller.endDateTarget.disabled).toBe(false);
        expect(controller.deputyDescTarget.disabled).toBe(false);
    });

    test('setOfficeQuestions shows email field when office has email', () => {
        controller.officeTarget = {
            value: '10',
            options: { find: jest.fn(() => ({
                value: '10',
                data: { is_deputy: false, email_address: 'test@example.com' }
            }))}
        };

        controller.setOfficeQuestions();

        expect(controller.emailAddressBlockTarget.classList.contains('d-none')).toBe(false);
        expect(controller.emailAddressTarget.disabled).toBe(false);
        expect(controller.emailAddressTarget.value).toBe('test@example.com');
    });

    test('setOfficeQuestions updates assignee autocomplete URL', () => {
        controller.assigneeTarget.setAttribute('data-ac-url-value', '/members/search/5');
        controller.officeTarget = {
            value: '10',
            options: { find: jest.fn(() => null) }
        };

        controller.setOfficeQuestions();

        expect(controller.assigneeTarget.getAttribute('data-ac-url-value')).toBe('/members/search/10');
    });
});
