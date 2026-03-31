// Controller registers on window.Controllers (no default export)
import '../../../plugins/Officers/assets/js/controllers/edit-officer-controller.js';
const EditOfficer = window.Controllers['officers-edit-officer'];

describe('EditOfficer', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="officers-edit-officer">
                <div data-officers-edit-officer-target="deputyDescBlock" class="d-none">
                    <input data-officers-edit-officer-target="deputyDesc" value="">
                </div>
                <input type="hidden" data-officers-edit-officer-target="id" value="">
                <div data-officers-edit-officer-target="emailAddressBlock" class="d-none">
                    <input data-officers-edit-officer-target="emailAddress" value="">
                </div>
            </div>
        `;

        controller = new EditOfficer();
        controller.element = document.querySelector('[data-controller="officers-edit-officer"]');

        // Wire up targets
        controller.deputyDescBlockTarget = document.querySelector('[data-officers-edit-officer-target="deputyDescBlock"]');
        controller.deputyDescTarget = document.querySelector('[data-officers-edit-officer-target="deputyDesc"]');
        controller.idTarget = document.querySelector('[data-officers-edit-officer-target="id"]');
        controller.emailAddressTarget = document.querySelector('[data-officers-edit-officer-target="emailAddress"]');
        controller.emailAddressBlockTarget = document.querySelector('[data-officers-edit-officer-target="emailAddressBlock"]');
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    // --- Static properties ---

    test('has correct static targets', () => {
        expect(EditOfficer.targets).toEqual(
            expect.arrayContaining(['deputyDescBlock', 'deputyDesc', 'id', 'emailAddress', 'emailAddressBlock'])
        );
    });

    test('has correct static outlets', () => {
        expect(EditOfficer.outlets).toEqual(
            expect.arrayContaining(['outlet-btn'])
        );
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['officers-edit-officer']).toBe(EditOfficer);
    });

    // --- setId ---

    test('setId populates form fields from deputy officer event', () => {
        const event = {
            detail: {
                id: '42',
                deputy_description: ': Deputy Herald',
                email_address: 'herald@test.com',
                is_deputy: '1'
            }
        };
        controller.setId(event);

        expect(controller.idTarget.value).toBe('42');
        expect(controller.deputyDescTarget.value).toBe('Deputy Herald');
        expect(controller.deputyDescBlockTarget.classList.contains('d-none')).toBe(false);
        expect(controller.emailAddressTarget.value).toBe('herald@test.com');
        expect(controller.emailAddressBlockTarget.classList.contains('d-none')).toBe(false);
    });

    test('setId hides deputy block for non-deputy officer', () => {
        const event = {
            detail: {
                id: '43',
                deputy_description: '',
                email_address: '',
                is_deputy: '0'
            }
        };
        controller.setId(event);

        expect(controller.idTarget.value).toBe('43');
        expect(controller.deputyDescBlockTarget.classList.contains('d-none')).toBe(true);
        expect(controller.emailAddressBlockTarget.classList.contains('d-none')).toBe(true);
    });

    test('setId shows email block when email is present', () => {
        const event = {
            detail: {
                id: '44',
                deputy_description: '',
                email_address: 'officer@test.com',
                is_deputy: '0'
            }
        };
        controller.setId(event);

        expect(controller.emailAddressBlockTarget.classList.contains('d-none')).toBe(false);
    });

    // --- outletBtnOutletConnected ---

    test('outletBtnOutletConnected registers setId listener', () => {
        const mockOutlet = { addListener: jest.fn() };
        controller.outletBtnOutletConnected(mockOutlet, document.createElement('div'));
        expect(mockOutlet.addListener).toHaveBeenCalledWith(expect.any(Function));
    });

    // --- outletBtnOutletDisconnected ---

    test('outletBtnOutletDisconnected removes setId listener', () => {
        const mockOutlet = { removeListener: jest.fn() };
        controller.outletBtnOutletDisconnected(mockOutlet);
        expect(mockOutlet.removeListener).toHaveBeenCalledWith(expect.any(Function));
    });
});
