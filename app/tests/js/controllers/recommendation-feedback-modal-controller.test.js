import '../../../assets/js/controllers/recommendation-feedback-modal-controller.js';

const RecommendationFeedbackModalController = window.Controllers['recommendation-feedback-modal'];

describe('RecommendationFeedbackModalController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="recommendation-feedback-modal">
                <input type="hidden" data-recommendation-feedback-modal-target="ids">
                <div data-recommendation-feedback-modal-target="selectionSummary"></div>
                <input type="hidden" data-recommendation-feedback-modal-target="recipientIds">
                <div data-recommendation-feedback-modal-target="recipientLookup">
                    <input type="hidden" data-ac-target="hidden">
                    <input type="hidden" data-ac-target="hiddenText">
                    <input type="text" data-ac-target="input">
                    <button type="button" data-ac-target="clearBtn"></button>
                </div>
                <button type="button" data-recommendation-feedback-modal-target="addRecipientButton"></button>
                <div data-recommendation-feedback-modal-target="recipientList"></div>
                <div data-recommendation-feedback-modal-target="recipientStatus"></div>
                <button type="submit" data-recommendation-feedback-modal-target="submitButton"></button>
            </div>
        `;

        controller = new RecommendationFeedbackModalController();
        controller.element = document.querySelector('[data-controller="recommendation-feedback-modal"]');
        controller.idsTarget = document.querySelector('[data-recommendation-feedback-modal-target="ids"]');
        controller.selectionSummaryTarget = document.querySelector('[data-recommendation-feedback-modal-target="selectionSummary"]');
        controller.recipientIdsTarget = document.querySelector('[data-recommendation-feedback-modal-target="recipientIds"]');
        controller.recipientLookupTarget = document.querySelector('[data-recommendation-feedback-modal-target="recipientLookup"]');
        controller.recipientListTarget = document.querySelector('[data-recommendation-feedback-modal-target="recipientList"]');
        controller.recipientStatusTarget = document.querySelector('[data-recommendation-feedback-modal-target="recipientStatus"]');
        controller.addRecipientButtonTarget = document.querySelector('[data-recommendation-feedback-modal-target="addRecipientButton"]');
        controller.submitButtonTarget = document.querySelector('[data-recommendation-feedback-modal-target="submitButton"]');
        controller.hasRecipientLookupTarget = true;
        controller.hasAddRecipientButtonTarget = true;
        controller.hasSubmitButtonTarget = true;
        controller.hasRecipientStatusTarget = true;
        controller.recipients = new Map();
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['recommendation-feedback-modal']).toBe(RecommendationFeedbackModalController);
    });

    test('adds selected lookup members and stores recipient IDs', () => {
        controller.idsTarget.value = '10';
        controller.recipientLookupTarget.querySelector("[data-ac-target='hidden']").value = '5';
        controller.recipientLookupTarget.querySelector("[data-ac-target='hiddenText']").value = 'Mistress Example';
        controller.recipientLookupTarget.querySelector("[data-ac-target='input']").disabled = true;
        controller.recipientLookupTarget.querySelector("[data-ac-target='clearBtn']").disabled = false;

        controller.addRecipient();

        expect(controller.recipientIdsTarget.value).toBe('5');
        expect(controller.recipientListTarget.textContent).toContain('Mistress Example');
        expect(controller.recipientListTarget.querySelector('[role="listitem"]').classList).toContain('rounded-2');
        expect(controller.recipientListTarget.querySelector('[role="listitem"]').classList).not.toContain('rounded-pill');
        expect(controller.recipientLookupTarget.querySelector("[data-ac-target='input']").disabled).toBe(false);
        expect(controller.recipientLookupTarget.querySelector("[data-ac-target='clearBtn']").disabled).toBe(true);
        expect(controller.submitButtonTarget.disabled).toBe(false);
    });

    test('does not add duplicate recipients', () => {
        controller.idsTarget.value = '10';
        controller.recipients.set('5', 'Mistress Example');
        controller.renderRecipients();
        controller.recipientLookupTarget.querySelector("[data-ac-target='hidden']").value = '5';
        controller.recipientLookupTarget.querySelector("[data-ac-target='hiddenText']").value = 'Mistress Example';

        controller.addRecipient();

        expect(controller.recipientIdsTarget.value).toBe('5');
        expect(controller.recipientListTarget.querySelectorAll('[role="listitem"]')).toHaveLength(1);
        expect(controller.recipientStatusTarget.textContent).toContain('already selected');
    });

    test('removes selected recipients and disables submit when none remain', () => {
        controller.idsTarget.value = '10';
        controller.recipients.set('5', 'Mistress Example');
        controller.renderRecipients();

        controller.recipientListTarget.querySelector('button').click();

        expect(controller.recipientIdsTarget.value).toBe('');
        expect(controller.submitButtonTarget.disabled).toBe(true);
    });
});
