// Controller registers on window.Controllers (no default export)
import '../../../plugins/Officers/assets/js/controllers/edit-officer-controller.js';
const EditOfficer = window.Controllers['officers-edit-officer'];

describe('EditOfficer', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div class="modal" data-controller="officers-edit-officer">
                <div data-officers-edit-officer-target="deputyDescBlock" class="d-none">
                    <input data-officers-edit-officer-target="deputyDesc" value="">
                </div>
                <input type="hidden" data-officers-edit-officer-target="id" value="">
                <input type="email" data-officers-edit-officer-target="emailAddress" value="">
                <input type="date" data-officers-edit-officer-target="startOn" value=""
                    aria-describedby="edit_officer__term_dates_error">
                <input type="date" data-officers-edit-officer-target="expiresOn" value=""
                    aria-describedby="edit_officer__term_dates_error">
                <div id="edit_officer__term_dates_error" class="d-none" role="alert"
                    data-officers-edit-officer-target="termDatesError">
                    The end date must be on or after the start date.
                </div>
                <span data-officers-edit-officer-target="termNoteRequired" class="d-none">(required)</span>
                <textarea data-officers-edit-officer-target="termNote" aria-required="false"></textarea>
                <p data-officers-edit-officer-target="termNotesEmpty"></p>
                <ul data-officers-edit-officer-target="termNotesList" class="d-none"></ul>
                <p data-officers-edit-officer-target="status" role="status"></p>
            </div>
        `;

        controller = new EditOfficer();
        controller.initialize();
        controller.element = document.querySelector('[data-controller="officers-edit-officer"]');

        // Wire up targets
        controller.deputyDescBlockTarget = document.querySelector('[data-officers-edit-officer-target="deputyDescBlock"]');
        controller.deputyDescTarget = document.querySelector('[data-officers-edit-officer-target="deputyDesc"]');
        controller.idTarget = document.querySelector('[data-officers-edit-officer-target="id"]');
        controller.emailAddressTarget = document.querySelector('[data-officers-edit-officer-target="emailAddress"]');
        controller.startOnTarget = document.querySelector('[data-officers-edit-officer-target="startOn"]');
        controller.expiresOnTarget = document.querySelector('[data-officers-edit-officer-target="expiresOn"]');
        controller.termDatesErrorTarget = document.querySelector('[data-officers-edit-officer-target="termDatesError"]');
        controller.termNoteTarget = document.querySelector('[data-officers-edit-officer-target="termNote"]');
        controller.termNoteRequiredTarget = document.querySelector('[data-officers-edit-officer-target="termNoteRequired"]');
        controller.termNotesEmptyTarget = document.querySelector('[data-officers-edit-officer-target="termNotesEmpty"]');
        controller.termNotesListTarget = document.querySelector('[data-officers-edit-officer-target="termNotesList"]');
        controller.statusTarget = document.querySelector('[data-officers-edit-officer-target="status"]');
        controller.connect();
    });

    afterEach(() => {
        controller.disconnect();
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    // --- Static properties ---

    test('has correct static targets', () => {
        expect(EditOfficer.targets).toEqual(
            expect.arrayContaining([
                'deputyDescBlock',
                'deputyDesc',
                'id',
                'emailAddress',
                'startOn',
                'expiresOn',
                'termDatesError',
                'termNote',
                'termNoteRequired',
                'termNotesEmpty',
                'termNotesList',
                'status',
            ])
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
                is_deputy: '1',
                start_on: '2026-01-15T00:00:00+00:00',
                expires_on: '2027-01-15',
                term_notes_payload: [],
            }
        };
        controller.setId(event);

        expect(controller.idTarget.value).toBe('42');
        expect(controller.deputyDescTarget.value).toBe('Deputy Herald');
        expect(controller.deputyDescBlockTarget.classList.contains('d-none')).toBe(false);
        expect(controller.emailAddressTarget.value).toBe('herald@test.com');
        expect(controller.startOnTarget.value).toBe('2026-01-15');
        expect(controller.expiresOnTarget.value).toBe('2027-01-15');
        expect(controller.termNoteTarget.required).toBe(false);
    });

    test('setId hides deputy fields but leaves a blank email editable for a non-deputy officer', () => {
        const event = {
            detail: {
                id: '43',
                deputy_description: null,
                email_address: null,
                is_deputy: '0',
                start_on: null,
                expires_on: null,
                term_notes_payload: null,
            }
        };
        controller.setId(event);

        expect(controller.idTarget.value).toBe('43');
        expect(controller.deputyDescBlockTarget.classList.contains('d-none')).toBe(true);
        expect(controller.emailAddressTarget.value).toBe('');
        expect(controller.emailAddressTarget.disabled).toBe(false);
        expect(controller.emailAddressTarget.closest('.d-none')).toBeNull();
    });

    test('setId populates an existing email address', () => {
        const event = {
            detail: {
                id: '44',
                deputy_description: '',
                email_address: 'officer@test.com',
                is_deputy: '0'
            }
        };
        controller.setId(event);

        expect(controller.emailAddressTarget.value).toBe('officer@test.com');
    });

    test('requires and announces a note when either term date changes', () => {
        controller.setId({
            detail: {
                id: '45',
                is_deputy: false,
                start_on: '2026-02-01',
                expires_on: '2027-02-01',
                term_notes_payload: [],
            }
        });

        controller.startOnTarget.value = '2026-02-02';
        controller.termDatesChanged();

        expect(controller.termNoteTarget.required).toBe(true);
        expect(controller.termNoteTarget).toHaveAttribute('aria-required', 'true');
        expect(controller.termNoteRequiredTarget).not.toHaveClass('d-none');
        expect(controller.statusTarget).toHaveTextContent('A term change note is now required.');

        controller.startOnTarget.value = '2026-02-01';
        controller.termDatesChanged();

        expect(controller.termNoteTarget.required).toBe(false);
        expect(controller.termNoteTarget).toHaveAttribute('aria-required', 'false');
        expect(controller.termNoteRequiredTarget).toHaveClass('d-none');

        controller.expiresOnTarget.value = '2027-03-01';
        controller.termDatesChanged();

        expect(controller.termNoteTarget.required).toBe(true);
        expect(controller.statusTarget).toHaveTextContent('A term change note is now required.');
    });

    test('identifies an invalid date range inline and focuses the end date on submit', () => {
        controller.setId({
            detail: {
                id: '46',
                is_deputy: false,
                start_on: '2026-06-01',
                expires_on: '2027-06-01',
                term_notes_payload: [],
            },
        });
        controller.expiresOnTarget.value = '2026-05-31';

        controller.termDatesChanged();

        expect(controller.expiresOnTarget).toHaveAttribute('aria-invalid', 'true');
        expect(controller.expiresOnTarget).toHaveClass('is-invalid');
        expect(controller.expiresOnTarget).toHaveAttribute(
            'aria-describedby',
            'edit_officer__term_dates_error',
        );
        expect(controller.termDatesErrorTarget).not.toHaveClass('d-none');
        expect(controller.expiresOnTarget.validationMessage)
            .toBe('The end date must be on or after the start date.');

        const focusSpy = jest.spyOn(controller.expiresOnTarget, 'focus');
        const event = {
            preventDefault: jest.fn(),
            stopImmediatePropagation: jest.fn(),
        };
        controller.validateForm(event);

        expect(event.preventDefault).toHaveBeenCalled();
        expect(event.stopImmediatePropagation).toHaveBeenCalled();
        expect(focusSpy).toHaveBeenCalled();

        controller.expiresOnTarget.value = '2026-06-01';
        controller.termDatesChanged();

        expect(controller.expiresOnTarget).not.toHaveAttribute('aria-invalid');
        expect(controller.expiresOnTarget).not.toHaveClass('is-invalid');
        expect(controller.termDatesErrorTarget).toHaveClass('d-none');
        expect(controller.expiresOnTarget.validationMessage).toBe('');
    });

    test('renders existing term notes as text without interpreting payload markup', () => {
        controller.setId({
            detail: {
                id: '46',
                is_deputy: false,
                term_notes_payload: JSON.stringify([{
                    subject: '<img src=x onerror=alert(1)> Term updated',
                    body: '<script>alert(1)</script> Approved by the Crown.',
                    created_on: 'September 2, 2026',
                    author: { sca_name: 'Test Herald' },
                }]),
            },
        });

        expect(controller.termNotesEmptyTarget).toHaveClass('d-none');
        expect(controller.termNotesListTarget).not.toHaveClass('d-none');
        expect(controller.termNotesListTarget).toHaveTextContent('<img src=x onerror=alert(1)> Term updated');
        expect(controller.termNotesListTarget).toHaveTextContent('<script>alert(1)</script> Approved by the Crown.');
        expect(controller.termNotesListTarget).toHaveTextContent('by Test Herald');
        expect(controller.termNotesListTarget.querySelector('img')).toBeNull();
        expect(controller.termNotesListTarget.querySelector('script')).toBeNull();
        expect(controller.statusTarget).toBeEmptyDOMElement();

        controller.element.dispatchEvent(new Event('shown.bs.modal'));

        expect(controller.statusTarget).toHaveTextContent('1 existing term note loaded.');
    });

    test('shows the empty state for a malformed term notes payload', () => {
        controller.setId({
            detail: {
                id: '47',
                is_deputy: false,
                term_notes_payload: '{not-json',
            },
        });

        expect(controller.termNotesEmptyTarget).not.toHaveClass('d-none');
        expect(controller.termNotesListTarget).toHaveClass('d-none');
        expect(controller.termNotesListTarget).toBeEmptyDOMElement();
        expect(controller.statusTarget).toBeEmptyDOMElement();

        controller.element.dispatchEvent(new Event('shown.bs.modal'));

        expect(controller.statusTarget).toHaveTextContent('No existing term notes.');
    });

    test('uses the same listener reference when an outlet connects and disconnects', () => {
        const mockOutlet = { addListener: jest.fn(), removeListener: jest.fn() };
        controller.outletBtnOutletConnected(mockOutlet);
        const registeredListener = mockOutlet.addListener.mock.calls[0][0];

        controller.outletBtnOutletDisconnected(mockOutlet);

        expect(registeredListener).toBe(controller.setIdListener);
        expect(mockOutlet.removeListener).toHaveBeenCalledWith(registeredListener);
    });
});
