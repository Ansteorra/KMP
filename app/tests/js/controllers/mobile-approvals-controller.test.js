import '../../../assets/js/controllers/mobile-approvals-controller.js';

const MobileApprovalsController = window.Controllers['mobile-approvals'];

describe('MobileApprovalsController', () => {
    let controller;

    beforeEach(() => {
        controller = new MobileApprovalsController();
    });

    test('renders feedback requests without approve or reject buttons', () => {
        const html = controller._renderResponseForm({
            id: 42,
            approverConfig: {
                feedbackResponse: true,
                requiresComment: true,
            },
        });

        document.body.innerHTML = html;

        expect(document.body.textContent).toContain('Feedback');
        expect(document.body.textContent).toContain('Send Feedback');
        expect(document.querySelector('.btn-approve')).toBeNull();
        expect(document.querySelector('.btn-reject')).toBeNull();
        expect(document.querySelector('[data-approval-form-id="42"]')).toHaveAttribute('data-selected-decision', 'approve');
        expect(document.querySelector('[data-submit-btn="42"]')).not.toBeDisabled();
    });

    test('keeps approve and reject buttons for standard approvals', () => {
        const html = controller._renderResponseForm({
            id: 43,
            approverConfig: {},
        });

        document.body.innerHTML = html;

        expect(document.body.textContent).toContain('Approve');
        expect(document.body.textContent).toContain('Reject');
        expect(document.querySelector('[data-approval-form-id="43"]')).not.toHaveAttribute('data-selected-decision');
        expect(document.querySelector('[data-submit-btn="43"]')).toBeDisabled();
    });

    test('renders configured feedback decision options without approve or reject buttons', () => {
        const html = controller._renderResponseForm({
            id: 44,
            approverConfig: {
                feedbackResponse: true,
                requiresComment: false,
                decisionPromptLabel: 'Polling Response',
                decisionOptions: [
                    { value: 'for', label: 'For' },
                    { value: 'against', label: 'Against' },
                    { value: 'abstain', label: 'Abstain' },
                ],
            },
        });

        document.body.innerHTML = html;

        expect(document.body.textContent).toContain('For');
        expect(document.body.textContent).toContain('Against');
        expect(document.body.textContent).toContain('Abstain');
        expect(document.querySelector('legend')).toHaveTextContent('Polling Response');
        expect(document.querySelector('.btn-approve')).toBeNull();
        expect(document.querySelector('.btn-reject')).toBeNull();
        expect(document.querySelector('[data-approval-form-id="44"]')).not.toHaveAttribute('data-selected-decision');
        expect(document.querySelector('[data-submit-btn="44"]')).toBeDisabled();
    });
});
