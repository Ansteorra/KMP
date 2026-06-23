import '../../../assets/js/controllers/mobile-approvals-controller.js';

const MobileApprovalsController = window.Controllers['mobile-approvals'];

describe('MobileApprovalsController', () => {
    let controller;

    beforeEach(() => {
        controller = new MobileApprovalsController();
    });

    test('saveTriage reports a readable error when the server returns HTML', async () => {
        document.head.innerHTML = '<meta name="csrf-token" content="csrf-token">';
        document.body.innerHTML = `
            <form data-mobile-triage-form="45">
                <select data-mobile-triage-state><option value="new" selected>New</option></select>
                <textarea data-mobile-triage-note>Review later</textarea>
                <button type="button" data-action="click->mobile-approvals#saveTriage">Save private triage</button>
                <span data-mobile-triage-status role="status" aria-live="polite"></span>
            </form>
        `;
        Object.defineProperty(controller, 'hasTriageUrlValue', { value: true });
        Object.defineProperty(controller, 'triageUrlValue', { value: '/approvals/triage' });
        controller._showToast = jest.fn();
        global.fetch = jest.fn().mockResolvedValue({
            ok: false,
            status: 403,
            headers: { get: () => 'text/html; charset=UTF-8' },
            json: jest.fn(),
        });

        const button = document.querySelector('button');
        await controller.saveTriage({
            preventDefault: jest.fn(),
            currentTarget: button,
        });

        const message = 'Unable to save triage state. Please refresh the page and try again.';
        expect(document.querySelector('[data-mobile-triage-status]')).toHaveTextContent(message);
        expect(controller._showToast).toHaveBeenCalledWith(message, 'danger');
        expect(button).not.toBeDisabled();
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

    test('renders source entity links in expanded mobile approval details', () => {
        const html = controller._renderDetail({
            id: 45,
            description: 'Review award recommendation',
            fields: [],
            entityUrl: '/awards/recommendations/view/12',
            triage: { states: { new: 'New' } },
            approverConfig: {},
        }, []);

        document.body.innerHTML = html;

        const link = document.querySelector('a[href="/awards/recommendations/view/12"]');
        expect(link).not.toBeNull();
        expect(link).toHaveTextContent('View Source');
        expect(link).toHaveAttribute('data-turbo-frame', '_top');
        expect(link.querySelector('i')).toHaveAttribute('aria-hidden', 'true');
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

    test('shows required bestowal gathering select when approving award approvals', () => {
        controller.element = document.createElement('div');
        controller._approvals = [{
            id: 48,
            progress: { required: 1, approved: 0 },
            approverConfig: {
                requiresBestowalGathering: true,
                bestowalGatheringOptions: [{ id: 9, label: 'Spring Crown - 2026-04-12' }],
            },
        }];
        controller.element.innerHTML = controller._renderResponseForm(controller._approvals[0]);

        const approve = controller.element.querySelector('.btn-approve');
        controller.selectDecision({ preventDefault: jest.fn(), currentTarget: approve });

        const section = controller.element.querySelector('[data-bestowal-gathering-section="48"]');
        const select = controller.element.querySelector('[data-bestowal-gathering-select="48"]');
        expect(section).not.toHaveAttribute('hidden');
        expect(select).toBeRequired();
        expect(select).toHaveAttribute('aria-required', 'true');
        expect(select.querySelector('option[value="9"]')).toHaveTextContent('Spring Crown - 2026-04-12');
    });

    test('submitResponse focuses missing bestowal gathering', async () => {
        controller.element = document.createElement('div');
        controller._approvals = [{
            id: 49,
            progress: { required: 1, approved: 0 },
            approverConfig: { requiresBestowalGathering: true },
        }];
        controller.element.innerHTML = controller._renderResponseForm(controller._approvals[0]);
        const approve = controller.element.querySelector('.btn-approve');
        controller.selectDecision({ preventDefault: jest.fn(), currentTarget: approve });

        await controller.submitResponse({
            preventDefault: jest.fn(),
            currentTarget: controller.element.querySelector('[data-submit-btn="49"]'),
        });

        expect(controller.element.querySelector('[data-bestowal-gathering-error="49"]')).not.toHaveAttribute('hidden');
        expect(controller.element.querySelector('[data-bestowal-gathering-select="49"]')).toHaveAttribute('aria-invalid', 'true');
    });
});
