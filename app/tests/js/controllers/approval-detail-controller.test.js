import '../../../assets/js/controllers/approval-detail-controller.js';

const ApprovalDetailController = window.Controllers['approval-detail'];

describe('ApprovalDetailController', () => {
    let controller;

    beforeEach(() => {
        controller = new ApprovalDetailController();
    });

    test('updateTriage reports a readable error when the server returns HTML', async () => {
        document.head.innerHTML = '<meta name="csrf-token" content="csrf-token">';
        document.body.innerHTML = `
            <form data-approval-triage-form="55">
                <select data-approval-triage-state><option value="new" selected>New</option></select>
                <textarea data-approval-triage-note>Review later</textarea>
                <button type="button" data-action="click->approval-detail#updateTriage">Save private triage</button>
                <span data-approval-triage-status role="status" aria-live="polite"></span>
            </form>
        `;
        Object.defineProperty(controller, 'hasTriageUrlValue', { value: true });
        Object.defineProperty(controller, 'triageUrlValue', { value: '/approvals/triage' });
        global.fetch = jest.fn().mockResolvedValue({
            ok: false,
            status: 403,
            headers: { get: () => 'text/html; charset=UTF-8' },
            json: jest.fn(),
        });

        const button = document.querySelector('button');
        await controller.updateTriage({
            preventDefault: jest.fn(),
            currentTarget: button,
        });

        const status = document.querySelector('[data-approval-triage-status]');
        expect(status).toHaveTextContent('Unable to save triage state. Please refresh the page and try again.');
        expect(window.KMP_accessibility.announce).toHaveBeenCalledWith(
            'Unable to save triage state. Please refresh the page and try again.',
            'assertive',
        );
        expect(button).not.toBeDisabled();
    });

    test('hides approval progress for feedback detail payloads', () => {
        const html = controller._renderDetail({
            ui: { hideProgress: true, feedbackResponse: true },
            context: {
                title: 'Recommendation Feedback',
                fields: [
                    { label: 'Recommended For', value: 'Example Recipient' },
                ],
            },
            progress: { required: 1, approved: 0, rejected: 0, status: 'pending' },
            responses: [],
        });

        document.body.innerHTML = html;

        expect(document.body.textContent).toContain('Recommendation Feedback');
        expect(document.body.textContent).toContain('Example Recipient');
        expect(document.body.textContent).not.toContain('Approval Progress');
        expect(document.body.textContent).not.toContain('No responses yet.');
        expect(document.body.querySelector('.col-12')).not.toBeNull();
        expect(document.body.querySelector('.col-md-6')).toBeNull();
    });

    test('keeps approval progress for standard detail payloads', () => {
        const html = controller._renderDetail({
            context: {
                title: 'Standard Approval',
                fields: [
                    { label: 'Entity', value: 'Example' },
                ],
            },
            progress: { required: 1, approved: 0, rejected: 0, status: 'pending' },
            responses: [],
        });

        document.body.innerHTML = html;

        expect(document.body.textContent).toContain('Approval Progress');
        expect(document.body.textContent).toContain('No responses yet.');
        expect(document.body.querySelectorAll('.col-md-6')).toHaveLength(2);
    });

    test('renders source entity links with accessible navigation text', () => {
        const html = controller._renderDetail({
            context: {
                title: 'Recommendation Approval',
                fields: [],
                entityUrl: '/awards/recommendations/view/12',
            },
            progress: { required: 1, approved: 0, rejected: 0, status: 'pending' },
            responses: [],
        });

        document.body.innerHTML = html;

        const link = document.querySelector('a[href="/awards/recommendations/view/12"]');
        expect(link).not.toBeNull();
        expect(link).toHaveTextContent('View Source');
        expect(link).toHaveAttribute('data-turbo-frame', '_top');
        expect(link.querySelector('i')).toHaveAttribute('aria-hidden', 'true');
    });

    test('renders private triage controls only when allowed', () => {
        const html = controller._renderDetail({
            approvalId: 55,
            ui: { canTriage: true },
            context: {
                title: 'Standard Approval',
                fields: [],
            },
            progress: { approvalId: 55, required: 1, approved: 0, rejected: 0, status: 'pending' },
            triage: {
                state: 'needs_research',
                note: 'Check court report',
                states: {
                    new: 'New',
                    needs_research: 'Needs Research',
                },
            },
            responses: [],
        });

        document.body.innerHTML = html;

        expect(document.querySelector('[data-approval-triage-form="55"]')).not.toBeNull();
        expect(document.querySelector('[data-approval-triage-state]')).toHaveValue('needs_research');
        expect(document.querySelector('[data-approval-triage-note]')).toHaveValue('Check court report');
        expect(document.body.textContent).toContain('Only you can see this triage note');
    });
});
