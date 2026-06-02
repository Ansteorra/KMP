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
});
