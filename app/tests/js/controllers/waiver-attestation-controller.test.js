// Controller registers on window.Controllers (no default export)
import '../../../plugins/Waivers/assets/js/controllers/waiver-attestation-controller.js';
const WaiverAttestationController = window.Controllers['waivers-waiver-attestation'];

describe('WaiverAttestationController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <meta name="csrf-token" content="test-csrf-token">
            <div data-controller="waivers-waiver-attestation">
                <div data-waivers-waiver-attestation-target="modal" class="modal"></div>
                <div data-waivers-waiver-attestation-target="reasonList"></div>
                <textarea data-waivers-waiver-attestation-target="notes"></textarea>
                <button data-waivers-waiver-attestation-target="submitBtn">Submit</button>
                <div data-waivers-waiver-attestation-target="error" class="d-none"></div>
                <div data-waivers-waiver-attestation-target="success" class="d-none"></div>
            </div>
        `;

        controller = new WaiverAttestationController();
        controller.element = document.querySelector('[data-controller="waivers-waiver-attestation"]');

        // Wire up targets
        controller.modalTarget = document.querySelector('[data-waivers-waiver-attestation-target="modal"]');
        controller.reasonListTarget = document.querySelector('[data-waivers-waiver-attestation-target="reasonList"]');
        controller.notesTarget = document.querySelector('[data-waivers-waiver-attestation-target="notes"]');
        controller.submitBtnTarget = document.querySelector('[data-waivers-waiver-attestation-target="submitBtn"]');
        controller.errorTarget = document.querySelector('[data-waivers-waiver-attestation-target="error"]');
        controller.successTarget = document.querySelector('[data-waivers-waiver-attestation-target="success"]');

        // Wire up has* checks
        controller.hasModalTarget = true;
        controller.hasReasonListTarget = true;
        controller.hasNotesTarget = true;
        controller.hasSubmitBtnTarget = true;
        controller.hasErrorTarget = true;
        controller.hasSuccessTarget = true;

        // Wire up values
        controller.activityIdValue = 0;
        controller.waiverTypeIdValue = 0;
        controller.gatheringIdValue = 0;
        controller.reasonsValue = [];
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
        delete global.fetch;
    });

    // --- Static properties ---

    test('has correct static targets', () => {
        expect(WaiverAttestationController.targets).toEqual(
            expect.arrayContaining(['modal', 'reasonList', 'notes', 'submitBtn', 'error', 'success'])
        );
    });

    test('has correct static values', () => {
        expect(WaiverAttestationController.values).toHaveProperty('activityId', Number);
        expect(WaiverAttestationController.values).toHaveProperty('waiverTypeId', Number);
        expect(WaiverAttestationController.values).toHaveProperty('gatheringId', Number);
        expect(WaiverAttestationController.values).toHaveProperty('reasons', Array);
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['waivers-waiver-attestation']).toBe(WaiverAttestationController);
    });

    // --- connect ---

    test('connect initializes Bootstrap modal when modalTarget exists', () => {
        // Mock bootstrap.Modal as a constructor
        const mockModalInstance = { show: jest.fn(), hide: jest.fn() };
        window.bootstrap.Modal = jest.fn(() => mockModalInstance);

        controller.connect();
        expect(window.bootstrap.Modal).toHaveBeenCalledWith(controller.modalTarget);
        expect(controller.modalInstance).toBe(mockModalInstance);
    });

    test('connect skips modal init when no modalTarget', () => {
        window.bootstrap.Modal = jest.fn();
        controller.hasModalTarget = false;
        controller.connect();
        expect(window.bootstrap.Modal).not.toHaveBeenCalled();
    });

    // --- escapeHtml ---

    test('escapeHtml escapes HTML entities', () => {
        const result = controller.escapeHtml('<script>alert("xss")</script>');
        expect(result).not.toContain('<script>');
        expect(result).toContain('&lt;script&gt;');
    });

    test('escapeHtml handles plain text', () => {
        expect(controller.escapeHtml('plain text')).toBe('plain text');
    });

    // --- getCsrfToken ---

    test('getCsrfToken returns token from meta tag', () => {
        expect(controller.getCsrfToken()).toBe('test-csrf-token');
    });

    test('getCsrfToken returns empty string when no meta tag', () => {
        document.querySelector('meta[name="csrf-token"]').remove();
        expect(controller.getCsrfToken()).toBe('');
    });

    // --- populateReasons ---

    test('populateReasons renders radio buttons for each reason', () => {
        controller.reasonsValue = ['Reason A', 'Reason B'];
        controller.populateReasons();
        const radios = controller.reasonListTarget.querySelectorAll('input[type="radio"]');
        expect(radios).toHaveLength(2);
        expect(radios[0].value).toBe('Reason A');
        expect(radios[1].value).toBe('Reason B');
    });

    test('populateReasons shows warning and disables submit when no reasons', () => {
        controller.reasonsValue = [];
        controller.populateReasons();
        expect(controller.reasonListTarget.innerHTML).toContain('alert-warning');
        expect(controller.submitBtnTarget.disabled).toBe(true);
    });

    test('populateReasons enables submit button when reasons exist', () => {
        controller.submitBtnTarget.disabled = true;
        controller.reasonsValue = ['Reason A'];
        controller.populateReasons();
        expect(controller.submitBtnTarget.disabled).toBe(false);
    });

    test('populateReasons does nothing without reasonListTarget', () => {
        controller.hasReasonListTarget = false;
        controller.reasonsValue = ['Reason A'];
        // Should not throw
        expect(() => controller.populateReasons()).not.toThrow();
    });

    // --- showModal ---

    test('showModal extracts values from button and shows modal', () => {
        controller.modalInstance = { show: jest.fn() };
        const event = {
            preventDefault: jest.fn(),
            currentTarget: {
                dataset: {
                    activityId: '10',
                    waiverTypeId: '20',
                    gatheringId: '30',
                    reasons: JSON.stringify(['R1', 'R2'])
                }
            }
        };
        controller.showModal(event);
        expect(event.preventDefault).toHaveBeenCalled();
        expect(controller.activityIdValue).toBe(10);
        expect(controller.waiverTypeIdValue).toBe(20);
        expect(controller.gatheringIdValue).toBe(30);
        expect(controller.reasonsValue).toEqual(['R1', 'R2']);
        expect(controller.modalInstance.show).toHaveBeenCalled();
    });

    test('showModal handles invalid JSON in reasons gracefully', () => {
        controller.modalInstance = { show: jest.fn() };
        const event = {
            preventDefault: jest.fn(),
            currentTarget: {
                dataset: {
                    activityId: '1',
                    waiverTypeId: '2',
                    gatheringId: '3',
                    reasons: 'invalid-json'
                }
            }
        };
        controller.showModal(event);
        expect(controller.reasonsValue).toEqual([]);
    });

    test('showModal clears previous notes', () => {
        controller.modalInstance = { show: jest.fn() };
        controller.notesTarget.value = 'old notes';
        const event = {
            preventDefault: jest.fn(),
            currentTarget: {
                dataset: {
                    activityId: '1',
                    waiverTypeId: '2',
                    gatheringId: '3',
                    reasons: '[]'
                }
            }
        };
        controller.showModal(event);
        expect(controller.notesTarget.value).toBe('');
    });

    // --- showError / showSuccess / clearMessages ---

    test('showError displays error message and hides success', () => {
        controller.showError('Something failed');
        expect(controller.errorTarget.textContent).toBe('Something failed');
        expect(controller.errorTarget.classList.contains('d-none')).toBe(false);
        expect(controller.successTarget.classList.contains('d-none')).toBe(true);
    });

    test('showSuccess displays success message and hides error', () => {
        controller.showSuccess('It worked!');
        expect(controller.successTarget.textContent).toBe('It worked!');
        expect(controller.successTarget.classList.contains('d-none')).toBe(false);
        expect(controller.errorTarget.classList.contains('d-none')).toBe(true);
    });

    test('clearMessages hides both error and success', () => {
        controller.errorTarget.classList.remove('d-none');
        controller.successTarget.classList.remove('d-none');
        controller.clearMessages();
        expect(controller.errorTarget.classList.contains('d-none')).toBe(true);
        expect(controller.successTarget.classList.contains('d-none')).toBe(true);
    });

    // --- resetSubmitButton ---

    test('resetSubmitButton re-enables and restores button text', () => {
        controller.submitBtnTarget.disabled = true;
        controller.submitBtnTarget.innerHTML = 'Loading...';
        controller.resetSubmitButton();
        expect(controller.submitBtnTarget.disabled).toBe(false);
        expect(controller.submitBtnTarget.innerHTML).toContain('Submit Attestation');
    });

    // --- submitAttestation ---

    test('submitAttestation shows error when no reason selected', async () => {
        const event = { preventDefault: jest.fn() };
        await controller.submitAttestation(event);
        expect(controller.errorTarget.textContent).toBe('Please select a reason for the exemption.');
    });

    test('submitAttestation submits data via fetch on success', async () => {
        // Select a reason
        controller.reasonsValue = ['Test Reason'];
        controller.populateReasons();
        controller.reasonListTarget.querySelector('input[type="radio"]').checked = true;
        controller.notesTarget.value = 'Test notes';

        controller.activityIdValue = 10;
        controller.waiverTypeIdValue = 20;
        controller.gatheringIdValue = 30;

        // Mock window.location.reload
        delete window.location;
        window.location = { reload: jest.fn() };

        global.fetch = jest.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve({ success: true, message: 'Recorded' })
        }));

        const event = { preventDefault: jest.fn() };
        await controller.submitAttestation(event);

        expect(global.fetch).toHaveBeenCalledWith('/waivers/gathering-waivers/attest', expect.objectContaining({
            method: 'POST',
            body: expect.any(String)
        }));
        expect(controller.successTarget.textContent).toBe('Recorded');
    });

    test('submitAttestation handles fetch error', async () => {
        controller.reasonsValue = ['Test Reason'];
        controller.populateReasons();
        controller.reasonListTarget.querySelector('input[type="radio"]').checked = true;

        global.fetch = jest.fn(() => Promise.reject(new Error('Network error')));

        const event = { preventDefault: jest.fn() };
        await controller.submitAttestation(event);

        expect(controller.errorTarget.textContent).toBe('An error occurred while submitting the attestation.');
        expect(controller.submitBtnTarget.disabled).toBe(false);
    });

    test('submitAttestation handles server error response', async () => {
        controller.reasonsValue = ['Test Reason'];
        controller.populateReasons();
        controller.reasonListTarget.querySelector('input[type="radio"]').checked = true;

        global.fetch = jest.fn(() => Promise.resolve({
            ok: false,
            json: () => Promise.resolve({ success: false, message: 'Server error' })
        }));

        const event = { preventDefault: jest.fn() };
        await controller.submitAttestation(event);

        expect(controller.errorTarget.textContent).toBe('Server error');
    });

    test('submitAttestation disables submit button during submission', async () => {
        controller.reasonsValue = ['Test Reason'];
        controller.populateReasons();
        controller.reasonListTarget.querySelector('input[type="radio"]').checked = true;

        global.fetch = jest.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve({ success: true })
        }));

        // Mock reload
        delete window.location;
        window.location = { reload: jest.fn() };

        const event = { preventDefault: jest.fn() };
        await controller.submitAttestation(event);

        expect(controller.submitBtnTarget.innerHTML).toContain('Submitting...');
    });
});
