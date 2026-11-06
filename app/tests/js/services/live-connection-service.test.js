jest.mock('eventsource-parser', () => ({ createParser: jest.fn(() => ({ feed: jest.fn() })) }));
import { createParser } from 'eventsource-parser';
import { maintainLiveConnection } from '../../../assets/js/services/live-connection-service.js';

describe('authorized live lease transport', () => {
    let connection, signals, authorize, events, connected, disconnected;
    beforeEach(() => {
        jest.useFakeTimers(); jest.clearAllMocks(); signals = [];
        authorize = jest.fn(async () => ({ url: 'https://gateway.test/events', token: 'opaque-token', expiresAt: Date.now() / 1000 + 120 }));
        events = jest.fn(); connected = jest.fn(); disconnected = jest.fn();
        global.fetch = jest.fn(async (url, options) => {
            signals.push(options.signal);
            return { ok: true, body: { async *[Symbol.asyncIterator]() {
                await new Promise(resolve => options.signal.addEventListener('abort', resolve, { once: true }));
            } } };
        });
        connection = maintainLiveConnection({ authorize, onEvent: events, onConnected: connected,
            onDisconnected: disconnected, random: () => 0 });
    });
    afterEach(async () => { connection.close(); await connection.done; jest.useRealTimers(); });

    test('renews before expiry while retaining the old stream until the replacement is authorized and connected', async () => {
        await jest.advanceTimersByTimeAsync(1);
        expect(connected).toHaveBeenCalledTimes(1);
        let finish;
        authorize.mockImplementationOnce(() => new Promise(resolve => { finish = resolve; }));
        await jest.advanceTimersByTimeAsync(90000);
        expect(authorize).toHaveBeenCalledTimes(2);
        expect(signals[0].aborted).toBe(false);
        finish({ url: 'https://gateway.test/events', token: 'renewed-token', expiresAt: Date.now() / 1000 + 120 });
        await jest.advanceTimersByTimeAsync(1);
        expect(signals).toHaveLength(2);
        expect(signals[0].aborted).toBe(true);
        expect(signals[1].aborted).toBe(false);
        expect(connected).toHaveBeenCalledTimes(1);
        expect(disconnected).not.toHaveBeenCalled();
    });

    test('resumes complete events with full-precision IDs, discards duplicates, and clears an ahead cursor on replay gaps', async () => {
        await jest.advanceTimersByTimeAsync(1);
        const parser = createParser.mock.calls[0][0];
        parser.onEvent({ id: '1793980000000-9007199254740993', data: 'first' });
        parser.onEvent({ id: '1793980000000-9007199254740992', data: 'obsolete' });
        expect(events).toHaveBeenCalledTimes(1);
        await jest.advanceTimersByTimeAsync(90000);
        expect(fetch.mock.calls[1][1].headers['Last-Event-ID']).toBe('1793980000000-9007199254740993');
        const renewed = createParser.mock.calls[1][0];
        renewed.onEvent({ id: '1793980000000-9007199254740993', data: 'duplicate' });
        expect(events).toHaveBeenCalledTimes(1);
        renewed.onEvent({ data: 'gap' });
        renewed.onEvent({ id: '1-0', data: 'fresh history' });
        expect(events).toHaveBeenCalledTimes(3);
    });

    test('a closed document cancels its stream and timers and cannot open another stream', async () => {
        await jest.advanceTimersByTimeAsync(1);
        connection.close(); await connection.done;
        await jest.advanceTimersByTimeAsync(180000);
        expect(fetch).toHaveBeenCalledTimes(1);
        expect(disconnected).toHaveBeenCalledTimes(1);
        expect(signals[0].aborted).toBe(true);
    });

    test('invalid renewal metadata retains the current lease and retries at a bounded interval', async () => {
        await jest.advanceTimersByTimeAsync(1);
        authorize.mockResolvedValueOnce({ url: 'https://gateway.test/events', token: 'bad-metadata', expiresIn: 0 });
        await jest.advanceTimersByTimeAsync(90000);
        expect(fetch).toHaveBeenCalledTimes(1);
        expect(signals[0].aborted).toBe(false);
        await jest.advanceTimersByTimeAsync(3001);
        expect(fetch).toHaveBeenCalledTimes(2);
        expect(signals[0].aborted).toBe(true);
        expect(disconnected).not.toHaveBeenCalled();
    });
});
