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
        expect(document.querySelector('[data-approval-comment="43"]')).toHaveAccessibleName('Comment');
        expect(document.querySelector('.btn-approve')).toHaveAttribute('aria-pressed', 'false');
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

    test('renders private triage controls with labels and help text', () => {
        const html = controller._renderTriageForm({
            id: 45,
            triage: {
                state: 'ready_to_decide',
                note: 'Ready after checking notes',
                states: {
                    new: 'New',
                    ready_to_decide: 'Ready to Decide',
                },
            },
        });

        document.body.innerHTML = html;

        expect(document.querySelector('[data-mobile-triage-form="45"]')).not.toBeNull();
        expect(document.querySelector('[data-mobile-triage-state]')).toHaveValue('ready_to_decide');
        expect(document.querySelector('[data-mobile-triage-note]')).toHaveValue('Ready after checking notes');
        expect(document.body.textContent).toContain('Only you can see this triage note');
    });

    test('mobile cards expose keyboard-operable expansion state', () => {
        const html = controller._renderCard({
            id: 46,
            title: 'Keyboard approval',
            requester: 'Requester',
            icon: 'bi-check2-square',
            progress: { required: 1, approved: 0 },
            approverConfig: {},
            triage: { stateLabel: 'Reviewing' },
        });

        document.body.innerHTML = html;
        const summary = document.querySelector('.approval-card-summary');

        expect(summary).toHaveAttribute('role', 'button');
        expect(summary).toHaveAttribute('tabindex', '0');
        expect(summary).toHaveAttribute('aria-expanded', 'false');
        expect(summary).toHaveAttribute('aria-controls', 'approval-card-detail-46');
        expect(summary.getAttribute('data-action')).toContain('keydown->mobile-approvals#toggleCardWithKeyboard');
        expect(document.body.textContent).toContain('Reviewing');
    });

    test('selectDecision synchronizes pressed state and validation ARIA', () => {
        controller.element = document.createElement('div');
        controller._approvals = [{ id: 47, progress: { required: 1, approved: 0 }, approverConfig: {} }];
        controller.element.innerHTML = controller._renderResponseForm(controller._approvals[0]);

        const reject = controller.element.querySelector('.btn-reject');
        controller.selectDecision({ preventDefault: jest.fn(), currentTarget: reject });

        expect(reject).toHaveClass('active');
        expect(reject).toHaveAttribute('aria-pressed', 'true');
        expect(controller.element.querySelector('.btn-approve')).toHaveAttribute('aria-pressed', 'false');
        expect(controller.element.querySelector('[data-comment-required="47"]')).not.toHaveAttribute('hidden');
    });
});
