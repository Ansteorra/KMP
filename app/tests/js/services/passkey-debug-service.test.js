import { passkeyDebug, tracePasskey, tracePasskeyError } from '../../../assets/js/services/passkey-debug-service.js';

let log;
const report = () => JSON.parse(passkeyDebug.report());
beforeEach(() => { passkeyDebug.clear(); log = jest.spyOn(console, 'info').mockImplementation(() => {}); });
afterEach(() => { passkeyDebug.clear(); log.mockRestore(); });

test('recording is opt-in and stop retains a copyable report without recording more events', () => {
    tracePasskey('create-start');
    expect(report().events).toEqual([]);
    expect(log).not.toHaveBeenCalled();
    expect(window.KMP_passkeyDebug).toBe(passkeyDebug);
    passkeyDebug.start();
    tracePasskey('create-start');
    passkeyDebug.stop();
    tracePasskey('create-returned');
    expect(report().events.map(event => event.stage)).toEqual(['debug-start', 'create-start', 'debug-stop']);
    expect(report().environment.browser).toBe(navigator.userAgent);
    passkeyDebug.clear();
    expect(report().environment).toBeNull();
    expect(report().events).toEqual([]);
});

test('reports and console output exclude secrets, arbitrary text, identifiers, and raw extension objects', () => {
    passkeyDebug.start();
    const secret = 'SECRET-password-credential-salt-PRF';
    tracePasskey('get-prf', { prfBytes: 32, credentialMatches: true, prfEnabled: null, hasPrf: true,
        transports: ['internal', secret], attachment: secret, password: secret, credentialId: secret,
        prf: { results: { first: secret } }, challenge: secret, error: secret });
    tracePasskey(secret, { prfBytes: 32 });
    tracePasskeyError('get-error', { name: 'OperationError', message: secret, stack: secret, cause: secret });
    tracePasskeyError('setup-error', { name: secret, code: secret });
    expect(report().events[1]).toMatchObject({ prfBytes: 32, credentialMatches: true, prfEnabled: null, hasPrf: true, transports: ['internal'] });
    expect(report().events[2].error).toBe('OperationError');
    expect(report().events[3].error).toBe('UnknownError');
    expect(passkeyDebug.report()).not.toContain(secret);
    expect(JSON.stringify(log.mock.calls)).not.toContain(secret);
});

test('the in-memory buffer is bounded and a new recording removes the previous attempt', () => {
    passkeyDebug.start();
    for (let i = 0; i < 250; i++) tracePasskey('create-start');
    expect(report().events).toHaveLength(200);
    expect(report().dropped).toBe(51);
    passkeyDebug.start();
    expect(report().events.map(event => event.stage)).toEqual(['debug-start']);
    expect(report().dropped).toBe(0);
});

test('broken console logging cannot interrupt passkey operations or lose the report', () => {
    passkeyDebug.start();
    log.mockImplementation(() => { throw new Error('Console unavailable'); });
    expect(() => tracePasskey('create-start')).not.toThrow();
    expect(report().events.at(-1).stage).toBe('create-start');
});
