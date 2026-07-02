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

    test('loads approvals one page at a time and exposes load-more status', async () => {
        Object.defineProperty(controller, 'perPageValue', { value: 25 });
        controller.initialize();
        document.body.innerHTML = `
            <div data-mobile-approvals-target="loading"></div>
            <div data-mobile-approvals-target="empty"></div>
            <div data-mobile-approvals-target="error"></div>
            <div data-mobile-approvals-target="list"></div>
            <span data-mobile-approvals-target="countBadge"></span>
            <div data-mobile-approvals-target="loadMore" hidden>
                <button type="button" data-mobile-approvals-target="loadMoreBtn"></button>
            </div>
            <div data-mobile-approvals-target="status"></div>
        `;
        const targets = {
            loading: document.querySelector('[data-mobile-approvals-target="loading"]'),
            empty: document.querySelector('[data-mobile-approvals-target="empty"]'),
            error: document.querySelector('[data-mobile-approvals-target="error"]'),
            list: document.querySelector('[data-mobile-approvals-target="list"]'),
            countBadge: document.querySelector('[data-mobile-approvals-target="countBadge"]'),
            loadMore: document.querySelector('[data-mobile-approvals-target="loadMore"]'),
            loadMoreBtn: document.querySelector('[data-mobile-approvals-target="loadMoreBtn"]'),
            status: document.querySelector('[data-mobile-approvals-target="status"]'),
        };
        for (const [name, target] of Object.entries(targets)) {
            Object.defineProperty(controller, `${name}Target`, { value: target });
            Object.defineProperty(controller, `has${name.charAt(0).toUpperCase()}${name.slice(1)}Target`, { value: true });
        }
        Object.defineProperty(controller, 'dataUrlValue', { value: '/approvals/mobile-data' });
        const approval = (id) => ({
            id,
            title: `Approval ${id}`,
            requester: 'Requester',
            icon: 'bi-check2-square',
            progress: { required: 1, approved: 0, rejected: 0 },
            approverConfig: {},
            triage: {},
        });
        controller.fetchWithRetry = jest.fn()
            .mockResolvedValueOnce({
                json: jest.fn().mockResolvedValue({
                    approvals: [approval(1)],
                    pagination: { page: 1, perPage: 25, total: 3, pageCount: 2, hasNextPage: true },
                }),
            })
            .mockResolvedValueOnce({
                json: jest.fn().mockResolvedValue({
                    approvals: [approval(2), approval(3)],
                    pagination: { page: 2, perPage: 25, total: 3, pageCount: 2, hasNextPage: false },
                }),
            });

        await controller.loadApprovals();

        expect(controller.fetchWithRetry.mock.calls[0][0]).toContain('page=1');
        expect(controller.fetchWithRetry.mock.calls[0][0]).toContain('per_page=25');
        expect(targets.countBadge).toHaveTextContent('1 of 3 pending');
        expect(targets.loadMore).not.toHaveAttribute('hidden');
        expect(targets.loadMoreBtn).toHaveTextContent('Load more (2 remaining)');

        await controller.loadMore();

        expect(controller.fetchWithRetry.mock.calls[1][0]).toContain('page=2');
        expect(controller.fetchWithRetry.mock.calls[1][0]).toContain('per_page=25');
        expect(targets.countBadge).toHaveTextContent('3 pending');
        expect(targets.loadMore).toHaveAttribute('hidden');
        expect(targets.status).toHaveTextContent('Loaded 3 of 3 pending approvals.');
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

    test('shows optional bestowal gathering select when approving award approvals', () => {
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
        expect(select).not.toBeRequired();
        expect(select).not.toHaveAttribute('aria-required');
        expect(select.querySelector('option[value="9"]')).toHaveTextContent('Spring Crown - 2026-04-12');
    });

    test('submitResponse allows missing optional bestowal gathering', async () => {
        document.head.innerHTML = '<meta name="csrf-token" content="csrf-token">';
        controller.element = document.createElement('div');
        Object.defineProperty(controller, 'listTarget', { value: document.createElement('div') });
        global.fetch = jest.fn()
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({
                    success: true,
                    approvals: [],
                    pagination: { total: 0, page: 1, perPage: 10, hasNextPage: false },
                }),
            });
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

        expect(global.fetch).toHaveBeenCalled();
        expect(controller.element.querySelector('[data-bestowal-gathering-error="49"]')).toHaveAttribute('hidden');
        expect(controller.element.querySelector('[data-bestowal-gathering-select="49"]')).toHaveAttribute('aria-invalid', 'false');
    });

    test('submitResponse shows service reason when approval response fails', async () => {
        document.head.innerHTML = '<meta name="csrf-token" content="csrf-token">';
        controller.element = document.createElement('div');
        Object.defineProperty(controller, 'listTarget', { value: document.createElement('div') });
        controller._approvals = [{
            id: 50,
            progress: { required: 1, approved: 0 },
            approverConfig: {},
        }];
        controller.element.innerHTML = controller._renderResponseForm(controller._approvals[0]);
        controller._showToast = jest.fn();
        Object.defineProperty(controller, 'online', { value: true });
        Object.defineProperty(controller, 'recordUrlValue', { value: '/approvals/record' });
        const approve = controller.element.querySelector('.btn-approve');
        controller.selectDecision({ preventDefault: jest.fn(), currentTarget: approve });
        global.fetch = jest.fn().mockResolvedValue({
            ok: true,
            headers: { get: () => 'application/json' },
            json: jest.fn().mockResolvedValue({
                success: false,
                reason: 'You are not eligible to respond to this approval.',
            }),
        });

        await controller.submitResponse({
            preventDefault: jest.fn(),
            currentTarget: controller.element.querySelector('[data-submit-btn="50"]'),
        });

        expect(controller._showToast).toHaveBeenCalledWith(
            'You are not eligible to respond to this approval.',
            'danger',
        );
    });
});
