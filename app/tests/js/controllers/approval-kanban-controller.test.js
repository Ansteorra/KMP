import '../../../assets/js/controllers/approval-kanban-controller.js';

const ApprovalKanbanController = window.Controllers['approval-kanban'];

describe('ApprovalKanbanController', () => {
    let controller;

    beforeEach(() => {
        document.head.innerHTML = '<meta name="csrf-token" content="csrf-token">';
        document.body.innerHTML = `
            <div class="approval-kanban">
                <section class="approval-kanban-lane" data-lane-state="new" data-total-count="1">
                    <header class="approval-kanban-lane-header">
                        <p class="approval-kanban-lane-subtitle">1 approval</p>
                        <span class="badge">1</span>
                    </header>
                    <div class="approval-kanban-cards" data-approval-kanban-target="cardList" data-lane-state="new">
                        <article class="approval-kanban-card" data-approval-id="10" data-current-state="new" data-triage-note="Private note" data-card-title="Award approval">
                            <select data-action="change->approval-kanban#moveFromSelect">
                                <option value="new" selected>New</option>
                                <option value="reviewing">Reviewing</option>
                            </select>
                        </article>
                    </div>
                    <footer class="approval-kanban-lane-footer">
                        <button type="button" data-approval-kanban-load-more="true" data-next-url="/approvals/kanban-lane?triage_state=new&page=2">
                            <span>Load more</span>
                        </button>
                    </footer>
                </section>
                <section class="approval-kanban-lane" data-lane-state="reviewing" data-total-count="0">
                    <header class="approval-kanban-lane-header">
                        <p class="approval-kanban-lane-subtitle">0 approvals</p>
                        <span class="badge">0</span>
                    </header>
                    <div class="approval-kanban-cards" data-approval-kanban-target="cardList" data-lane-state="reviewing">
                        <div class="approval-kanban-empty">No approvals here.</div>
                    </div>
                </section>
            </div>
        `;
        controller = new ApprovalKanbanController();
        controller.element = document.querySelector('.approval-kanban');
        controller.observer = null;
        Object.defineProperty(controller, 'triageUrlValue', { value: '/approvals/triage' });
    });

    test('moveFromSelect saves triage state and moves the card with an announcement', async () => {
        global.fetch = jest.fn().mockResolvedValue({
            ok: true,
            headers: { get: () => 'application/json' },
            json: jest.fn().mockResolvedValue({ success: true }),
        });

        const select = document.querySelector('select');
        select.value = 'reviewing';
        await controller.moveFromSelect({ currentTarget: select });

        const card = document.querySelector('.approval-kanban-card');
        expect(fetch).toHaveBeenCalledWith('/approvals/triage', expect.objectContaining({
            method: 'POST',
            headers: expect.objectContaining({
                Accept: 'application/json',
                'X-CSRF-Token': 'csrf-token',
            }),
        }));
        const body = fetch.mock.calls[0][1].body;
        expect(body.get('approvalId')).toBe('10');
        expect(body.get('state')).toBe('reviewing');
        expect(body.get('note')).toBe('Private note');
        expect(card.closest('[data-lane-state="reviewing"]')).not.toBeNull();
        expect(card.dataset.currentState).toBe('reviewing');
        expect(document.querySelector('[data-lane-state="reviewing"] .badge')).toHaveTextContent('1');
        expect(document.querySelector('[data-lane-state="new"] .approval-kanban-empty')).not.toBeNull();
        expect(window.KMP_accessibility.announce).toHaveBeenCalledWith('Award approval moved.', 'polite');
    });

    test('loadMore appends returned cards and replaces lane footer', async () => {
        global.fetch = jest.fn().mockResolvedValue({
            ok: true,
            text: jest.fn().mockResolvedValue(`
                <turbo-frame id="approval-kanban-lane-new">
                    <section class="approval-kanban-lane" data-lane-state="new">
                        <div class="approval-kanban-cards" data-approval-kanban-target="cardList">
                            <article class="approval-kanban-card" data-approval-id="11">Next approval</article>
                        </div>
                        <footer class="approval-kanban-lane-footer">
                            <span class="approval-kanban-lane-complete">All approvals loaded</span>
                        </footer>
                    </section>
                </turbo-frame>
            `),
        });

        const button = document.querySelector('[data-next-url]');
        await controller.loadMore({ currentTarget: button, preventDefault: jest.fn() });

        expect(fetch).toHaveBeenCalledWith('/approvals/kanban-lane?triage_state=new&page=2', expect.any(Object));
        expect(document.querySelector('[data-approval-id="11"]')).not.toBeNull();
        expect(document.querySelector('.approval-kanban-lane-complete')).toHaveTextContent('All approvals loaded');
        expect(window.KMP_accessibility.announce).toHaveBeenCalledWith('More approvals loaded.', 'polite');
    });

    test('loadMore decodes escaped ampersands and drops polluted amp query parameters', async () => {
        global.fetch = jest.fn().mockResolvedValue({
            ok: true,
            text: jest.fn().mockResolvedValue(`
                <section class="approval-kanban-lane" data-lane-state="new">
                    <div class="approval-kanban-cards" data-approval-kanban-target="cardList"></div>
                    <footer class="approval-kanban-lane-footer">
                        <span class="approval-kanban-lane-complete">All approvals loaded</span>
                    </footer>
                </section>
            `),
        });

        const button = document.querySelector('[data-next-url]');
        button.dataset.nextUrl = '/approvals/kanban-lane?view_id=sys-approvals-triage-board&amp;amp%3Bpage=2&amp;triage_state=new&amp;page=2';

        await controller.loadMore({ currentTarget: button, preventDefault: jest.fn() });

        expect(fetch).toHaveBeenCalledWith(
            '/approvals/kanban-lane?view_id=sys-approvals-triage-board&triage_state=new&page=2',
            expect.any(Object),
        );
    });

    test('connect does not auto-load visible load more buttons', () => {
        global.fetch = jest.fn();

        controller.connect();

        const button = document.querySelector('[data-next-url]');
        expect(button).not.toBeDisabled();
        expect(fetch).not.toHaveBeenCalled();
    });

    test('handleBoardClick delegates nested load more button clicks', async () => {
        global.fetch = jest.fn().mockResolvedValue({
            ok: true,
            text: jest.fn().mockResolvedValue(`
                <section class="approval-kanban-lane" data-lane-state="new">
                    <div class="approval-kanban-cards" data-approval-kanban-target="cardList">
                        <article class="approval-kanban-card" data-approval-id="12">Delegated approval</article>
                    </div>
                    <footer class="approval-kanban-lane-footer">
                        <span class="approval-kanban-lane-complete">All approvals loaded</span>
                    </footer>
                </section>
            `),
        });

        const preventDefault = jest.fn();
        await controller.handleBoardClick({
            target: document.querySelector('[data-approval-kanban-load-more] span'),
            preventDefault,
        });

        expect(preventDefault).toHaveBeenCalled();
        expect(fetch).toHaveBeenCalledWith('/approvals/kanban-lane?triage_state=new&page=2', expect.any(Object));
        expect(document.querySelector('[data-approval-id="12"]')).not.toBeNull();
    });
});
