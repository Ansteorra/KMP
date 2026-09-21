import '../../../plugins/Awards/Assets/js/controllers/approval-sync-controller.js';

const Progress = window.Controllers['awards-approval-sync'];

describe('Approval synchronization progress', () => {
    let controller;
    beforeEach(() => {
        jest.useFakeTimers();
        controller = new Progress();
        controller.statusTarget = document.createElement('p');
        controller.attentionTarget = document.createElement('ul');
        controller.urlValue = '/awards/approval-processes/sync-status/1';
        global.fetch = jest.fn();
    });
    afterEach(() => {
        controller.disconnect();
        jest.useRealTimers();
    });
    test('renders safe result text and polls only active runs', () => {
        controller.render({ id: 1, status: 'running', counts: { restarted: 2, pending: 1 }, attention: [] });
        expect(controller.statusTarget.textContent).toContain('2 restarted, 1 pending');
        expect(jest.getTimerCount()).toBe(1);
        controller.disconnect();
        controller.render({ id: 1, status: 'partial_failure', counts: { failed: 1 }, attention: [
            { recommendation_id: 8, status: 'failed', message: '<script>unsafe</script>' },
        ] });
        expect(controller.attentionTarget.querySelector('script')).toBeNull();
        expect(controller.attentionTarget.textContent).toContain('Recommendation #8');
        expect(jest.getTimerCount()).toBe(0);
    });
    test('a request failure leaves an actionable refresh message', async () => {
        fetch.mockResolvedValue({ ok: false });
        await controller.refresh();
        expect(controller.statusTarget.textContent).toContain('Refresh progress');
        expect(jest.getTimerCount()).toBe(0);
    });
    test('disconnect cancels in-flight requests and polling', () => {
        controller.abort = { abort: jest.fn() };
        controller.render({ id: 1, status: 'queued' });
        controller.disconnect();
        expect(controller.abort.abort).toHaveBeenCalled();
        expect(jest.getTimerCount()).toBe(0);
    });
});
