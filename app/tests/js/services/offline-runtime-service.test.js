jest.mock('../../../assets/js/services/offline-vault-service.js', () => ({ __esModule: true, default: { openTrusted: jest.fn(), trust: jest.fn(), read: jest.fn().mockResolvedValue({}) } }));
jest.mock('../../../assets/js/services/offline-data-service.js', () => ({ currentOfflineContext: jest.fn(), refreshOfflineSnapshot: jest.fn() }));
jest.mock('../../../assets/js/services/rsvp-cache-service.js', () => ({ __esModule: true, default: { getPendingCount: jest.fn(), syncPendingRsvps: jest.fn() } }));
let runtime, vault, data, rsvps;
beforeEach(() => {
    jest.resetModules();
    runtime = require('../../../assets/js/services/offline-runtime-service.js');
    vault = require('../../../assets/js/services/offline-vault-service.js').default;
    data = require('../../../assets/js/services/offline-data-service.js');
    rsvps = require('../../../assets/js/services/rsvp-cache-service.js').default;
    Object.defineProperty(navigator, 'onLine', { configurable: true, value: true });
    global.MessageChannel = class {
        constructor() { this.port1 = { close() {} }; this.port2 = { receive: event => this.port1.onmessage(event) }; }
    };
    const worker = { state: 'activated', postMessage: (message, ports) => ports[0].receive({ data: { ready: true } }) };
    Object.defineProperty(navigator, 'serviceWorker', { configurable: true, value: { register: jest.fn().mockResolvedValue({ active: worker }) } });
    vault.generation = 0;
    vault.openTrusted.mockResolvedValue(true);
    rsvps.getPendingCount.mockResolvedValue(0);
});
test('untrusted pages never enroll or download private data automatically', async () => {
    vault.openTrusted.mockResolvedValue(false);
    await runtime.updateTrustedDevice();
    expect(vault.trust).not.toHaveBeenCalled();
    expect(data.currentOfflineContext).not.toHaveBeenCalled();
    expect(data.refreshOfflineSnapshot).not.toHaveBeenCalled();
});
test('ordinary page updates send queued requests first and throttle complete snapshots', async () => {
    rsvps.getPendingCount.mockResolvedValue(1);
    rsvps.syncPendingRsvps.mockResolvedValue({ failed: 0, success: 1 });
    await Promise.all([runtime.updateTrustedDevice(), runtime.updateTrustedDevice()]);
    await runtime.updateTrustedDevice();
    expect(data.refreshOfflineSnapshot).toHaveBeenCalledTimes(1);
    expect(rsvps.syncPendingRsvps.mock.invocationCallOrder[0]).toBeLessThan(data.refreshOfflineSnapshot.mock.invocationCallOrder[0]);
    expect(runtime.offlineStatus.ready).toBe(true);
});
test('expired server sessions keep local access and automatically retry later', async () => {
    data.currentOfflineContext.mockRejectedValueOnce(new Error('Sign in online to continue.'));
    await runtime.updateTrustedDevice();
    expect(runtime.offlineStatus.message).toContain('saved information is kept');
    expect(data.refreshOfflineSnapshot).not.toHaveBeenCalled();
    await runtime.updateTrustedDevice();
    expect(data.refreshOfflineSnapshot).toHaveBeenCalledTimes(1);
});
test('offline opening never requires server contact', async () => {
    Object.defineProperty(navigator, 'onLine', { configurable: true, value: false });
    await runtime.updateTrustedDevice();
    expect(vault.openTrusted).toHaveBeenCalled();
    expect(data.currentOfflineContext).not.toHaveBeenCalled();
});

test('an account transition during preparation cancels the trust choice', async () => {
    data.currentOfflineContext.mockImplementationOnce(async () => { vault.generation++; });
    await expect(runtime.trustThisDevice()).rejects.toThrow('sign-in changed');
    expect(vault.trust).not.toHaveBeenCalled();
});


test('reconnection during an outdated failing request immediately retries the fresh session', async () => {
    let rejectOld;
    data.currentOfflineContext.mockImplementationOnce(() => new Promise((resolve, reject) => { rejectOld = reject; }));
    const old = runtime.updateTrustedDevice();
    await Promise.resolve();
    const reconnected = runtime.updateTrustedDevice(true);
    rejectOld(new Error('Sign in online to continue.'));
    await Promise.all([old, reconnected]);
    expect(data.currentOfflineContext).toHaveBeenCalledTimes(2);
    expect(data.refreshOfflineSnapshot).toHaveBeenCalledTimes(1);
    expect(runtime.offlineStatus.ready).toBe(true);
});

test('an RSVP queued during a successful refresh sends immediately afterward', async () => {
    let finishSnapshot;
    let snapshotStarted;
    const started = new Promise(resolve => { snapshotStarted = resolve; });
    data.refreshOfflineSnapshot.mockImplementationOnce(() => new Promise(resolve => {
        finishSnapshot = resolve;
        snapshotStarted();
    }));
    const first = runtime.updateTrustedDevice();
    await started;
    rsvps.getPendingCount.mockResolvedValue(1);
    rsvps.syncPendingRsvps.mockResolvedValue({ failed: 0, success: 1 });
    const queued = runtime.updateTrustedDevice(true);
    expect(rsvps.syncPendingRsvps).not.toHaveBeenCalled();
    finishSnapshot();
    await Promise.all([first, queued]);
    expect(rsvps.syncPendingRsvps).toHaveBeenCalledTimes(1);
    expect(data.refreshOfflineSnapshot).toHaveBeenCalledTimes(2);
    await runtime.updateTrustedDevice();
    expect(data.refreshOfflineSnapshot).toHaveBeenCalledTimes(2);
});
