import '../../../assets/js/controllers/approval-detail-controller.js';

const ApprovalDetailController = window.Controllers['approval-detail'];

describe('ApprovalDetailController', () => {
    let controller;

    beforeEach(() => {
        controller = new ApprovalDetailController();
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
