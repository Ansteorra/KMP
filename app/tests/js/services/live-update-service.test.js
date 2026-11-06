jest.mock('eventsource-parser', () => ({ createParser: jest.fn() }));
jest.mock('../../../assets/js/services/ui-navigation-service.js', () => ({ driveEnabled: () => true, invalidateUiSession: jest.fn() }));
jest.mock('../../../assets/js/services/form-state-service.js', () => ({ dirtyWithin: jest.fn(() => false) }));
jest.mock('../../../assets/js/services/page-refresh-service.js', () => ({
    refreshAfterChanges: jest.fn().mockResolvedValue(), refreshScreen: jest.fn().mockResolvedValue(true),
}));
import { installLiveUpdates } from '../../../assets/js/services/live-update-service.js';
import { dirtyWithin } from '../../../assets/js/services/form-state-service.js';
import { refreshAfterChanges, refreshScreen } from '../../../assets/js/services/page-refresh-service.js';
import { invalidateUiSession } from '../../../assets/js/services/ui-navigation-service.js';

describe('live invalidation coordination', () => {
    const Turbo = { StreamActions: {}, cache: { clear: jest.fn() }, visit: jest.fn() };
    beforeAll(() => installLiveUpdates(Turbo));
    beforeEach(() => {
        jest.useFakeTimers(); jest.clearAllMocks();
        dirtyWithin.mockReturnValue(false);
        document.head.innerHTML = '<meta name="kmp-live-subscriptions-url" content="/live/subscriptions">';
        document.body.innerHTML = '<main><section id="notes" data-refresh-on="notes">Current notes</section></main>';
        history.replaceState({}, '', '/members/view/1?tab=notes#place');
        global.fetch = jest.fn().mockResolvedValue({ status: 400 });
        window.dispatchEvent(new Event('kmp:navigation-ready'));
    });
    afterEach(() => { window.dispatchEvent(new Event('pagehide')); jest.useRealTimers(); });
    function invalidate(changes, requestId) {
        const stream = document.createElement('turbo-stream');
        stream.setAttribute('changes', JSON.stringify(changes));
        if (requestId) stream.setAttribute('request-id', requestId);
        Turbo.StreamActions['kmp-invalidate'].call(stream);
    }

    test('coalesces duplicate signals into one visible-region reconciliation', async () => {
        invalidate(['notes']); invalidate(['notes']);
        await jest.advanceTimersByTimeAsync(151);
        expect(refreshAfterChanges).toHaveBeenCalledTimes(1);
        expect(refreshAfterChanges).toHaveBeenCalledWith(['notes'], { visibleOnly: true });
        expect(Turbo.visit).not.toHaveBeenCalled();
        expect(location.hash).toBe('#place');
    });

    test('ignores malformed signals while reconciling an actor event whose response may have been lost', async () => {
        expect(() => invalidate({ unexpected: 'object' }, 'own')).not.toThrow();
        invalidate(['notes'], 'own'); invalidate(['unsafe"selector']);
        await jest.advanceTimersByTimeAsync(151);
        expect(refreshAfterChanges).toHaveBeenCalledTimes(1);
        expect(Turbo.visit).not.toHaveBeenCalled();
    });

    test('defers an undeclared dependent refresh while work is dirty, then preserves the URL on reconciliation', async () => {
        dirtyWithin.mockReturnValue(true);
        invalidate(['record']);
        await jest.advanceTimersByTimeAsync(151);
        expect(Turbo.visit).not.toHaveBeenCalled();
        expect(document.getElementById('kmp-live-status')).toHaveTextContent('Finish or cancel');
        dirtyWithin.mockReturnValue(false);
        document.dispatchEvent(new Event('hidden.bs.modal'));
        await jest.advanceTimersByTimeAsync(151);
        expect(refreshScreen).toHaveBeenCalledTimes(1);
        expect(Turbo.visit).not.toHaveBeenCalled();
    });

    test('mixed changes update clean declared summaries while an unrelated draft blocks the screen', async () => {
        dirtyWithin.mockReturnValue(true);
        invalidate(['record', 'notes']);
        await jest.advanceTimersByTimeAsync(151);
        expect(refreshAfterChanges).toHaveBeenCalledWith(['notes'], { visibleOnly: true });
        expect(refreshScreen).not.toHaveBeenCalled();
        dirtyWithin.mockReturnValue(false);
        window.dispatchEvent(new Event('kmp:draft-settled'));
        await jest.advanceTimersByTimeAsync(151);
        expect(refreshScreen).toHaveBeenCalledTimes(1);
    });

    test('revoked subscription access invalidates the session instead of retaining the old screen', async () => {
        window.dispatchEvent(new Event('pagehide'));
        fetch.mockResolvedValueOnce({ status: 403 });
        window.dispatchEvent(new Event('kmp:navigation-ready'));
        await jest.advanceTimersByTimeAsync(1);
        expect(invalidateUiSession).toHaveBeenCalledTimes(1);
        expect(refreshScreen).not.toHaveBeenCalled();
    });
});
